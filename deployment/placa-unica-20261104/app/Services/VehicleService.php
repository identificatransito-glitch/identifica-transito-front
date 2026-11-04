<?php

namespace App\Services;

use App\Enums\VehicleUsageProfileEnum;
use App\Jobs\FillVehicleInfoJob;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class VehicleService
{
    public function paginate(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Vehicle::query()
            ->with([
                'tagPurchases' => static function ($query) {
                    $query->orderByDesc('id');
                },
            ])
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function create(array $payload, User $user): Vehicle
    {
        if (isset($payload['usage_profile'])) {
            $this->validateUsageProfile((int) $payload['usage_profile'], $payload, $user);
        }

        $payload['user_id'] = $user->id;

        return VehiclePlateGuard::save((string)($payload['plate'] ?? ''), null, function (string $plate) use ($payload) {
            $payload['plate']=$plate;
            $vehicle=Vehicle::query()->create($payload);
            FillVehicleInfoJob::dispatch($vehicle->id)->afterCommit();
            return $vehicle;
        });
    }

    public function update(Vehicle $vehicle, array $payload, User $user): Vehicle
    {
        $this->ensureUserOwnsVehicle($vehicle, $user);

        if (isset($payload['usage_profile'])) {
            $this->validateUsageProfile((int) $payload['usage_profile'], $payload, $user, $vehicle);
        }

        return VehiclePlateGuard::save((string)($payload['plate'] ?? $vehicle->plate), $vehicle->id, function (string $plate) use ($vehicle,$payload) {
            $payload['plate']=$plate;
            $vehicle->fill($payload);
            $vehicle->save();
            return $vehicle;
        });
    }

    public function delete(Vehicle $vehicle, User $user): void
    {
        $this->ensureUserOwnsVehicle($vehicle, $user);

        $vehicle->delete();
    }

    public function ensureUserOwnsVehicle(Vehicle $vehicle, User $user): void
    {
        if ($vehicle->user_id !== $user->id) {
            throw new AuthorizationException('Você não tem permissão para acessar este veículo.');
        }
    }

    private function validateUsageProfile(int $usageProfile, array $payload, User $user, ?Vehicle $vehicle = null): void
    {
        $profile = VehicleUsageProfileEnum::tryFrom($usageProfile);

        if ($profile === VehicleUsageProfileEnum::Gestante && $user->gender === 'M') {
            throw ValidationException::withMessages([
                'usage_profile' => 'O perfil "gestante" não é permitido para usuários do sexo masculino.',
            ]);
        }

        if ($profile === VehicleUsageProfileEnum::PessoaIdosa) {
            $birthDate = $user->birth_date;

            if (!$birthDate || now()->diffInYears($birthDate, true) < 60) {
                throw ValidationException::withMessages([
                    'usage_profile' => 'O perfil "pessoa idosa" exige idade mínima de 60 anos.',
                ]);
            }
        }

        if ($profile === VehicleUsageProfileEnum::RecemNascido) {
            $babyBirthDate = $payload['usage_profile_baby_date_of_birth'] ?? $vehicle?->usage_profile_baby_date_of_birth;

            if (!$babyBirthDate) {
                throw ValidationException::withMessages([
                    'usage_profile_baby_date_of_birth' => 'A data de nascimento da criança é obrigatória para o perfil "recém-nascido".',
                ]);
            }

            if (now()->diffInYears($babyBirthDate, true) >= 2) {
                throw ValidationException::withMessages([
                    'usage_profile_baby_date_of_birth' => 'O perfil "recém-nascido" exige criança com até 2 anos de idade.',
                ]);
            }
        }
    }
}
