import { useForm } from 'react-hook-form'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import toast from 'react-hot-toast'
import { pessoasApi } from '@/api'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'
import { FormField, Input, Select } from '@/components/ui/FormField'

/**
 * Modal de criação/edição de Pessoa (cliente/fornecedor/transportadora).
 * Se receber `pessoa`, opera em modo edição; senão, criação.
 */
export function PessoaForm({ open, onClose, pessoa = null }) {
  const qc = useQueryClient()
  const editando = !!pessoa

  const { register, handleSubmit, reset, watch, formState: { errors } } = useForm({
    defaultValues: pessoa || {
      tipo_pessoa: 'PJ',
      is_cliente: true,
      is_fornecedor: false,
      is_transportadora: false,
    },
  })

  const tipoPessoa = watch('tipo_pessoa')

  const salvar = useMutation({
    mutationFn: (dados) =>
      editando ? pessoasApi.update(pessoa.id, dados) : pessoasApi.create(dados),
    onSuccess: () => {
      toast.success(editando ? 'Pessoa atualizada!' : 'Pessoa cadastrada!')
      qc.invalidateQueries({ queryKey: ['pessoas'] })
      reset()
      onClose()
    },
    onError: (err) => {
      const msg = err.response?.data?.message || 'Erro ao salvar.'
      toast.error(msg)
    },
  })

  const onSubmit = (dados) => salvar.mutate(dados)

  return (
    <Modal open={open} onClose={onClose} title={editando ? 'Editar pessoa' : 'Nova pessoa'} size="lg">
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormField label="Tipo" required>
            <Select {...register('tipo_pessoa')}>
              <option value="PJ">Pessoa Jurídica</option>
              <option value="PF">Pessoa Física</option>
            </Select>
          </FormField>

          <FormField label="Nome / Razão social" required error={errors.nome?.message} className="sm:col-span-1">
            <Input {...register('nome', { required: 'Campo obrigatório' })} error={errors.nome} />
          </FormField>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {tipoPessoa === 'PJ' ? (
            <FormField label="CNPJ">
              <Input placeholder="00.000.000/0000-00" {...register('cnpj')} />
            </FormField>
          ) : (
            <FormField label="CPF">
              <Input placeholder="000.000.000-00" {...register('cpf')} />
            </FormField>
          )}

          <FormField label="Inscrição Estadual">
            <Input {...register('ie')} />
          </FormField>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormField label="E-mail" error={errors.email?.message}>
            <Input type="email" {...register('email')} error={errors.email} />
          </FormField>
          <FormField label="Telefone">
            <Input {...register('telefone')} />
          </FormField>
        </div>

        {/* Endereço */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <FormField label="Município" className="sm:col-span-2">
            <Input {...register('municipio')} />
          </FormField>
          <FormField label="UF">
            <Input maxLength={2} placeholder="SP" {...register('uf')} />
          </FormField>
        </div>

        {/* Papéis */}
        <FormField label="Esta pessoa é">
          <div className="flex flex-wrap gap-4 mt-1">
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" {...register('is_cliente')} /> Cliente
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" {...register('is_fornecedor')} /> Fornecedor
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" {...register('is_transportadora')} /> Transportadora
            </label>
          </div>
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
