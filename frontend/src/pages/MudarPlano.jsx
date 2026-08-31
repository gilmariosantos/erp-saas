import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { ArrowUp, ArrowDown, Check, AlertTriangle } from 'lucide-react'
import toast from 'react-hot-toast'
import { planosApi } from '@/api'
import { Modal } from '@/components/ui/Modal'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Spinner } from '@/components/ui/Spinner'
import { formatMoeda } from '@/lib/utils'

/**
 * Fluxo de upgrade/downgrade de plano.
 * Simula antes de efetivar (mostra preço e valida bloqueios de downgrade).
 */
export function MudarPlano({ planoAtualId }) {
  const qc = useQueryClient()
  const [ciclo, setCiclo] = useState('mensal')
  const [confirmacao, setConfirmacao] = useState(null)

  const { data, isLoading } = useQuery({
    queryKey: ['planos-mudanca'],
    queryFn: async () => (await planosApi.listarPublico()).data,
  })

  const simular = useMutation({
    mutationFn: (planoId) => planosApi.simular({ plano_id: planoId, ciclo }),
    onSuccess: ({ data }) => setConfirmacao(data),
    onError: () => toast.error('Erro ao simular mudança.'),
  })

  const mudar = useMutation({
    mutationFn: (planoId) => planosApi.mudar({ plano_id: planoId, ciclo }),
    onSuccess: ({ data }) => {
      toast.success(data.message)
      qc.invalidateQueries({ queryKey: ['assinatura-status'] })
      setConfirmacao(null)
    },
    onError: (err) => {
      const b = err.response?.data?.bloqueios
      toast.error(b ? b[0] : 'Erro ao mudar de plano.')
    },
  })

  if (isLoading) return <Spinner />

  const planos = data?.data || []

  return (
    <Card>
      <div className="flex items-center justify-between mb-4">
        <h3 className="font-medium">Mudar de plano</h3>
        <div className="inline-flex items-center gap-1 p-1 bg-slate-100 dark:bg-slate-800 rounded-lg text-sm">
          <button onClick={() => setCiclo('mensal')}
            className={`px-3 py-1 rounded ${ciclo === 'mensal' ? 'bg-white dark:bg-slate-700 shadow' : 'text-slate-500'}`}>
            Mensal
          </button>
          <button onClick={() => setCiclo('anual')}
            className={`px-3 py-1 rounded ${ciclo === 'anual' ? 'bg-white dark:bg-slate-700 shadow' : 'text-slate-500'}`}>
            Anual
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
        {planos.map((plano) => {
          const atual = plano.id === planoAtualId
          const preco = ciclo === 'anual' ? plano.preco_anual / 12 : plano.preco_mensal
          return (
            <div key={plano.id}
              className={`rounded-lg border p-4 ${atual ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-slate-200 dark:border-slate-700'}`}>
              <div className="flex items-center justify-between">
                <span className="font-medium">{plano.nome}</span>
                {atual && <Badge cor="blue">Atual</Badge>}
              </div>
              <p className="text-2xl font-bold mt-2">{formatMoeda(preco)}<span className="text-xs text-slate-400">/mês</span></p>
              {!atual && (
                <Button className="w-full mt-3 text-sm py-1.5" variant="secondary"
                  onClick={() => simular.mutate(plano.id)} disabled={simular.isPending}>
                  Selecionar
                </Button>
              )}
            </div>
          )
        })}
      </div>

      {/* Modal de confirmação */}
      <Modal open={!!confirmacao} onClose={() => setConfirmacao(null)}
        title={confirmacao?.tipo === 'upgrade' ? 'Confirmar upgrade' : 'Confirmar mudança'} size="sm">
        {confirmacao && (
          <div className="space-y-4">
            <div className="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-900/50 rounded-lg">
              {confirmacao.tipo === 'upgrade'
                ? <ArrowUp className="text-green-500" />
                : <ArrowDown className="text-amber-500" />}
              <div>
                <p className="text-sm">
                  {confirmacao.plano_atual} → <strong>{confirmacao.plano_novo}</strong>
                </p>
                <p className="text-lg font-semibold">{formatMoeda(confirmacao.preco)}
                  <span className="text-xs text-slate-400">/{confirmacao.ciclo}</span></p>
              </div>
            </div>

            {!confirmacao.pode_mudar && (
              <div className="p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                <div className="flex items-center gap-2 text-red-600 mb-1">
                  <AlertTriangle size={16} /> <span className="text-sm font-medium">Não é possível fazer downgrade</span>
                </div>
                {confirmacao.bloqueios.map((b, i) => (
                  <p key={i} className="text-xs text-red-600">{b}</p>
                ))}
              </div>
            )}

            <div className="flex justify-end gap-2">
              <Button variant="secondary" onClick={() => setConfirmacao(null)}>Cancelar</Button>
              <Button disabled={!confirmacao.pode_mudar || mudar.isPending}
                onClick={() => mudar.mutate(planos.find(p => p.nome === confirmacao.plano_novo)?.id)}>
                {mudar.isPending ? 'Confirmando...' : 'Confirmar'}
              </Button>
            </div>
          </div>
        )}
      </Modal>
    </Card>
  )
}
