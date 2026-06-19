import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Search, Pencil, Trash2 } from 'lucide-react'
import toast from 'react-hot-toast'
import { produtosApi } from '@/api'
import { Card } from '@/components/ui/Card'
import { Table } from '@/components/ui/Table'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Spinner } from '@/components/ui/Spinner'
import { formatMoeda, formatNumero } from '@/lib/utils'
import { ProdutoForm } from './ProdutoForm'

export default function Produtos() {
  const [busca, setBusca] = useState('')
  const [formAberto, setFormAberto] = useState(false)
  const [produtoEdicao, setProdutoEdicao] = useState(null)
  const qc = useQueryClient()

  const { data, isLoading } = useQuery({
    queryKey: ['produtos', busca],
    queryFn: async () => (await produtosApi.list({ search: busca })).data,
  })

  const excluir = useMutation({
    mutationFn: (id) => produtosApi.remove(id),
    onSuccess: () => {
      toast.success('Produto desativado.')
      qc.invalidateQueries({ queryKey: ['produtos'] })
    },
    onError: () => toast.error('Erro ao remover.'),
  })

  const abrirNovo = () => { setProdutoEdicao(null); setFormAberto(true) }
  const abrirEdicao = (p) => { setProdutoEdicao(p); setFormAberto(true) }
  const confirmarExclusao = (p) => {
    if (window.confirm(`Desativar "${p.descricao}"?`)) excluir.mutate(p.id)
  }

  const colunas = [
    { key: 'codigo', label: 'Código' },
    { key: 'descricao', label: 'Descrição' },
    { key: 'preco_venda', label: 'Preço', render: (r) => formatMoeda(r.preco_venda) },
    { key: 'estoque_atual', label: 'Estoque', render: (r) => (
      <span className={r.estoque_atual <= r.estoque_minimo ? 'text-red-500 font-medium' : ''}>
        {formatNumero(r.estoque_atual, 0)}
      </span>
    )},
    { key: 'tipo', label: 'Tipo', render: (r) => (
      <Badge cor={r.tipo === 'S' ? 'purple' : 'blue'}>{r.tipo === 'S' ? 'Serviço' : 'Produto'}</Badge>
    )},
    { key: 'acoes', label: '', render: (r) => (
      <div className="flex gap-1 justify-end">
        <button onClick={(e) => { e.stopPropagation(); abrirEdicao(r) }}
          className="p-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-500" title="Editar">
          <Pencil size={16} />
        </button>
        <button onClick={(e) => { e.stopPropagation(); confirmarExclusao(r) }}
          className="p-1.5 rounded hover:bg-red-50 text-red-500" title="Desativar">
          <Trash2 size={16} />
        </button>
      </div>
    )},
  ]

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Produtos</h1>
        <Button onClick={abrirNovo}><Plus size={18} /> Novo produto</Button>
      </div>
      <Card>
        <div className="relative mb-4">
          <Search size={18} className="absolute left-3 top-2.5 text-slate-400" />
          <input className="input pl-10" placeholder="Buscar produto..." value={busca} onChange={(e) => setBusca(e.target.value)} />
        </div>
        {isLoading ? <Spinner /> : <Table columns={colunas} data={data?.data || []} />}
      </Card>

      <ProdutoForm open={formAberto} onClose={() => setFormAberto(false)} produto={produtoEdicao} />
    </div>
  )
}
