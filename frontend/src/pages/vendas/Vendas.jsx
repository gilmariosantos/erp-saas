import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { vendasApi } from '@/api'
import { Card } from '@/components/ui/Card'
import { Table } from '@/components/ui/Table'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Spinner } from '@/components/ui/Spinner'
import { formatMoeda, formatData } from '@/lib/utils'

const statusCor = {
  rascunho: 'gray', aguardando_aprovacao: 'yellow', aprovado: 'blue',
  faturado: 'green', entregue: 'green', cancelado: 'red',
}

export default function Vendas() {
  const { data, isLoading } = useQuery({
    queryKey: ['vendas'],
    queryFn: async () => (await vendasApi.list()).data,
  })

  const colunas = [
    { key: 'numero', label: 'Nº' },
    { key: 'cliente', label: 'Cliente', render: (r) => r.cliente?.nome || '—' },
    { key: 'data_pedido', label: 'Data', render: (r) => formatData(r.data_pedido) },
    { key: 'total_pedido', label: 'Total', render: (r) => formatMoeda(r.total_pedido) },
    { key: 'tipo', label: 'Tipo', render: (r) => (
      <Badge cor={r.tipo === 'orcamento' ? 'purple' : 'blue'}>
        {r.tipo === 'orcamento' ? 'Orçamento' : 'Pedido'}
      </Badge>
    )},
    { key: 'status', label: 'Status', render: (r) => (
      <Badge cor={statusCor[r.status] || 'gray'}>{r.status}</Badge>
    )},
  ]

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Vendas</h1>
        <Button><Plus size={18} /> Novo pedido</Button>
      </div>
      <Card>
        {isLoading ? <Spinner /> : <Table columns={colunas} data={data?.data || []} />}
      </Card>
    </div>
  )
}
