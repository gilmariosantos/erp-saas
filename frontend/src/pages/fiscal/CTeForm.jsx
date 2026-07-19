import { useForm } from 'react-hook-form'
import { useMutation, useQueryClient, useQuery } from '@tanstack/react-query'
import toast from 'react-hot-toast'
import { fiscalApi, pessoasApi } from '@/api'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'
import { FormField, Input, Select, Textarea } from '@/components/ui/FormField'

/**
 * Formulário de criação de CT-e (rascunho) — modal rodoviário.
 * Cobre os tipos de CT-e (normal/complemento/anulação/substituição),
 * tomador do serviço e dados da carga, alinhado ao CTeXmlBuilder.
 */
export function CTeForm({ open, onClose }) {
  const qc = useQueryClient()

  const { register, handleSubmit, reset, watch, formState: { errors } } = useForm({
    defaultValues: {
      tipo_ct: '0',
      tipo_servico: '0',
      modal: '01',
      tomador: '3',
      cfop: '5353',
      natureza_operacao: 'PRESTACAO DE SERVICO DE TRANSPORTE',
      data_emissao: new Date().toISOString().slice(0, 10),
      cst_icms: '00',
      carga_unidade_medida: '01',
    },
  })

  const { data: pessoas } = useQuery({
    queryKey: ['pessoas-cte'],
    queryFn: async () => (await pessoasApi.list()).data,
    enabled: open,
  })

  const criar = useMutation({
    mutationFn: (dados) => fiscalApi.ctes.create(dados),
    onSuccess: () => {
      toast.success('CT-e criado em rascunho! Use "emitir" para enviar à SEFAZ.')
      qc.invalidateQueries({ queryKey: ['ctes'] })
      reset()
      onClose()
    },
    onError: (err) => {
      const erros = err.response?.data?.errors
      toast.error(erros ? Object.values(erros)[0][0] : (err.response?.data?.message || 'Erro ao criar CT-e.'))
    },
  })

  const tipoCt = watch('tipo_ct')
  const tomador = watch('tomador')
  const precisaChaveRef = ['1', '3'].includes(tipoCt) // complemento ou substituição

  return (
    <Modal open={open} onClose={onClose} title="Novo CT-e" size="xl">
      <form onSubmit={handleSubmit((d) => criar.mutate(d))} className="space-y-4">
        {/* Tipo e serviço */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <FormField label="Tipo de CT-e" required>
            <Select {...register('tipo_ct')}>
              <option value="0">Normal</option>
              <option value="1">Complemento de valores</option>
              <option value="2">Anulação</option>
              <option value="3">Substituição</option>
            </Select>
          </FormField>
          <FormField label="Tipo de serviço" required>
            <Select {...register('tipo_servico')}>
              <option value="0">Normal</option>
              <option value="1">Subcontratação</option>
              <option value="2">Redespacho</option>
              <option value="3">Redespacho intermediário</option>
              <option value="4">Vinculado a multimodal</option>
            </Select>
          </FormField>
          <FormField label="Tomador do serviço" required>
            <Select {...register('tomador')}>
              <option value="0">Remetente</option>
              <option value="1">Expedidor</option>
              <option value="2">Recebedor</option>
              <option value="3">Destinatário</option>
              <option value="4">Outros</option>
            </Select>
          </FormField>
        </div>

        {precisaChaveRef && (
          <FormField label="Chave do CT-e referenciado (44 dígitos)" required error={errors.cte_referenciado_chave?.message}>
            <Input {...register('cte_referenciado_chave', { required: precisaChaveRef, minLength: 44, maxLength: 44 })}
              placeholder="Chave do CT-e original que está sendo complementado/substituído" error={errors.cte_referenciado_chave} />
          </FormField>
        )}

        {/* Rota */}
        <div className="border-t border-slate-200 dark:border-slate-700 pt-4">
          <h3 className="font-medium mb-3 text-sm">Rota do transporte</h3>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <FormField label="Município início" required>
              <Input {...register('municipio_inicio', { required: true })} />
            </FormField>
            <FormField label="UF início" required>
              <Input {...register('uf_inicio', { required: true })} maxLength={2} placeholder="SP" />
            </FormField>
            <FormField label="Município fim" required>
              <Input {...register('municipio_fim', { required: true })} />
            </FormField>
            <FormField label="UF fim" required>
              <Input {...register('uf_fim', { required: true })} maxLength={2} placeholder="RJ" />
            </FormField>
          </div>
        </div>

        {/* Remetente e destinatário */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormField label="Remetente">
            <Select {...register('remetente_id')}>
              <option value="">Selecione...</option>
              {(pessoas?.data || []).map((p) => <option key={p.id} value={p.id}>{p.nome}</option>)}
            </Select>
          </FormField>
          <FormField label="Destinatário">
            <Select {...register('destinatario_id')}>
              <option value="">Selecione...</option>
              {(pessoas?.data || []).map((p) => <option key={p.id} value={p.id}>{p.nome}</option>)}
            </Select>
          </FormField>
        </div>

        {tomador === '4' && (
          <FormField label="Tomador (quando 'Outros')">
            <Select {...register('tomador_id')}>
              <option value="">Selecione...</option>
              {(pessoas?.data || []).map((p) => <option key={p.id} value={p.id}>{p.nome}</option>)}
            </Select>
          </FormField>
        )}

        {/* Valores e carga */}
        <div className="border-t border-slate-200 dark:border-slate-700 pt-4">
          <h3 className="font-medium mb-3 text-sm">Valores e carga</h3>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <FormField label="Valor do serviço" required error={errors.valor_total_servico?.message}>
              <Input type="number" step="0.01" {...register('valor_total_servico', { required: true })} error={errors.valor_total_servico} />
            </FormField>
            <FormField label="Valor da carga">
              <Input type="number" step="0.01" {...register('valor_carga')} />
            </FormField>
            <FormField label="Produto predominante">
              <Input {...register('produto_predominante')} placeholder="Ex: Eletrônicos" />
            </FormField>
            <FormField label="Peso (kg)">
              <Input type="number" step="0.001" {...register('valor_total_mercadoria')} />
            </FormField>
          </div>
        </div>

        {/* ICMS e RNTRC */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <FormField label="CST ICMS">
            <Select {...register('cst_icms')}>
              <option value="00">00 - Tributação normal</option>
              <option value="20">20 - BC reduzida</option>
              <option value="40">40 - Isenta</option>
              <option value="41">41 - Não tributada</option>
              <option value="51">51 - Diferimento</option>
              <option value="90">90 - Simples Nacional</option>
            </Select>
          </FormField>
          <FormField label="Alíquota ICMS %">
            <Input type="number" step="0.01" {...register('aliquota_icms')} />
          </FormField>
          <FormField label="RNTRC">
            <Input {...register('rntrc')} placeholder="Registro ANTT" />
          </FormField>
          <FormField label="CFOP">
            <Input {...register('cfop')} />
          </FormField>
        </div>

        <FormField label="Informações complementares">
          <Textarea {...register('informacoes_complementares')} />
        </FormField>

        <div className="flex items-start gap-2 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
          <p className="text-xs text-blue-800 dark:text-blue-300">
            O CT-e será criado em <strong>rascunho</strong> (modal rodoviário).
            A emissão para a SEFAZ é feita pela ação "emitir" na listagem.
          </p>
        </div>

        <div className="flex justify-end gap-2 pt-2">
          <Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button>
          <Button type="submit" disabled={criar.isPending}>
            {criar.isPending ? 'Criando...' : 'Criar rascunho'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
