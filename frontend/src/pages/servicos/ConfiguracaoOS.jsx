import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2, Settings, Tag, ListChecks } from 'lucide-react'
import toast from 'react-hot-toast'
import { tiposOsApi, statusOsApi } from '@/api'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Spinner } from '@/components/ui/Spinner'
import { Modal } from '@/components/ui/Modal'
import { FormField, Input } from '@/components/ui/FormField'

const CORES = ['#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6', '#10B981', '#6B7280', '#EC4899', '#14B8A6']

export default function ConfiguracaoOS() {
  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2">
        <Settings className="text-brand-600" size={24} />
        <h1 className="text-2xl font-semibold">Configuração de Ordens de Serviço</h1>
      </div>
      <p className="text-sm text-slate-500 dark:text-slate-400">
        Personalize os tipos de OS e o fluxo de status para o ramo da sua empresa.
      </p>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <TiposSecao />
        <StatusSecao />
      </div>
    </div>
  )
}

// ─── Tipos de OS ──────────────────────────────────────────────────────────────

function TiposSecao() {
  const qc = useQueryClient()
  const [modalAberto, setModalAberto] = useState(false)
  const [nome, setNome] = useState('')
  const [prefixo, setPrefixo] = useState('')

  const { data, isLoading } = useQuery({
    queryKey: ['tipos-os'],
    queryFn: async () => (await tiposOsApi.list()).data,
  })

  const criar = useMutation({
    mutationFn: () => tiposOsApi.create({ nome, prefixo }),
    onSuccess: () => {
      toast.success('Tipo criado!')
      qc.invalidateQueries({ queryKey: ['tipos-os'] })
      setModalAberto(false); setNome(''); setPrefixo('')
    },
    onError: (e) => toast.error(e.response?.data?.message || 'Erro.'),
  })

  const remover = useMutation({
    mutationFn: (id) => tiposOsApi.remove(id),
    onSuccess: () => { toast.success('Tipo removido.'); qc.invalidateQueries({ queryKey: ['tipos-os'] }) },
    onError: () => toast.error('Erro ao remover.'),
  })

  return (
    <Card>
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-2">
          <Tag size={18} className="text-brand-600" />
          <h3 className="font-medium">Tipos de OS</h3>
        </div>
        <Button onClick={() => setModalAberto(true)} className="text-sm py-1">
          <Plus size={16} /> Novo tipo
        </Button>
      </div>

      {isLoading ? <Spinner /> : (
        <div className="space-y-2">
          {(data?.data || []).length === 0 ? (
            <p className="text-sm text-slate-400 text-center py-4">Nenhum tipo cadastrado.</p>
          ) : (
            (data?.data || []).map((tipo) => (
              <div key={tipo.id} className="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-900/50 rounded-lg">
                <div className="flex items-center gap-2">
                  <span className="font-medium text-sm">{tipo.nome}</span>
                  {tipo.prefixo && <Badge cor="blue">{tipo.prefixo}</Badge>}
                  {tipo.exige_equipamento && <Badge cor="purple">Exige equipamento</Badge>}
                </div>
                <button onClick={() => window.confirm(`Remover "${tipo.nome}"?`) && remover.mutate(tipo.id)}
                  className="p-1.5 text-red-500 hover:bg-red-50 rounded">
                  <Trash2 size={16} />
                </button>
              </div>
            ))
          )}
        </div>
      )}

      <Modal open={modalAberto} onClose={() => setModalAberto(false)} title="Novo tipo de OS" size="sm">
        <div className="space-y-4">
          <FormField label="Nome" required>
            <Input value={nome} onChange={(e) => setNome(e.target.value)} placeholder="Ex: Conserto, Revisão" />
          </FormField>
          <FormField label="Prefixo do número">
            <Input value={prefixo} onChange={(e) => setPrefixo(e.target.value)} placeholder="Ex: OS, REV" maxLength={10} />
          </FormField>
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setModalAberto(false)}>Cancelar</Button>
            <Button onClick={() => nome && criar.mutate()} disabled={!nome || criar.isPending}>Salvar</Button>
          </div>
        </div>
      </Modal>
    </Card>
  )
}

// ─── Status de OS ─────────────────────────────────────────────────────────────

function StatusSecao() {
  const qc = useQueryClient()
  const [modalAberto, setModalAberto] = useState(false)
  const [nome, setNome] = useState('')
  const [cor, setCor] = useState(CORES[0])

  const { data, isLoading } = useQuery({
    queryKey: ['status-os'],
    queryFn: async () => (await statusOsApi.list()).data,
  })

  const criar = useMutation({
    mutationFn: () => statusOsApi.create({ nome, cor }),
    onSuccess: () => {
      toast.success('Status criado!')
      qc.invalidateQueries({ queryKey: ['status-os'] })
      setModalAberto(false); setNome(''); setCor(CORES[0])
    },
    onError: (e) => toast.error(e.response?.data?.message || 'Erro.'),
  })

  const remover = useMutation({
    mutationFn: (id) => statusOsApi.remove(id),
    onSuccess: () => { toast.success('Status removido.'); qc.invalidateQueries({ queryKey: ['status-os'] }) },
    onError: () => toast.error('Erro ao remover.'),
  })

  return (
    <Card>
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-2">
          <ListChecks size={18} className="text-brand-600" />
          <h3 className="font-medium">Fluxo de Status</h3>
        </div>
        <Button onClick={() => setModalAberto(true)} className="text-sm py-1">
          <Plus size={16} /> Novo status
        </Button>
      </div>

      {isLoading ? <Spinner /> : (
        <div className="space-y-2">
          {(data?.data || []).length === 0 ? (
            <p className="text-sm text-slate-400 text-center py-4">Nenhum status cadastrado.</p>
          ) : (
            (data?.data || []).map((status) => (
              <div key={status.id} className="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-900/50 rounded-lg">
                <div className="flex items-center gap-3">
                  <span className="w-4 h-4 rounded-full" style={{ backgroundColor: status.cor }} />
                  <span className="font-medium text-sm">{status.nome}</span>
                  {status.is_inicial && <Badge cor="blue">Inicial</Badge>}
                  {status.is_final && <Badge cor="green">Final</Badge>}
                  {status.is_cancelado && <Badge cor="red">Cancelamento</Badge>}
                </div>
                <button onClick={() => window.confirm(`Remover "${status.nome}"?`) && remover.mutate(status.id)}
                  className="p-1.5 text-red-500 hover:bg-red-50 rounded">
                  <Trash2 size={16} />
                </button>
              </div>
            ))
          )}
        </div>
      )}

      <Modal open={modalAberto} onClose={() => setModalAberto(false)} title="Novo status" size="sm">
        <div className="space-y-4">
          <FormField label="Nome" required>
            <Input value={nome} onChange={(e) => setNome(e.target.value)} placeholder="Ex: Aguardando aprovação" />
          </FormField>
          <FormField label="Cor">
            <div className="flex gap-2 flex-wrap">
              {CORES.map((c) => (
                <button key={c} onClick={() => setCor(c)}
                  className={`w-8 h-8 rounded-full border-2 ${cor === c ? 'border-slate-900 dark:border-white' : 'border-transparent'}`}
                  style={{ backgroundColor: c }} />
              ))}
            </div>
          </FormField>
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setModalAberto(false)}>Cancelar</Button>
            <Button onClick={() => nome && criar.mutate()} disabled={!nome || criar.isPending}>Salvar</Button>
          </div>
        </div>
      </Modal>
    </Card>
  )
}
