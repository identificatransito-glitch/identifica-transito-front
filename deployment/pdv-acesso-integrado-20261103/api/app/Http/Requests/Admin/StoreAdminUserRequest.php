<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'               => ['required', 'string', 'max:255'],
            'email'              => ['required', 'email', 'unique:users,email'],
            'password'           => ['required', 'string', 'min:8'],
            'cpf'                => ['nullable', 'string', 'max:20'],
            'birth_date'         => ['nullable', 'date'],
            'phone'              => ['nullable', 'string', 'max:30'],
            'gender'             => ['required', 'string', 'in:M,F'],
            'type'               => ['required', 'integer', 'in:3'],
            'pdv_id'             => ['exclude'],
            'user_id_admin_pdv'  => ['exclude'],
            // Personal commission fields from cached clients are deliberately excluded.
            'pdv_commission_type' => ['exclude'],
            'pdv_commission_value' => ['exclude'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'O nome é obrigatório.',
            'email.required'         => 'O e-mail é obrigatório.',
            'email.email'            => 'Informe um e-mail válido.',
            'email.unique'           => 'Este e-mail já está cadastrado.',
            'password.required'      => 'A senha é obrigatória.',
            'password.min'           => 'A senha deve ter no mínimo 8 caracteres.',
            'type.required'          => 'O tipo de usuário é obrigatório.',
            'type.in'                => 'O cadastro de usuários é exclusivo para clientes. Cadastre administradores em Pontos de Venda.',
            'pdv_id.exists'          => 'O PDV informado não foi encontrado.',
            'user_id_admin_pdv.exists' => 'O usuário admin PDV informado não foi encontrado.',
        ];
    }

}
