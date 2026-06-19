import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Search, CheckCircle, Flag } from 'lucide-react'
import toast from 'react-hot-toast'
import { ordensServicoApi } from '@/api'
import { Card } from '@/components/ui/Card'
import { Table } from '@/components/ui/Table'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Spinner } from '@/components/ui/Spinner'
import { formatMoeda, formatData } from '@/lib/utils'
import { OrdemServicoForm } from './OrdemServicoForm'

const situacaoCor = {
  orcamento: 'gray', aprovada: 'blue', em_execucao: 'yellow',
  concluida: 'green', entregue: 'green', cancelada: 'red',
}
const situacaoLabel = {
  orcamento: 'Orçamento', aprovada: 'Aprovada', em_execucao: 'Em execução',
  concluida: 'Concluída', entregue: 'Entregue', cancelada: 'Cancelada',
}

export default function OrdensServico() {
  const [busca, setBusca] = useState('')
  const [formAberto, setFormAberto] = useState(false)
  const qc = useQueryClient()

  const { data, isLoading } = useQuery({
    queryKey: ['ordens-servico', busca],
    queryFn: async () => (await ordensServicoApi.list({ search: busca })).data,
  })

  const aprovar = useMutation({
    mutationFn: (id) => ordensServicoApi.aprovar(id),
    onSuccess: () => { toast.success('Orçamento aprovado!'); qc.invalidateQueries({ queryKey: ['ordens-servico'] }) },
    onError: (e) => toast.error(e.response?.data?.message || 'Erro.'),
  })

  const finalizar = useMutation({
    mutationFn: (id) => ordensServicoApi.finalizar(id, { gerar_nfse: true, gerar_financeiro: true }),
    onSuccess: () => { toast.success('OS finalizada! NFS-e e financeiro gerados.'); qc.invalidateQueries({ queryKey: ['ordens-servico'] }) },
    onError: (e) => toast.error(e.response?.data?.message || 'Erro.'),
  })

  const colunas = [
    { key: 'numero', label: 'Nº' },
    { key: 'cliente', label: 'Cliente', render: (r) => r.cliente?.nome || '—' },
    { key: 'data_abertura', label: 'Abertura', render: (r) => formatData(r.data_abertura) },
    { key: 'tecnico', label: 'Técnico', render: (r) => r.tecnico?.name || '—' },
    { key: 'total_geral', label: 'Total', render: (r) => formatMoeda(r.total_geral) },
    { key: 'situacao', label: 'Situação', render: (r) => (
      <Badge cor={situacaoCor[r.situacao] || 'gray'}>{situacaoLabel[r.situacao] || r.situacao}</Badge>
    )},
    { key: 'acoes', label: '', render: (r) => (
      <div className="flex gap-1 justify-end">
        {r.situacao === 'orcamento' && (
          <button onClick={(e) => { e.stopPropagation(); aprovar.mutate(r.id) }}
            className="p-1.5 rounded hover:bg-blue-50 text-blue-600" title="Aprovar orçamento">
            <CheckCircle size={16} />
          </button>
        )}
        {['aprovada', 'em_execucao'].includes(r.situacao) && (
          <button onClick={(e) => { e.stopPropagation(); finalizar.mutate(r.id) }}
            className="p-1.5 rounded hover:bg-green-50 text-green-600" title="Finalizar OS">
            <Flag size={16} />
          </button>
        )}
      </div>
    )},
  ]

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Ordens de Serviço</h1>
        <Button onClick={() => setFormAberto(true)}><Plus size={18} /> Nova OS</Button>
      </div>
      <Card>
        <div className="relative mb-4">
          <Search size={18} className="absolute left-3 top-2.5 text-slate-400" />
          <input className="input pl-10" placeholder="Buscar por número ou cliente..." value={busca} onChange={(e) => setBusca(e.target.value)} />
        </div>
        {isLoading ? <Spinner /> : <Table columns={colunas} data={data?.data || []} />}
      </Card>

      <OrdemServicoForm open={formAberto} onClose={() => setFormAberto(false)} />
    </div>
  )
}
