<?php
namespace App\Http\Requests\Fiscal;
use Illuminate\Foundation\Http\FormRequest;

class CartaCorrecaoRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['descricao' => ['required', 'string', 'min:15', 'max:1000']];
    }
}
