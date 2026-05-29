<?php
namespace App\Http\Requests\Fiscal;
use Illuminate\Foundation\Http\FormRequest;

class NFeRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'empresa_id'              => ['required', 'exists:empresas,id'],
            'modelo'                  => ['sometimes', 'in:55,65'],
            'serie'                   => ['sometimes', 'string', 'max:3'],
            'natureza_operacao'       => ['required', 'string', 'max:60'],
            'tipo_emissao'            => ['sometimes', 'in:1,6,7'],
            'finalidade'              => ['sometimes', 'in:1,2,3,4'],
            'operacao'                => ['sometimes', 'in:0,1'],
            'data_emissao'            => ['required', 'date'],
            'destinatario_id'         => ['nullable', 'exists:pessoas,id'],
            'destinatario_cnpj_cpf'   => ['required', 'string'],
            'destinatario_nome'       => ['required', 'string', 'max:150'],
            'destinatario_uf'         => ['required', 'string', 'size:2'],
            'destinatario_indicador_ie'=> ['required', 'in:1,2,9'],
            'modalidade_frete'        => ['required', 'in:0,1,2,3,4,9'],
            'informacoes_complementares' => ['nullable', 'string'],
        ];
    }
}
