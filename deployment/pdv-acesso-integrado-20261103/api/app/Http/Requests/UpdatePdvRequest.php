<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Pdv;
class UpdatePdvRequest extends FormRequest {
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void {
        $this->merge(['access_email'=>strtolower(trim((string)$this->input('access_email')))]);
    }
    public function rules(): array {
        $point=$this->route('pdv');
        $id=$point instanceof Pdv ? $point->id : null;
        $access=$point instanceof Pdv ? $point->accessUser : null;
        return [
            'name'=>['sometimes','required','string','max:255'],
            'responsible'=>['required','string','max:255'],
            'cnpj'=>['nullable','string','max:18',Rule::unique('points_of_sale','cnpj')->ignore($id)],
            'address'=>['nullable','string'],'phone'=>['nullable','string','max:20'],
            'map_link'=>['nullable','string'],'active'=>['sometimes','boolean'],
            'commission_percentage'=>['nullable','numeric','between:0,100','decimal:0,2'],
            'access_email'=>['required','email','max:255',Rule::unique('users','email')->ignore($access?->id)],
            'password'=>[$access ? 'nullable' : 'required','string','min:8','max:128','regex:/\S/','confirmed'],
        ];
    }
    public function messages(): array { return [
        'access_email.required'=>'Informe o e-mail de acesso do PDV.',
        'access_email.unique'=>'Este e-mail já pertence a outra conta. Os acessos não serão transferidos entre PDVs.',
        'responsible.required'=>'Informe o nome do responsável pelo PDV.',
        'password.required'=>'Este PDV ainda não tem acesso. Defina a senha inicial.',
        'password.confirmed'=>'A confirmação da senha deve ser igual à senha.',
    ]; }
}
