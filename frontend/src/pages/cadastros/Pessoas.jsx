import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Plus, Search } from 'lucide-react'
import { pessoasApi } from '@/api'
import { Card } from '@/components/ui/Card'
import { Table } from '@/components/ui/Table'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Spinner } from '@/components/ui/Spinner'
import { formatCnpj } from '@/lib/utils'

export default function Pessoas() {
  const [busca, setBusca] = useState('')
  const { data, isLoading } = useQuery({
    queryKey: ['pessoas', busca],
    queryFn: async () => (await pessoasApi.list({ search: busca })).data,
  })

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
  ]

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Clientes e Fornecedores</h1>
        <Button><Plus size={18} /> Novo cadastro</Button>
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
    </div>
  )
}
