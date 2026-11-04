<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PdvResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'cnpj'                  => $this->cnpj,
            'address'               => $this->address,
            'phone'                 => $this->phone,
            'responsible'           => $this->responsible,
            'map_link'              => $this->map_link,
            'commission_percentage' => $this->when($request->user() && ((int) $request->user()->type === 1 || ((int) $request->user()->type === 2 && (int) $request->user()->pdv_id === (int) $this->id)), $this->commission_percentage),
            'access' => $this->when((int) $request->user()?->type === 1, fn () => [
                'configured' => $this->accessUser !== null,
                'email' => $this->accessUser?->email,
            ]),
            'active'                 => (bool) $this->active,
            'created_at'            => $this->created_at?->format('d/m/Y H:i:s'),
            'updated_at'            => $this->updated_at?->format('d/m/Y H:i:s'),
            'deleted_at'            => $this->deleted_at?->format('d/m/Y H:i:s'),
        ];
    }
}
