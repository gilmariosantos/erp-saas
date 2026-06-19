import { useForm } from 'react-hook-form'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import toast from 'react-hot-toast'
import { produtosApi } from '@/api'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'
import { FormField, Input, Select } from '@/components/ui/FormField'

/**
 * Modal de criação/edição de Produto ou Serviço.
 */
export function ProdutoForm({ open, onClose, produto = null }) {
  const qc = useQueryClient()
  const editando = !!produto

  const { register, handleSubmit, reset, watch, formState: { errors } } = useForm({
    defaultValues: produto || {
      tipo: 'P',
      controla_estoque: true,
      origem: '0',
    },
  })

  const tipo = watch('tipo')

  const salvar = useMutation({
    mutationFn: (dados) =>
      editando ? produtosApi.update(produto.id, dados) : produtosApi.create(dados),
    onSuccess: () => {
      toast.success(editando ? 'Produto atualizado!' : 'Produto cadastrado!')
      qc.invalidateQueries({ queryKey: ['produtos'] })
      reset()
      onClose()
    },
    onError: (err) => toast.error(err.response?.data?.message || 'Erro ao salvar.'),
  })

  return (
    <Modal open={open} onClose={onClose} title={editando ? 'Editar produto' : 'Novo produto'} size="lg">
      <form onSubmit={handleSubmit((d) => salvar.mutate(d))} className="space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <FormField label="Tipo" required>
            <Select {...register('tipo')}>
              <option value="P">Produto</option>
              <option value="S">Serviço</option>
              <option value="C">Combo</option>
            </Select>
          </FormField>
          <FormField label="Código" error={errors.codigo?.message}>
            <Input {...register('codigo')} />
          </FormField>
          <FormField label="Código de barras">
            <Input {...register('codigo_barras')} />
          </FormField>
        </div>

        <FormField label="Descrição" required error={errors.descricao?.message}>
          <Input {...register('descricao', { required: 'Campo obrigatório' })} error={errors.descricao} />
        </FormField>

        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <FormField label="Preço de custo">
            <Input type="number" step="0.01" {...register('preco_custo')} />
          </FormField>
          <FormField label="Preço de venda" required>
            <Input type="number" step="0.01" {...register('preco_venda', { required: true })} />
          </FormField>
          <FormField label="NCM">
            <Input placeholder="00000000" {...register('ncm')} />
          </FormField>
        </div>

        {tipo === 'P' && (
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <FormField label="Estoque atual">
              <Input type="number" step="0.01" {...register('estoque_atual')} />
            </FormField>
            <FormField label="Estoque mínimo">
              <Input type="number" step="0.01" {...register('estoque_minimo')} />
            </FormField>
            <FormField label="Controla estoque">
              <div className="flex items-center h-10">
                <input type="checkbox" {...register('controla_estoque')} className="mr-2" />
                <span className="text-sm">Sim</span>
              </div>
            </FormField>
          </div>
        )}

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
