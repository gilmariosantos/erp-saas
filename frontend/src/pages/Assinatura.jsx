import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { CreditCard, QrCode, FileText, Check } from 'lucide-react'
import toast from 'react-hot-toast'
import api from '@/api/client'
import { Card, MetricCard } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Spinner } from '@/components/ui/Spinner'
import { formatMoeda, formatData } from '@/lib/utils'

const assinaturaApi = {
  status: () => api.get('/v1/assinatura/status'),
  faturas: () => api.get('/v1/assinatura/faturas'),
  gerarCobranca: (data) => api.post('/v1/assinatura/cobranca', data),
}

export default function Assinatura() {
  const qc = useQueryClient()
  const [gateway, setGateway] = useState('asaas')
  const [metodo, setMetodo] = useState('pix')

  const { data: status, isLoading } = useQuery({
    queryKey: ['assinatura-status'],
    queryFn: async () => (await assinaturaApi.status()).data,
  })

  const { data: faturas } = useQuery({
    queryKey: ['assinatura-faturas'],
    queryFn: async () => (await assinaturaApi.faturas()).data,
  })

  const gerar = useMutation({
    mutationFn: () => assinaturaApi.gerarCobranca({ gateway, metodo }),
    onSuccess: () => {
      toast.success('Cobrança gerada! Verifique suas faturas.')
      qc.invalidateQueries({ queryKey: ['assinatura-faturas'] })
    },
    onError: (e) => toast.error(e.response?.data?.message || 'Erro ao gerar cobrança.'),
  })

  if (isLoading) return <Spinner size={40} />

  const uso = status?.uso || {}
  const assin = status?.assinatura || {}

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold">Assinatura e Cobrança</h1>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <MetricCard label="Plano atual" value={uso.plano || '—'} icon={Check} color="brand" />
        <MetricCard label="NF-e este mês" value={`${uso.nfe?.usado || 0} / ${uso.nfe?.limite || 0}`} color={uso.nfe?.percentual > 80 ? 'amber' : 'green'} />
        <MetricCard label="CT-e este mês" value={`${uso.cte?.usado || 0} / ${uso.cte?.limite || 0}`} color="brand" />
      </div>

      <Card>
        <h3 className="font-medium mb-4">Gerar pagamento</h3>
        <div className="space-y-4">
          <div>
            <label className="label">Gateway</label>
            <div className="flex gap-2">
              <button onClick={() => setGateway('asaas')} className={`btn ${gateway === 'asaas' ? 'btn-primary' : 'btn-secondary'}`}>Asaas</button>
              <button onClick={() => setGateway('mercadopago')} className={`btn ${gateway === 'mercadopago' ? 'btn-primary' : 'btn-secondary'}`}>Mercado Pago</button>
            </div>
          </div>
          <div>
            <label className="label">Forma de pagamento</label>
            <div className="flex gap-2">
              <button onClick={() => setMetodo('pix')} className={`btn ${metodo === 'pix' ? 'btn-primary' : 'btn-secondary'}`}><QrCode size={16} /> PIX</button>
              <button onClick={() => setMetodo('boleto')} className={`btn ${metodo === 'boleto' ? 'btn-primary' : 'btn-secondary'}`}><FileText size={16} /> Boleto</button>
              <button onClick={() => setMetodo('cartao')} className={`btn ${metodo === 'cartao' ? 'btn-primary' : 'btn-secondary'}`}><CreditCard size={16} /> Cartão</button>
            </div>
          </div>
          <Button onClick={() => gerar.mutate()} disabled={gerar.isPending}>
            {gerar.isPending ? 'Gerando...' : 'Gerar cobrança'}
          </Button>
        </div>
      </Card>

      <Card>
        <h3 className="font-medium mb-4">Histórico de faturas</h3>
        <div className="space-y-2">
          {(faturas?.data || []).length === 0 ? (
            <p className="text-sm text-slate-400 py-4 text-center">Nenhuma fatura emitida ainda.</p>
          ) : (
            (faturas?.data || []).map((f) => (
              <div key={f.id} className="flex items-center justify-between py-3 border-b border-slate-100 dark:border-slate-700">
                <div>
                  <p className="font-medium text-sm">{f.numero}</p>
                  <p className="text-xs text-slate-400">Venc. {formatData(f.vencimento)}</p>
                </div>
                <div className="flex items-center gap-3">
                  <span className="font-medium">{formatMoeda(f.valor)}</span>
                  <Badge cor={f.status === 'pago' ? 'green' : f.status === 'vencido' ? 'red' : 'yellow'}>{f.status}</Badge>
                  {f.link_pagamento && f.status !== 'pago' && (
                    <a href={f.link_pagamento} target="_blank" rel="noreferrer" className="text-brand-600 text-sm hover:underline">Pagar</a>
                  )}
                </div>
              </div>
            ))
          )}
        </div>
      </Card>
    </div>
  )
}
