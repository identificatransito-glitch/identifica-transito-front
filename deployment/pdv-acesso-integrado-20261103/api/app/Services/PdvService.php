<?php

namespace App\Services;

use App\Enums\UserTypeEnum;
use App\Models\Pdv;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\{DB,Hash,Schema};
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use App\Jobs\CreateDefaultUserSettingsJob;

class PdvService
{
    public function paginate(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Pdv::query()->with('accessUser')->orderByDesc('id');

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('cnpj', 'like', '%' . $search . '%')
                    ->orWhere('address', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%')
                    ->orWhere('responsible', 'like', '%' . $search . '%');
            });
        }

        return $query->paginate($perPage);
    }

    private const POINT_FIELDS = ['name','cnpj','address','phone','responsible','map_link','active','commission_percentage'];

    public function create(array $payload, User $user): Pdv
    {
        $this->ensureSuperAdmin($user, 'Somente o Super Admin pode cadastrar um PDV.');
        $this->validateAccess($payload, null);
        return DB::transaction(function () use ($payload) {
            $point=Pdv::query()->create(Arr::only($payload,self::POINT_FIELDS));
            $account=$this->createAccount($point,$payload);
            $point->forceFill(['access_user_id'=>$account->id])->save();
            return $point->fresh('accessUser');
        });
    }

    public function update(Pdv $pdv, array $payload, User $user): Pdv
    {
        $this->ensureSuperAdmin($user, 'Somente o Super Admin pode editar um PDV.');
        return DB::transaction(function () use ($pdv,$payload) {
            $point=Pdv::query()->whereKey($pdv->id)->lockForUpdate()->firstOrFail();
            $account=$point->accessUser;
            if ($account && ((int)$account->type!==2 || (int)$account->pdv_id!==(int)$point->id)) {
                throw ValidationException::withMessages(['access_email'=>'O vínculo do administrador deste PDV precisa ser conferido.']);
            }
            // Older ambiguous links are not silently reassigned.
            if (!$account && $point->users()->where('type',2)->exists()) {
                throw ValidationException::withMessages(['access_email'=>'Este PDV já tem administradores vinculados. Confira o acesso principal antes de alterar.']);
            }
            $this->validateAccess($payload,$account);
            $point->update(Arr::only($payload,self::POINT_FIELDS));
            if (!$account) {
                $account=$this->createAccount($point,$payload);
                $point->forceFill(['access_user_id'=>$account->id])->save();
            } else {
                $changed=$account->email!==$payload['access_email'] || !empty($payload['password']);
                $oldEmail=$account->email;
                $account->name=$point->responsible;
                $account->email=$payload['access_email'];
                if (!empty($payload['password'])) $account->password=Hash::make($payload['password']);
                $account->save();
                if ($changed) {
                    $account->tokens()->delete();
                    if (Schema::hasTable('sessions')) DB::table('sessions')->where('user_id',$account->id)->delete();
                    if (Schema::hasTable('password_reset_tokens')) DB::table('password_reset_tokens')->whereIn('email',[$oldEmail,$account->email])->delete();
                }
            }
            return $point->fresh('accessUser');
        });
    }

    private function validateAccess(array &$payload, ?User $account): void
    {
        $payload['access_email']=strtolower(trim((string)($payload['access_email']??'')));
        $rules=[
            'responsible'=>['required','string','max:255'],
            'access_email'=>['required','email','max:255',\Illuminate\Validation\Rule::unique('users','email')->ignore($account?->id)],
            'password'=>[$account ? 'nullable' : 'required','string','min:8','max:128','regex:/\S/'],
        ];
        validator($payload,$rules)->validate();
    }

    private function createAccount(Pdv $point, array $payload): User
    {
        $account=User::query()->create([
            'name'=>$point->responsible, 'email'=>$payload['access_email'],
            'password'=>Hash::make($payload['password']), 'type'=>2, 'pdv_id'=>$point->id,
        ]);
        CreateDefaultUserSettingsJob::dispatch($account->id)->afterCommit();
        return $account;
    }

    public function delete(Pdv $pdv, User $user): void
    {
        $this->ensureSuperAdmin($user, 'Você não tem permissão para remover pontos de venda.');

        $pdv->delete();
    }

    private function ensureSuperAdmin(User $user, string $message): void
    {
        if ($user->type !== UserTypeEnum::SuperAdmin->value) {
            throw new AuthorizationException($message);
        }
    }
}
