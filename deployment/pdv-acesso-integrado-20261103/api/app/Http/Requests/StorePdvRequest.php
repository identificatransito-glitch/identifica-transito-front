<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StorePdvRequest extends FormRequest {
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void {
        $this->merge(['access_email'=>strtolower(trim((string)$this->input('access_email')))]);
    }
    public function rules(): array { return [
        'name'=>['required','string','max:255'],
        'responsible'=>['required','string','max:255'],
        'cnpj'=>['nullable','string','max:18','unique:points_of_sale,cnpj'],
        'address'=>['nullable','string'],'phone'=>['nullable','string','max:20'],
        'map_link'=>['nullable','string'],'active'=>['nullable','boolean'],
        'commission_percentage'=>['nullable','numeric','between:0,100','decimal:0,2'],
        'access_email'=>['required','email','max:255','unique:users,email'],
        'password'=>['required','string','min:8','max:128','regex:/\S/','confirmed'],
    ]; }
    public function messages(): array { return [
        'access_email.required'=>'Informe o e-mail de acesso do PDV.',
        'access_email.unique'=>'Este e-mail já está em uso. Informe um e-mail exclusivo para este PDV.',
        'responsible.required'=>'Informe o nome do responsável pelo PDV.',
        'password.required'=>'Defina a senha inicial de acesso do PDV.',
        'password.confirmed'=>'A confirmação da senha deve ser igual à senha.',
    ]; }
}
