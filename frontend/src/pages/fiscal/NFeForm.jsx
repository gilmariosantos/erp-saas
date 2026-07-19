import { useForm } from 'react-hook-form'
import { useMutation, useQueryClient, useQuery } from '@tanstack/react-query'
import toast from 'react-hot-toast'
import { fiscalApi, pessoasApi } from '@/api'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'
import { FormField, Input, Select, Textarea } from '@/components/ui/FormField'

/**
 * Formulário de criação de NF-e (rascunho).
 * Alinhado aos campos validados pelo NFeRequest no backend.
 * A emissão para a SEFAZ é disparada depois, pela ação "emitir" na listagem.
 */
export function NFeForm({ open, onClose }) {
  const qc = useQueryClient()

  const { register, handleSubmit, reset, watch, setValue, formState: { errors } } = useForm({
    defaultValues: {
      modelo: '55',
      serie: '1',
      natureza_operacao: 'VENDA DE MERCADORIA',
      tipo_emissao: '1',
      finalidade: '1',
      operacao: '1',
      data_emissao: new Date().toISOString().slice(0, 10),
      destinatario_indicador_ie: '9',
      modalidade_frete: '9',
    },
  })

  // Carrega clientes para o select de destinatário
  const { data: clientes } = useQuery({
    queryKey: ['pessoas-dest-nfe'],
    queryFn: async () => (await pessoasApi.list({ cliente: true })).data,
    enabled: open,
  })

  // Ao escolher um cliente, preenche os campos do destinatário
  const preencherDestinatario = (id) => {
    const cliente = clientes?.data?.find((c) => c.id === Number(id))
    if (!cliente) return
    setValue('destinatario_id', cliente.id)
    setValue('destinatario_cnpj_cpf', cliente.cnpj || cliente.cpf || '')
    setValue('destinatario_nome', cliente.nome)
    setValue('destinatario_uf', cliente.uf || '')
    setValue('destinatario_indicador_ie', cliente.ie ? '1' : '9')
  }

  const criar = useMutation({
    mutationFn: (dados) => fiscalApi.nfes.create(dados),
    onSuccess: () => {
      toast.success('NF-e criada em rascunho! Use "emitir" para enviar à SEFAZ.')
      qc.invalidateQueries({ queryKey: ['nfes'] })
      reset()
      onClose()
    },
    onError: (err) => {
      const erros = err.response?.data?.errors
      if (erros) {
        toast.error(Object.values(erros)[0][0])
      } else {
        toast.error(err.response?.data?.message || 'Erro ao criar NF-e.')
      }
    },
  })

  const modelo = watch('modelo')

  return (
    <Modal open={open} onClose={onClose} title="Nova NF-e" size="xl">
      <form onSubmit={handleSubmit((d) => criar.mutate(d))} className="space-y-4">
        {/* Dados gerais */}
        <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
          <FormField label="Modelo" required>
            <Select {...register('modelo')}>
              <option value="55">55 - NF-e</option>
              <option value="65">65 - NFC-e</option>
            </Select>
          </FormField>
          <FormField label="Série">
            <Input {...register('serie')} maxLength={3} />
          </FormField>
          <FormField label="Finalidade">
            <Select {...register('finalidade')}>
              <option value="1">Normal</option>
              <option value="2">Complementar</option>
              <option value="3">Ajuste</option>
              <option value="4">Devolução</option>
            </Select>
          </FormField>
          <FormField label="Operação">
            <Select {...register('operacao')}>
              <option value="1">Saída</option>
              <option value="0">Entrada</option>
            </Select>
          </FormField>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormField label="Natureza da operação" required error={errors.natureza_operacao?.message}>
            <Input {...register('natureza_operacao', { required: 'Campo obrigatório' })} error={errors.natureza_operacao} />
          </FormField>
          <FormField label="Data de emissão" required>
            <Input type="date" {...register('data_emissao', { required: true })} />
          </FormField>
        </div>

        {/* Destinatário */}
        <div className="border-t border-slate-200 dark:border-slate-700 pt-4">
          <h3 className="font-medium mb-3 text-sm">Destinatário</h3>

          {modelo === '55' && (
            <FormField label="Selecionar cliente cadastrado" className="mb-3">
              <Select onChange={(e) => preencherDestinatario(e.target.value)}>
                <option value="">Selecione ou preencha manualmente...</option>
                {(clientes?.data || []).map((c) => <option key={c.id} value={c.id}>{c.nome}</option>)}
              </Select>
            </FormField>
          )}

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <FormField label="CNPJ/CPF" required error={errors.destinatario_cnpj_cpf?.message}>
              <Input {...register('destinatario_cnpj_cpf', { required: 'Campo obrigatório' })} error={errors.destinatario_cnpj_cpf} />
            </FormField>
            <FormField label="Nome/Razão social" required error={errors.destinatario_nome?.message}>
              <Input {...register('destinatario_nome', { required: 'Campo obrigatório' })} error={errors.destinatario_nome} />
            </FormField>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
            <FormField label="UF" required error={errors.destinatario_uf?.message}>
              <Input {...register('destinatario_uf', { required: true })} maxLength={2} placeholder="SP" error={errors.destinatario_uf} />
            </FormField>
            <FormField label="Indicador de IE" required>
              <Select {...register('destinatario_indicador_ie')}>
                <option value="1">1 - Contribuinte ICMS</option>
                <option value="2">2 - Isento</option>
                <option value="9">9 - Não contribuinte</option>
              </Select>
            </FormField>
          </div>
        </div>

        {/* Frete */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormField label="Modalidade do frete" required>
            <Select {...register('modalidade_frete')}>
              <option value="9">Sem frete</option>
              <option value="0">Por conta do emitente</option>
              <option value="1">Por conta do destinatário</option>
              <option value="2">Por conta de terceiros</option>
            </Select>
          </FormField>
        </div>

        <FormField label="Informações complementares">
          <Textarea {...register('informacoes_complementares')} />
        </FormField>

        <div className="flex items-start gap-2 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
          <p className="text-xs text-blue-800 dark:text-blue-300">
            A NF-e será criada em <strong>rascunho</strong>. Os itens/produtos e impostos
            são adicionados na edição, e a emissão para a SEFAZ é feita pela ação "emitir".
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
