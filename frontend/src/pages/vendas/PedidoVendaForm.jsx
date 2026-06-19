import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import toast from 'react-hot-toast'
import { vendasApi, pessoasApi, produtosApi } from '@/api'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'
import { FormField, Input, Select } from '@/components/ui/FormField'
import { formatMoeda } from '@/lib/utils'

/**
 * Modal de criação de pedido/orçamento de venda.
 * Permite adicionar múltiplos itens com cálculo de total em tempo real.
 */
export function PedidoVendaForm({ open, onClose }) {
  const qc = useQueryClient()
  const [tipo, setTipo] = useState('pedido')
  const [clienteId, setClienteId] = useState('')
  const [itens, setItens] = useState([])

  // Carrega clientes e produtos para os selects
  const { data: clientes } = useQuery({
    queryKey: ['pessoas-clientes'],
    queryFn: async () => (await pessoasApi.list({ cliente: true })).data,
    enabled: open,
  })
  const { data: produtos } = useQuery({
    queryKey: ['produtos-venda'],
    queryFn: async () => (await produtosApi.list()).data,
    enabled: open,
  })

  // Reseta ao abrir
  useEffect(() => {
    if (open) { setTipo('pedido'); setClienteId(''); setItens([]) }
  }, [open])

  const adicionarItem = () => {
    setItens([...itens, { produto_id: '', quantidade: 1, preco_unitario: 0, desconto_percentual: 0 }])
  }

  const atualizarItem = (i, campo, valor) => {
    const novos = [...itens]
    novos[i][campo] = valor
    // Auto-preenche o preço ao escolher o produto
    if (campo === 'produto_id') {
      const prod = produtos?.data?.find((p) => p.id === Number(valor))
      if (prod) novos[i].preco_unitario = Number(prod.preco_venda)
    }
    setItens(novos)
  }

  const removerItem = (i) => setItens(itens.filter((_, idx) => idx !== i))

  const totalItem = (item) => {
    const bruto = (item.quantidade || 0) * (item.preco_unitario || 0)
    const desc = bruto * ((item.desconto_percentual || 0) / 100)
    return bruto - desc
  }
  const totalGeral = itens.reduce((acc, item) => acc + totalItem(item), 0)

  const salvar = useMutation({
    mutationFn: (dados) => vendasApi.create(dados),
    onSuccess: () => {
      toast.success('Pedido criado!')
      qc.invalidateQueries({ queryKey: ['vendas'] })
      onClose()
    },
    onError: (err) => toast.error(err.response?.data?.message || 'Erro ao criar pedido.'),
  })

  const onSubmit = () => {
    if (!clienteId) return toast.error('Selecione um cliente.')
    if (itens.length === 0) return toast.error('Adicione ao menos um item.')
    if (itens.some((i) => !i.produto_id)) return toast.error('Selecione o produto em todos os itens.')

    salvar.mutate({
      tipo,
      cliente_id: Number(clienteId),
      data_pedido: new Date().toISOString().slice(0, 10),
      itens: itens.map((i) => ({
        produto_id: Number(i.produto_id),
        quantidade: Number(i.quantidade),
        preco_unitario: Number(i.preco_unitario),
        desconto_percentual: Number(i.desconto_percentual || 0),
      })),
    })
  }

  return (
    <Modal open={open} onClose={onClose} title="Novo pedido de venda" size="xl">
      <div className="space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormField label="Tipo" required>
            <Select value={tipo} onChange={(e) => setTipo(e.target.value)}>
              <option value="pedido">Pedido</option>
              <option value="orcamento">Orçamento</option>
            </Select>
          </FormField>
          <FormField label="Cliente" required>
            <Select value={clienteId} onChange={(e) => setClienteId(e.target.value)}>
              <option value="">Selecione...</option>
              {(clientes?.data || []).map((c) => (
                <option key={c.id} value={c.id}>{c.nome}</option>
              ))}
            </Select>
          </FormField>
        </div>

        {/* Itens */}
        <div>
          <div className="flex items-center justify-between mb-2">
            <label className="label mb-0">Itens do pedido</label>
            <Button type="button" variant="secondary" onClick={adicionarItem} className="text-sm py-1">
              <Plus size={16} /> Adicionar item
            </Button>
          </div>

          {itens.length === 0 ? (
            <p className="text-sm text-slate-400 text-center py-6 border border-dashed border-slate-300 dark:border-slate-700 rounded-lg">
              Nenhum item. Clique em "Adicionar item".
            </p>
          ) : (
            <div className="space-y-2">
              {itens.map((item, i) => (
                <div key={i} className="grid grid-cols-12 gap-2 items-end bg-slate-50 dark:bg-slate-900/50 p-2 rounded-lg">
                  <div className="col-span-5">
                    <Select value={item.produto_id} onChange={(e) => atualizarItem(i, 'produto_id', e.target.value)}>
                      <option value="">Produto...</option>
                      {(produtos?.data || []).map((p) => (
                        <option key={p.id} value={p.id}>{p.descricao}</option>
                      ))}
                    </Select>
                  </div>
                  <div className="col-span-2">
                    <Input type="number" step="0.01" placeholder="Qtd" value={item.quantidade}
                      onChange={(e) => atualizarItem(i, 'quantidade', e.target.value)} />
                  </div>
                  <div className="col-span-2">
                    <Input type="number" step="0.01" placeholder="Preço" value={item.preco_unitario}
                      onChange={(e) => atualizarItem(i, 'preco_unitario', e.target.value)} />
                  </div>
                  <div className="col-span-2 text-right text-sm font-medium pb-2">
                    {formatMoeda(totalItem(item))}
                  </div>
                  <div className="col-span-1 flex justify-end pb-1">
                    <button type="button" onClick={() => removerItem(i)} className="p-1.5 text-red-500 hover:bg-red-50 rounded">
                      <Trash2 size={16} />
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Total */}
        <div className="flex justify-end items-center gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
          <span className="text-slate-500">Total do pedido:</span>
          <span className="text-xl font-semibold">{formatMoeda(totalGeral)}</span>
        </div>

        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button>
          <Button type="button" onClick={onSubmit} disabled={salvar.isPending}>
            {salvar.isPending ? 'Salvando...' : 'Criar pedido'}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
