<?php
namespace App\Http\Resources\Fiscal;
use Illuminate\Http\Resources\Json\JsonResource;

class CTeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'numero'               => $this->numero,
            'serie'                => $this->serie,
            'chave_acesso'         => $this->chave_acesso,
            'status'               => $this->status->value,
            'status_label'         => $this->status->label(),
            'data_emissao'         => $this->data_emissao?->format('d/m/Y H:i'),
            'data_autorizacao'     => $this->data_autorizacao?->format('d/m/Y H:i'),
            'valor_total_servico'  => $this->valor_total_servico,
            'municipio_inicio'     => $this->municipio_inicio,
            'uf_inicio'            => $this->uf_inicio,
            'municipio_fim'        => $this->municipio_fim,
            'uf_fim'               => $this->uf_fim,
            'ciot'                 => $this->ciot,
            'protocolo_autorizacao'=> $this->protocolo_autorizacao,
            'motivo_rejeicao'      => $this->motivo_rejeicao,
            'tem_ciot'             => $this->temCiot(),
            'tem_xml'              => (bool) $this->path_xml,
            'tem_pdf'              => (bool) $this->path_pdf,
        ];
    }
}
