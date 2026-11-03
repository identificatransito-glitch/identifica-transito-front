<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePdvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'commission_percentage' => ['nullable', 'numeric', 'between:0,100', 'decimal:0,2'],
            'name'       => ['required', 'string', 'max:255'],
            'cnpj'       => ['nullable', 'string', 'max:18', 'unique:points_of_sale,cnpj'],
            'address'    => ['nullable', 'string'],
            'phone'      => ['nullable', 'string', 'max:20'],
            'responsible'=> ['nullable', 'string', 'max:255'],
            'map_link'   => ['nullable', 'string'],
            'active'     => ['nullable', 'boolean'],
        ];
    }
}
