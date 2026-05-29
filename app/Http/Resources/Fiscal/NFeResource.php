<?php
namespace App\Http\Resources\Fiscal;
use Illuminate\Http\Resources\Json\JsonResource;

class NFeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                     => $this->id,
            'numero'                 => $this->numero,
            'serie'                  => $this->serie,
            'modelo'                 => $this->modelo,
            'chave_acesso'           => $this->chave_acesso,
            'status'                 => $this->status->value,
            'status_label'           => $this->status->label(),
            'status_cor'             => $this->status->cor(),
            'data_emissao'           => $this->data_emissao?->format('d/m/Y H:i'),
            'data_autorizacao'       => $this->data_autorizacao?->format('d/m/Y H:i'),
            'total_nota'             => $this->total_nota,
            'natureza_operacao'      => $this->natureza_operacao,
            'destinatario_nome'      => $this->destinatario_nome,
            'destinatario_cnpj_cpf'  => $this->destinatario_cnpj_cpf,
            'protocolo_autorizacao'  => $this->protocolo_autorizacao,
            'motivo_rejeicao'        => $this->motivo_rejeicao,
            'pode_emitir'            => $this->podeEmitir(),
            'pode_cancelar'          => $this->podeCancelar(),
            'tem_xml'                => (bool) $this->path_xml,
            'tem_pdf'                => (bool) $this->path_pdf,
            'empresa'                => $this->whenLoaded('empresa', fn () => [
                'id' => $this->empresa->id, 'razao_social' => $this->empresa->razao_social,
            ]),
            'itens'                  => $this->whenLoaded('itens'),
            'created_at'             => $this->created_at?->format('d/m/Y H:i'),
        ];
    }
}
