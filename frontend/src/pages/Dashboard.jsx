import { useQuery } from '@tanstack/react-query'
import {
  DollarSign, TrendingUp, TrendingDown, ShoppingCart,
  Package, AlertTriangle, FileText,
} from 'lucide-react'
import {
  BarChart, Bar, LineChart, Line, XAxis, YAxis,
  CartesianGrid, Tooltip, ResponsiveContainer,
} from 'recharts'
import { dashboardApi } from '@/api/dashboard'
import { MetricCard, Card } from '@/components/ui/Card'
import { Spinner } from '@/components/ui/Spinner'
import { formatMoeda } from '@/lib/utils'

function inicioDoMes() {
  const d = new Date()
  return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10)
}
function fimDoMes() {
  const d = new Date()
  return new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().slice(0, 10)
}

export default function Dashboard() {
  const { data, isLoading, error } = useQuery({
    queryKey: ['dashboard', inicioDoMes(), fimDoMes()],
    queryFn: async () => {
      const res = await dashboardApi.kpis(inicioDoMes(), fimDoMes())
      return res.data.data
    },
  })

  if (isLoading) return <Spinner size={40} />
  if (error) return <p className="text-red-500">Erro ao carregar dashboard.</p>

  const fin = data?.financeiro || {}
  const vendas = data?.vendas || {}
  const estoque = data?.estoque || {}
  const fiscal = data?.fiscal || {}

  const topProdutos = (vendas.top_produtos || []).map((p) => ({
    nome: p.descricao?.slice(0, 20) || '—',
    valor: Number(p.total_valor) || 0,
  }))

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">Dashboard</h1>
        <p className="text-slate-500 dark:text-slate-400 text-sm">Visão geral do mês atual</p>
      </div>

      {/* KPIs financeiros */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <MetricCard label="Recebido no mês" value={formatMoeda(fin.total_recebido)} icon={TrendingUp} color="green" />
        <MetricCard label="Pago no mês" value={formatMoeda(fin.total_pago)} icon={TrendingDown} color="red" />
        <MetricCard label="A receber" value={formatMoeda(fin.a_receber)} icon={DollarSign} color="brand" />
        <MetricCard label="A pagar" value={formatMoeda(fin.a_pagar)} icon={DollarSign} color="amber" />
      </div>

      {/* KPIs operacionais */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <MetricCard label="Faturado (vendas)" value={formatMoeda(vendas.total_faturado)} icon={ShoppingCart} color="brand" />
        <MetricCard label="Ticket médio" value={formatMoeda(vendas.ticket_medio)} icon={TrendingUp} color="green" />
        <MetricCard label="Valor em estoque" value={formatMoeda(estoque.valor_total_estoque)} icon={Package} color="brand" />
        <MetricCard
          label="Produtos abaixo do mínimo"
          value={estoque.produtos_abaixo_minimo || 0}
          icon={AlertTriangle}
          color={estoque.produtos_abaixo_minimo > 0 ? 'red' : 'green'}
        />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Top produtos */}
        <Card>
          <h3 className="font-medium mb-4">Produtos mais vendidos</h3>
          {topProdutos.length === 0 ? (
            <p className="text-sm text-slate-400 py-8 text-center">Nenhuma venda no período.</p>
          ) : (
            <ResponsiveContainer width="100%" height={260}>
              <BarChart data={topProdutos} layout="vertical" margin={{ left: 20 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                <XAxis type="number" tick={{ fontSize: 12 }} />
                <YAxis type="category" dataKey="nome" tick={{ fontSize: 11 }} width={120} />
                <Tooltip formatter={(v) => formatMoeda(v)} />
                <Bar dataKey="valor" fill="#2563eb" radius={[0, 4, 4, 0]} />
              </BarChart>
            </ResponsiveContainer>
          )}
        </Card>

        {/* Resumo fiscal */}
        <Card>
          <h3 className="font-medium mb-4">Documentos fiscais do mês</h3>
          <div className="space-y-4">
            <div className="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-700">
              <div className="flex items-center gap-2">
                <FileText size={18} className="text-brand-600" />
                <span className="text-sm">NF-e emitidas</span>
              </div>
              <div className="text-right">
                <p className="font-semibold">{fiscal.nfe?.total_emitidas || 0}</p>
                <p className="text-xs text-slate-400">{formatMoeda(fiscal.nfe?.valor_total)}</p>
              </div>
            </div>
            <div className="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-700">
              <div className="flex items-center gap-2">
                <FileText size={18} className="text-purple-600" />
                <span className="text-sm">CT-e emitidos</span>
              </div>
              <div className="text-right">
                <p className="font-semibold">{fiscal.cte?.total_emitidos || 0}</p>
                <p className="text-xs text-slate-400">{formatMoeda(fiscal.cte?.valor_total)}</p>
              </div>
            </div>
            <div className="flex items-center justify-between py-2">
              <span className="text-sm text-slate-500">Documentos pendentes</span>
              <span className="font-semibold">{(fiscal.nfe?.pendentes || 0) + (fiscal.cte?.pendentes || 0)}</span>
            </div>
          </div>
        </Card>
      </div>
    </div>
  )
}
