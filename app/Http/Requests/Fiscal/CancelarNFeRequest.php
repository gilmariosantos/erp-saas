<?php
namespace App\Http\Requests\Fiscal;
use Illuminate\Foundation\Http\FormRequest;

class CancelarNFeRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'justificativa' => ['required', 'string', 'min:15', 'max:255'],
        ];
    }
    public function messages(): array
    {
        return ['justificativa.min' => 'A justificativa deve ter no mínimo 15 caracteres.'];
    }
}
