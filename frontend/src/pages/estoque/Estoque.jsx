import { useQuery } from '@tanstack/react-query'
import { AlertTriangle } from 'lucide-react'
import { estoqueApi } from '@/api'
import { Card, MetricCard } from '@/components/ui/Card'
import { Table } from '@/components/ui/Table'
import { Spinner } from '@/components/ui/Spinner'
import { formatMoeda, formatNumero } from '@/lib/utils'

export default function Estoque() {
  const { data: posicao, isLoading } = useQuery({
    queryKey: ['estoque-posicao'],
    queryFn: async () => (await estoqueApi.posicao()).data,
  })
  const { data: alertas } = useQuery({
    queryKey: ['estoque-alertas'],
    queryFn: async () => (await estoqueApi.alertas()).data,
  })

  const colunas = [
    { key: 'descricao', label: 'Produto' },
    { key: 'estoque_atual', label: 'Estoque', render: (r) => formatNumero(r.estoque_atual, 0) },
    { key: 'estoque_minimo', label: 'Mínimo', render: (r) => formatNumero(r.estoque_minimo, 0) },
    { key: 'valor_total', label: 'Valor', render: (r) => formatMoeda(r.valor_total) },
  ]

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold">Estoque</h1>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <MetricCard label="Valor total em estoque" value={formatMoeda(posicao?.total_valor)} />
        <MetricCard label="Total de produtos" value={posicao?.data?.length || 0} />
        <MetricCard
          label="Abaixo do mínimo"
          value={alertas?.total || 0}
          icon={AlertTriangle}
          color={alertas?.total > 0 ? 'red' : 'green'}
        />
      </div>

      <Card>
        <h3 className="font-medium mb-4">Posição de estoque</h3>
        {isLoading ? <Spinner /> : <Table columns={colunas} data={posicao?.data || []} />}
      </Card>
    </div>
  )
}
