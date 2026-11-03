<?php

namespace App\Http\Resources;

use App\Enums\UserTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'cpf' => $this->cpf,
            'birth_date' => $this->birth_date?->format('d/m/Y'),
            'phone' => $this->phone,
            'gender' => $this->gender,
            'type' => $this->type,
            'type_label' => UserTypeEnum::tryFrom((int) $this->type)?->label(),
            'photo' => $this->photo ? asset('storage/' . $this->photo) : null,
            'pdv_id' => $this->pdv_id,
            'user_id_admin_pdv' => $this->user_id_admin_pdv,
            'vehicles_count'      => $this->whenNotNull($this->vehicles_count),
            'tag_purchases_count' => $this->whenNotNull($this->tag_purchases_count),
            'created_at' => $this->created_at?->format('d/m/Y H:i:s'),
            'updated_at' => $this->updated_at?->format('d/m/Y H:i:s'),
        ];
    }
}
