import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Plus, Search } from 'lucide-react'
import { produtosApi } from '@/api'
import { Card } from '@/components/ui/Card'
import { Table } from '@/components/ui/Table'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Spinner } from '@/components/ui/Spinner'
import { formatMoeda, formatNumero } from '@/lib/utils'

export default function Produtos() {
  const [busca, setBusca] = useState('')
  const { data, isLoading } = useQuery({
    queryKey: ['produtos', busca],
    queryFn: async () => (await produtosApi.list({ search: busca })).data,
  })

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
  ]

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Produtos</h1>
        <Button><Plus size={18} /> Novo produto</Button>
      </div>
      <Card>
        <div className="relative mb-4">
          <Search size={18} className="absolute left-3 top-2.5 text-slate-400" />
          <input className="input pl-10" placeholder="Buscar produto..." value={busca} onChange={(e) => setBusca(e.target.value)} />
        </div>
        {isLoading ? <Spinner /> : <Table columns={colunas} data={data?.data || []} />}
      </Card>
    </div>
  )
}
