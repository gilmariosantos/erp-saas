import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { financeiroApi } from '@/api'
import { Card } from '@/components/ui/Card'
import { Table } from '@/components/ui/Table'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Spinner } from '@/components/ui/Spinner'
import { formatMoeda, formatData } from '@/lib/utils'

const statusCor = { aberto: 'blue', parcial: 'yellow', pago: 'green', vencido: 'red', cancelado: 'gray' }

export default function Financeiro() {
  const [tipo, setTipo] = useState('receber')
  const { data, isLoading } = useQuery({
    queryKey: ['lancamentos', tipo],
    queryFn: async () => (await financeiroApi.list({ tipo })).data,
  })

  const colunas = [
    { key: 'descricao', label: 'Descrição' },
    { key: 'pessoa', label: 'Cliente/Fornecedor', render: (r) => r.pessoa?.nome || '—' },
    { key: 'data_vencimento', label: 'Vencimento', render: (r) => formatData(r.data_vencimento) },
    { key: 'valor_original', label: 'Valor', render: (r) => formatMoeda(r.valor_original) },
    { key: 'status', label: 'Status', render: (r) => (
      <Badge cor={statusCor[r.status?.value || r.status] || 'gray'}>
        {r.status_label || r.status}
      </Badge>
    )},
  ]

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Financeiro</h1>
        <Button><Plus size={18} /> Novo lançamento</Button>
      </div>

      <div className="flex gap-2">
        <button onClick={() => setTipo('receber')} className={`btn ${tipo === 'receber' ? 'btn-primary' : 'btn-secondary'}`}>
          Contas a Receber
        </button>
        <button onClick={() => setTipo('pagar')} className={`btn ${tipo === 'pagar' ? 'btn-primary' : 'btn-secondary'}`}>
          Contas a Pagar
        </button>
      </div>

      <Card>
        {isLoading ? <Spinner /> : <Table columns={colunas} data={data?.data || []} />}
      </Card>
    </div>
  )
}
