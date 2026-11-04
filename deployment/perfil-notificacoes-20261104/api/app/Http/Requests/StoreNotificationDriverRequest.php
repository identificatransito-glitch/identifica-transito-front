<?php

namespace App\Http\Requests;

use App\Enums\NotificationDriverEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['plate'=>strtoupper((string)preg_replace('/[^A-Za-z0-9]/','',(string)$this->input('plate')))]);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'driver_id' => ['nullable', 'integer', 'exists:users,id'],
            'plate' => ['required', 'string', 'regex:/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/'],
            'type' => ['required', 'integer', Rule::in(NotificationDriverEnum::values())],
            'read' => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.integer' => 'O usuário deve ser um identificador válido.',
            'user_id.exists' => 'O usuário informado não existe.',
            'driver_id.integer' => 'O motorista deve ser um identificador válido.',
            'driver_id.exists' => 'O motorista informado não existe.',
            'plate.required' => 'A placa é obrigatória.',
            'plate.string' => 'A placa deve ser um texto.',
            'plate.regex' => 'Informe uma placa válida no padrão AAA0000 ou AAA0A00.',
            'type.required' => 'O tipo é obrigatório.',
            'type.integer' => 'O tipo deve ser um número inteiro.',
            'type.in' => 'O tipo informado é inválido.',
            'read.integer' => 'O campo read deve ser 0 ou 1.',
            'read.in' => 'O campo read deve ser 0 ou 1.',
        ];
    }
}
