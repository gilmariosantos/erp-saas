import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Search, Pencil, Trash2 } from 'lucide-react'
import toast from 'react-hot-toast'
import { pessoasApi } from '@/api'
import { Card } from '@/components/ui/Card'
import { Table } from '@/components/ui/Table'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Spinner } from '@/components/ui/Spinner'
import { formatCnpj } from '@/lib/utils'
import { PessoaForm } from './PessoaForm'

export default function Pessoas() {
  const [busca, setBusca] = useState('')
  const [formAberto, setFormAberto] = useState(false)
  const [pessoaEdicao, setPessoaEdicao] = useState(null)
  const qc = useQueryClient()

  const { data, isLoading } = useQuery({
    queryKey: ['pessoas', busca],
    queryFn: async () => (await pessoasApi.list({ search: busca })).data,
  })

  const excluir = useMutation({
    mutationFn: (id) => pessoasApi.remove(id),
    onSuccess: () => {
      toast.success('Pessoa removida.')
      qc.invalidateQueries({ queryKey: ['pessoas'] })
    },
    onError: () => toast.error('Erro ao remover.'),
  })

  const abrirNovo = () => { setPessoaEdicao(null); setFormAberto(true) }
  const abrirEdicao = (pessoa) => { setPessoaEdicao(pessoa); setFormAberto(true) }

  const confirmarExclusao = (pessoa) => {
    if (window.confirm(`Remover "${pessoa.nome}"?`)) excluir.mutate(pessoa.id)
  }

  const colunas = [
    { key: 'nome', label: 'Nome' },
    { key: 'documento', label: 'CNPJ/CPF', render: (r) => formatCnpj(r.cnpj) || r.cpf || '—' },
    { key: 'municipio', label: 'Cidade', render: (r) => r.municipio ? `${r.municipio}/${r.uf}` : '—' },
    { key: 'papeis', label: 'Tipo', render: (r) => (
      <div className="flex gap-1">
        {r.is_cliente && <Badge cor="blue">Cliente</Badge>}
        {r.is_fornecedor && <Badge cor="purple">Fornecedor</Badge>}
        {r.is_transportadora && <Badge cor="orange">Transp.</Badge>}
      </div>
    )},
    { key: 'acoes', label: '', render: (r) => (
      <div className="flex gap-1 justify-end">
        <button onClick={(e) => { e.stopPropagation(); abrirEdicao(r) }}
          className="p-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-500" title="Editar">
          <Pencil size={16} />
        </button>
        <button onClick={(e) => { e.stopPropagation(); confirmarExclusao(r) }}
          className="p-1.5 rounded hover:bg-red-50 text-red-500" title="Remover">
          <Trash2 size={16} />
        </button>
      </div>
    )},
  ]

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Clientes e Fornecedores</h1>
        <Button onClick={abrirNovo}><Plus size={18} /> Novo cadastro</Button>
      </div>

      <Card>
        <div className="relative mb-4">
          <Search size={18} className="absolute left-3 top-2.5 text-slate-400" />
          <input
            className="input pl-10"
            placeholder="Buscar por nome, CNPJ ou CPF..."
            value={busca}
            onChange={(e) => setBusca(e.target.value)}
          />
        </div>
        {isLoading ? <Spinner /> : <Table columns={colunas} data={data?.data || []} />}
      </Card>

      <PessoaForm open={formAberto} onClose={() => setFormAberto(false)} pessoa={pessoaEdicao} />
    </div>
  )
}
