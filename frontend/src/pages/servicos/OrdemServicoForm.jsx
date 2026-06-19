import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2, Wrench, Package } from 'lucide-react'
import toast from 'react-hot-toast'
import { ordensServicoApi, pessoasApi, produtosApi } from '@/api'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'
import { FormField, Input, Select, Textarea } from '@/components/ui/FormField'
import { formatMoeda } from '@/lib/utils'

/**
 * Formulário de Ordem de Serviço.
 * Permite adicionar serviços (mão de obra) e peças (com baixa de estoque).
 */
export function OrdemServicoForm({ open, onClose }) {
  const qc = useQueryClient()
  const [clienteId, setClienteId] = useState('')
  const [problema, setProblema] = useState('')
  const [garantia, setGarantia] = useState(90)
  const [itens, setItens] = useState([])

  const { data: clientes } = useQuery({
    queryKey: ['pessoas-clientes-os'],
    queryFn: async () => (await pessoasApi.list({ cliente: true })).data,
    enabled: open,
  })
  const { data: produtos } = useQuery({
    queryKey: ['produtos-os'],
    queryFn: async () => (await produtosApi.list()).data,
    enabled: open,
  })

  useEffect(() => {
    if (open) { setClienteId(''); setProblema(''); setGarantia(90); setItens([]) }
  }, [open])

  const adicionarItem = (tipo) => {
    setItens([...itens, { tipo, produto_id: '', descricao: '', quantidade: 1, valor_unitario: 0 }])
  }

  const atualizarItem = (i, campo, valor) => {
    const novos = [...itens]
    novos[i][campo] = valor
    if (campo === 'produto_id') {
      const prod = produtos?.data?.find((p) => p.id === Number(valor))
      if (prod) {
        novos[i].valor_unitario = Number(prod.preco_venda)
        novos[i].descricao = prod.descricao
      }
    }
    setItens(novos)
  }

  const removerItem = (i) => setItens(itens.filter((_, idx) => idx !== i))

  const totalItem = (it) => (it.quantidade || 0) * (it.valor_unitario || 0)
  const totalGeral = itens.reduce((acc, it) => acc + totalItem(it), 0)

  const salvar = useMutation({
    mutationFn: (dados) => ordensServicoApi.create(dados),
    onSuccess: () => {
      toast.success('Ordem de serviço criada!')
      qc.invalidateQueries({ queryKey: ['ordens-servico'] })
      onClose()
    },
    onError: (err) => toast.error(err.response?.data?.message || 'Erro ao criar OS.'),
  })

  const onSubmit = () => {
    if (!clienteId) return toast.error('Selecione um cliente.')
    if (itens.length === 0) return toast.error('Adicione ao menos um serviço ou peça.')

    salvar.mutate({
      cliente_id: Number(clienteId),
      descricao_problema: problema,
      garantia_dias: Number(garantia),
      data_abertura: new Date().toISOString().slice(0, 10),
      itens: itens.map((i) => ({
        tipo: i.tipo,
        produto_id: i.produto_id ? Number(i.produto_id) : null,
        descricao: i.descricao,
        quantidade: Number(i.quantidade),
        valor_unitario: Number(i.valor_unitario),
      })),
    })
  }

  const servicos = itens.filter((i) => i.tipo === 'servico')
  const pecas = itens.filter((i) => i.tipo === 'peca')

  return (
    <Modal open={open} onClose={onClose} title="Nova ordem de serviço" size="xl">
      <div className="space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormField label="Cliente" required>
            <Select value={clienteId} onChange={(e) => setClienteId(e.target.value)}>
              <option value="">Selecione...</option>
              {(clientes?.data || []).map((c) => <option key={c.id} value={c.id}>{c.nome}</option>)}
            </Select>
          </FormField>
          <FormField label="Garantia (dias)">
            <Input type="number" value={garantia} onChange={(e) => setGarantia(e.target.value)} />
          </FormField>
        </div>

        <FormField label="Problema relatado pelo cliente">
          <Textarea value={problema} onChange={(e) => setProblema(e.target.value)} placeholder="Descreva o problema..." />
        </FormField>

        {/* Botões de adicionar */}
        <div className="flex gap-2">
          <Button type="button" variant="secondary" onClick={() => adicionarItem('servico')} className="text-sm py-1">
            <Wrench size={16} /> Adicionar serviço
          </Button>
          <Button type="button" variant="secondary" onClick={() => adicionarItem('peca')} className="text-sm py-1">
            <Package size={16} /> Adicionar peça
          </Button>
        </div>

        {/* Lista de itens */}
        {itens.length === 0 ? (
          <p className="text-sm text-slate-400 text-center py-6 border border-dashed border-slate-300 dark:border-slate-700 rounded-lg">
            Adicione serviços (mão de obra) e peças.
          </p>
        ) : (
          <div className="space-y-2">
            {itens.map((item, i) => (
              <div key={i} className="grid grid-cols-12 gap-2 items-center bg-slate-50 dark:bg-slate-900/50 p-2 rounded-lg">
                <div className="col-span-1 flex justify-center">
                  {item.tipo === 'servico'
                    ? <Wrench size={16} className="text-blue-500" />
                    : <Package size={16} className="text-purple-500" />}
                </div>
                {item.tipo === 'peca' ? (
                  <div className="col-span-4">
                    <Select value={item.produto_id} onChange={(e) => atualizarItem(i, 'produto_id', e.target.value)}>
                      <option value="">Peça...</option>
                      {(produtos?.data || []).map((p) => <option key={p.id} value={p.id}>{p.descricao}</option>)}
                    </Select>
                  </div>
                ) : (
                  <div className="col-span-4">
                    <Input placeholder="Descrição do serviço" value={item.descricao}
                      onChange={(e) => atualizarItem(i, 'descricao', e.target.value)} />
                  </div>
                )}
                <div className="col-span-2">
                  <Input type="number" step="0.01" placeholder="Qtd" value={item.quantidade}
                    onChange={(e) => atualizarItem(i, 'quantidade', e.target.value)} />
                </div>
                <div className="col-span-2">
                  <Input type="number" step="0.01" placeholder="Valor" value={item.valor_unitario}
                    onChange={(e) => atualizarItem(i, 'valor_unitario', e.target.value)} />
                </div>
                <div className="col-span-2 text-right text-sm font-medium">{formatMoeda(totalItem(item))}</div>
                <div className="col-span-1 flex justify-end">
                  <button type="button" onClick={() => removerItem(i)} className="p-1 text-red-500 hover:bg-red-50 rounded">
                    <Trash2 size={16} />
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Resumo */}
        <div className="flex justify-end gap-6 pt-2 border-t border-slate-200 dark:border-slate-700 text-sm">
          <span className="text-slate-500">Serviços: <strong>{formatMoeda(servicos.reduce((a, i) => a + totalItem(i), 0))}</strong></span>
          <span className="text-slate-500">Peças: <strong>{formatMoeda(pecas.reduce((a, i) => a + totalItem(i), 0))}</strong></span>
          <span className="text-lg font-semibold">Total: {formatMoeda(totalGeral)}</span>
        </div>

        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button>
          <Button type="button" onClick={onSubmit} disabled={salvar.isPending}>
            {salvar.isPending ? 'Salvando...' : 'Criar OS'}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
