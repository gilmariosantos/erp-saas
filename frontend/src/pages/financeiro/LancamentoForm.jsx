import { useForm } from 'react-hook-form'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import toast from 'react-hot-toast'
import { financeiroApi } from '@/api'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'
import { FormField, Input, Select, Textarea } from '@/components/ui/FormField'

/**
 * Modal de criação/edição de lançamento financeiro (a pagar / a receber).
 */
export function LancamentoForm({ open, onClose, lancamento = null, tipoInicial = 'receber' }) {
  const qc = useQueryClient()
  const editando = !!lancamento

  const { register, handleSubmit, reset, formState: { errors } } = useForm({
    defaultValues: lancamento || {
      tipo: tipoInicial,
      data_emissao: new Date().toISOString().slice(0, 10),
      data_vencimento: new Date().toISOString().slice(0, 10),
    },
  })

  const salvar = useMutation({
    mutationFn: (dados) =>
      editando ? financeiroApi.update(lancamento.id, dados) : financeiroApi.create(dados),
    onSuccess: () => {
      toast.success(editando ? 'Lançamento atualizado!' : 'Lançamento criado!')
      qc.invalidateQueries({ queryKey: ['lancamentos'] })
      reset()
      onClose()
    },
    onError: (err) => toast.error(err.response?.data?.message || 'Erro ao salvar.'),
  })

  return (
    <Modal open={open} onClose={onClose} title={editando ? 'Editar lançamento' : 'Novo lançamento'} size="lg">
      <form onSubmit={handleSubmit((d) => salvar.mutate(d))} className="space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormField label="Tipo" required>
            <Select {...register('tipo')}>
              <option value="receber">A Receber</option>
              <option value="pagar">A Pagar</option>
            </Select>
          </FormField>
          <FormField label="Valor" required error={errors.valor_original?.message}>
            <Input type="number" step="0.01" {...register('valor_original', { required: 'Informe o valor' })} error={errors.valor_original} />
          </FormField>
        </div>

        <FormField label="Descrição" required error={errors.descricao?.message}>
          <Input {...register('descricao', { required: 'Campo obrigatório' })} error={errors.descricao} />
        </FormField>

        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <FormField label="Emissão">
            <Input type="date" {...register('data_emissao')} />
          </FormField>
          <FormField label="Vencimento" required>
            <Input type="date" {...register('data_vencimento', { required: true })} />
          </FormField>
          <FormField label="Nº documento">
            <Input {...register('numero_documento')} />
          </FormField>
        </div>

        <FormField label="Observação">
          <Textarea {...register('observacao')} />
        </FormField>

        <div className="flex justify-end gap-2 pt-2">
          <Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button>
          <Button type="submit" disabled={salvar.isPending}>
            {salvar.isPending ? 'Salvando...' : 'Salvar'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
