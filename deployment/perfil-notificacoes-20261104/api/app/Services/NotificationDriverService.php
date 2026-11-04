<?php

namespace App\Services;

use App\Enums\UserTypeEnum;
use App\Jobs\SendNotificationDriverMailJob;
use App\Models\NotificationDriver;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NotificationDriverService
{
    public function getForUser(User $authenticatedUser): Collection
    {
        $this->ensureAllowedRole($authenticatedUser);

        return NotificationDriver::query()
            ->where('user_id', $authenticatedUser->id)
            ->orderByDesc('id')
            ->get();
    }

    public function getReceivedForUser(User $authenticatedUser): Collection
    {
        $this->ensureAllowedRole($authenticatedUser);

        return NotificationDriver::query()
            ->where('driver_id', $authenticatedUser->id)
            ->orderByDesc('id')
            ->get();
    }

    public function find(int $id, User $authenticatedUser): NotificationDriver
    {
        $notification = NotificationDriver::query()->findOrFail($id);

        $this->ensureOwnershipOrSuperAdmin($notification, $authenticatedUser);

        return $notification;
    }

    public function getUnreadTotalForUser(User $authenticatedUser): int
    {
        $this->ensureAllowedRole($authenticatedUser);

        return NotificationDriver::query()
            ->where('read', 0)
            ->where('driver_id', $authenticatedUser->id)
            ->count();
    }

    public function store(array $data, User $authenticatedUser): NotificationDriver
    {
        $this->ensureAllowedRole($authenticatedUser);

        $plate=strtoupper((string)preg_replace('/[^A-Za-z0-9]/','',(string)($data['plate']??'')));
        if (!preg_match('/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/',$plate)) {
            throw ValidationException::withMessages(['plate'=>'Informe uma placa válida no padrão AAA0000 ou AAA0A00.']);
        }
        $userId=$this->isClient($authenticatedUser) ? $authenticatedUser->id : (int)($data['user_id']??$authenticatedUser->id);
        return DB::transaction(function () use ($data,$plate,$userId) {
            $vehicles=Vehicle::query()->with('user')->whereRaw("REPLACE(REPLACE(UPPER(plate), '-', ''), ' ', '') = ?",[$plate])->lockForUpdate()->limit(2)->get();
            if ($vehicles->count()!==1 || !$vehicles->first()->user || !filter_var($vehicles->first()->user->email,FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages(['plate'=>'Placa não cadastrada ou sem usuário disponível para receber a notificação.']);
            }
            $notification=NotificationDriver::query()->create([
                'user_id'=>$userId,
                'driver_id'=>$vehicles->first()->user->id,
                'plate'=>$plate,
                'type'=>(int)$data['type'],
                'read'=>0,
            ]);
            SendNotificationDriverMailJob::dispatch($notification->id)->afterCommit();
            return $notification;
        });
    }

    public function update(int $id, array $data, User $authenticatedUser): NotificationDriver
    {
        $notification = NotificationDriver::query()->findOrFail($id);

        $this->ensureOwnershipOrSuperAdmin($notification, $authenticatedUser);

        if ($this->isClient($authenticatedUser)) {
            $allowedForClient = ['read'];
            $incomingKeys = array_keys($data);
            $invalidKeys = array_diff($incomingKeys, $allowedForClient);

            if (! empty($invalidKeys)) {
                throw new AuthorizationException('O cliente final pode atualizar apenas o campo read.');
            }

            if (array_key_exists('read', $data)) {
                $notification->read = (int) $data['read'];
                $notification->save();
            }

            return $notification->fresh();
        }

        $fields = [];

        if (array_key_exists('user_id', $data)) {
            $fields['user_id'] = $data['user_id'];
        }

        if (array_key_exists('driver_id', $data)) {
            $fields['driver_id'] = $data['driver_id'];
        }

        if (array_key_exists('plate', $data)) {
            $fields['plate'] = $data['plate'];
        }

        if (array_key_exists('type', $data)) {
            $fields['type'] = (int) $data['type'];
        }

        if (array_key_exists('read', $data)) {
            $fields['read'] = (int) $data['read'];
        }

        if (! empty($fields)) {
            $notification->update($fields);
        }

        return $notification->fresh();
    }

    public function destroy(int $id, User $authenticatedUser): void
    {
        $notification = NotificationDriver::query()->findOrFail($id);

        $this->ensureOwnershipOrSuperAdmin($notification, $authenticatedUser);

        $notification->delete();
    }

    private function ensureOwnershipOrSuperAdmin(NotificationDriver $notification, User $authenticatedUser): void
    {
        $this->ensureAllowedRole($authenticatedUser);

        if ($this->isSuperAdmin($authenticatedUser)) {
            return;
        }

        if (
            $notification->user_id !== $authenticatedUser->id
            && $notification->driver_id !== $authenticatedUser->id
        ) {
            throw new AuthorizationException('Você não tem permissão para acessar esta notificação.');
        }
    }

    private function ensureAllowedRole(User $authenticatedUser): void
    {
        if (! $this->isSuperAdmin($authenticatedUser) && ! $this->isClient($authenticatedUser)) {
            throw new AuthorizationException('Apenas superAdmin e cliente final podem acessar notificações de motorista.');
        }
    }

    private function isSuperAdmin(User $authenticatedUser): bool
    {
        return $authenticatedUser->type === UserTypeEnum::SuperAdmin->value;
    }

    private function isClient(User $authenticatedUser): bool
    {
        return $authenticatedUser->type === UserTypeEnum::Client->value;
    }

}
