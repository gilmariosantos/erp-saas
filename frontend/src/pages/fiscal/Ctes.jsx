import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { fiscalApi } from '@/api'
import { Card } from '@/components/ui/Card'
import { Table } from '@/components/ui/Table'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Spinner } from '@/components/ui/Spinner'
import { formatMoeda, formatData } from '@/lib/utils'

const statusCor = { rascunho: 'gray', autorizada: 'green', cancelada: 'red', rejeitada: 'orange' }

export default function Ctes() {
  const { data, isLoading } = useQuery({
    queryKey: ['ctes'],
    queryFn: async () => (await fiscalApi.ctes.list()).data,
  })

  const colunas = [
    { key: 'numero', label: 'Número' },
    { key: 'rota', label: 'Rota', render: (r) => `${r.uf_inicio || '?'} → ${r.uf_fim || '?'}` },
    { key: 'data_emissao', label: 'Emissão', render: (r) => formatData(r.data_emissao) },
    { key: 'valor_total_servico', label: 'Valor', render: (r) => formatMoeda(r.valor_total_servico) },
    { key: 'ciot', label: 'CIOT', render: (r) => r.tem_ciot ? <Badge cor="green">{r.ciot}</Badge> : <Badge cor="gray">—</Badge> },
    { key: 'status', label: 'Status', render: (r) => (
      <Badge cor={statusCor[r.status] || 'gray'}>{r.status_label || r.status}</Badge>
    )},
  ]

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">CT-e / CIOT</h1>
        <Button><Plus size={18} /> Novo CT-e</Button>
      </div>
      <Card>
        {isLoading ? <Spinner /> : <Table columns={colunas} data={data?.data || []} />}
      </Card>
    </div>
  )
}
