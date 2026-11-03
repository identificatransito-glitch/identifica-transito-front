<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'name'               => ['sometimes', 'string', 'max:255'],
            'email'              => ['sometimes', 'email', "unique:users,email,{$userId}"],
            'password'           => ['sometimes', 'string', 'min:8'],
            'cpf'                => ['nullable', 'string', 'max:20'],
            'birth_date'         => ['nullable', 'date'],
            'phone'              => ['nullable', 'string', 'max:30'],
            'gender'             => ['sometimes', 'required', 'string', 'in:M,F'],
            'type'               => ['sometimes', 'integer', 'in:2,3'],
            'pdv_id'             => ['nullable', 'integer', 'exists:points_of_sale,id'],
            'user_id_admin_pdv'  => ['nullable', 'integer', 'exists:users,id'],
            // Personal commission fields from cached clients are deliberately excluded.
            'pdv_commission_type' => ['exclude'],
            'pdv_commission_value' => ['exclude'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email'            => 'Informe um e-mail válido.',
            'email.unique'           => 'Este e-mail já está em uso.',
            'password.min'           => 'A senha deve ter no mínimo 8 caracteres.',
            'type.in'                => 'Tipo inválido. Use 2 (Admin) ou 3 (Client).',
            'pdv_id.exists'          => 'O PDV informado não foi encontrado.',
            'user_id_admin_pdv.exists' => 'O usuário admin PDV informado não foi encontrado.',
        ];
    }

}
