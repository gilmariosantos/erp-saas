import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Send, Download } from 'lucide-react'
import toast from 'react-hot-toast'
import { fiscalApi } from '@/api'
import { Card } from '@/components/ui/Card'
import { Table } from '@/components/ui/Table'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Spinner } from '@/components/ui/Spinner'
import { formatMoeda, formatData } from '@/lib/utils'

const statusCor = {
  rascunho: 'gray', pendente: 'yellow', processando: 'blue',
  autorizada: 'green', cancelada: 'red', rejeitada: 'orange',
}

export default function NotasFiscais() {
  const qc = useQueryClient()
  const { data, isLoading } = useQuery({
    queryKey: ['nfes'],
    queryFn: async () => (await fiscalApi.nfes.list()).data,
  })

  const emitir = useMutation({
    mutationFn: (id) => fiscalApi.nfes.emitir(id),
    onSuccess: () => {
      toast.success('NF-e enviada para processamento.')
      qc.invalidateQueries({ queryKey: ['nfes'] })
    },
    onError: (e) => toast.error(e.response?.data?.message || 'Erro ao emitir.'),
  })

  const baixarXml = async (id) => {
    try {
      const { data } = await fiscalApi.nfes.downloadXml(id)
      window.open(data.url, '_blank')
    } catch {
      toast.error('XML não disponível.')
    }
  }

  const colunas = [
    { key: 'numero', label: 'Número' },
    { key: 'destinatario_nome', label: 'Destinatário' },
    { key: 'data_emissao', label: 'Emissão', render: (r) => formatData(r.data_emissao) },
    { key: 'total_nota', label: 'Valor', render: (r) => formatMoeda(r.total_nota) },
    { key: 'status', label: 'Status', render: (r) => (
      <Badge cor={statusCor[r.status] || 'gray'}>{r.status_label || r.status}</Badge>
    )},
    { key: 'acoes', label: 'Ações', render: (r) => (
      <div className="flex gap-1">
        {r.pode_emitir && (
          <button onClick={(e) => { e.stopPropagation(); emitir.mutate(r.id) }}
            className="p-1.5 rounded hover:bg-brand-50 text-brand-600" title="Emitir">
            <Send size={16} />
          </button>
        )}
        {r.tem_xml && (
          <button onClick={(e) => { e.stopPropagation(); baixarXml(r.id) }}
            className="p-1.5 rounded hover:bg-slate-100 text-slate-600" title="Baixar XML">
            <Download size={16} />
          </button>
        )}
      </div>
    )},
  ]

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Notas Fiscais (NF-e)</h1>
        <Button><Plus size={18} /> Nova NF-e</Button>
      </div>
      <Card>
        {isLoading ? <Spinner /> : <Table columns={colunas} data={data?.data || []} />}
      </Card>
    </div>
  )
}
