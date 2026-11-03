<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePdvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $pdvId = $this->route('pdv')?->id;

        return [
            'commission_percentage' => ['nullable', 'numeric', 'between:0,100', 'decimal:0,2'],
            'name'        => ['sometimes', 'required', 'string', 'max:255'],
            'cnpj'        => ['nullable', 'string', 'max:18', Rule::unique('points_of_sale', 'cnpj')->ignore($pdvId)],
            'address'     => ['nullable', 'string'],
            'phone'       => ['nullable', 'string', 'max:20'],
            'responsible' => ['nullable', 'string', 'max:255'],
            'map_link'    => ['nullable', 'string'],
            'active'      => ['sometimes', 'boolean'],
        ];
    }
}
