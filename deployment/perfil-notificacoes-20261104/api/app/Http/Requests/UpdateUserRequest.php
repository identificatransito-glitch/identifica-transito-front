<?php

namespace App\Http\Requests;

use App\Enums\UserTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('cpf')) $this->merge(['cpf'=>preg_replace('/[^0-9]/','',(string)$this->input('cpf'))]);
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'cpf' => [
                'sometimes',
                'required',
                'string',
                'regex:/^[0-9]{11}$/',
                Rule::unique('users', 'cpf')->ignore($userId),
                function ($attribute,$value,$fail) use ($userId) {
                    if (\Illuminate\Support\Facades\DB::table('users')->where('id','!=',$userId)->whereRaw("REPLACE(REPLACE(cpf, '.', ''), '-', '') = ?",[$value])->exists()) $fail('Este CPF já está cadastrado em outra conta.');
                },
            ],
            'birth_date' => ['sometimes', 'required', 'date'],
            'phone' => ['sometimes', 'required', 'string', 'max:20'],
            'gender' => ['sometimes', 'required', 'string', 'in:M,F'],
            'type' => ['sometimes', 'integer', 'in:'.implode(',', UserTypeEnum::values())],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'photo' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,gif', 'max:2048'],
            'pdv_id' => ['sometimes', 'nullable', 'integer', 'exists:pdvs,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'cpf.regex' => 'Informe um CPF com 11 dígitos.',
            'cpf.unique' => 'Este CPF já está cadastrado em outra conta.',
            'gender.in' => 'Gênero inválido. Use M ou F.',
        ];
    }

}
