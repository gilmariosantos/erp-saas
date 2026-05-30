<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegistrarTenantRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'razao_social'     => ['required', 'string', 'max:150'],
            'cnpj'             => ['nullable', 'string', 'max:18'],
            'nome_responsavel' => ['required', 'string', 'max:150'],
            'email'            => ['required', 'email', 'max:180'],
            'senha'            => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'subdominio'       => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9-]+$/'],
            'plano_slug'       => ['nullable', 'in:basico,pro,enterprise'],
        ];
    }

    public function messages(): array
    {
        return [
            'subdominio.regex' => 'O subdomínio só pode conter letras minúsculas, números e hífen.',
            'senha.confirmed'  => 'A confirmação de senha não corresponde.',
        ];
    }
}
