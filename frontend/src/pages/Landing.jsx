import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import {
  FileText, Wallet, Package, Truck, Wrench, ShieldCheck,
  ArrowRight, CheckCircle2,
} from 'lucide-react'
import api from '@/api/client'
import { formatMoeda } from '@/lib/utils'

/**
 * Landing page pública de vendas.
 * Foco: empresas brasileiras que sofrem com burocracia fiscal.
 * Converte visitante em trial de 14 dias.
 */
export default function Landing() {
  const { data } = useQuery({
    queryKey: ['planos-landing'],
    queryFn: async () => (await api.get('/planos')).data,
    retry: false,
  })
  const planos = data?.data || []
  const planoDestaque = planos.find((p) => p.destaque) || planos[1] || planos[0]

  return (
    <div className="min-h-screen bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100">
      {/* Navbar */}
      <header className="sticky top-0 z-40 backdrop-blur bg-white/80 dark:bg-slate-950/80 border-b border-slate-100 dark:border-slate-800">
        <div className="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
          <span className="text-lg font-bold text-emerald-700 dark:text-emerald-400">
            NotaGestão
          </span>
          <nav className="flex items-center gap-6 text-sm">
            <a href="#recursos" className="hidden sm:inline hover:text-emerald-700">Recursos</a>
            <Link to="/planos" className="hidden sm:inline hover:text-emerald-700">Preços</Link>
            <Link to="/login" className="hover:text-emerald-700">Entrar</Link>
            <Link to="/registrar"
              className="bg-emerald-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-emerald-700 transition-colors">
              Teste grátis
            </Link>
          </nav>
        </div>
      </header>

      {/* Hero — foco na dor fiscal */}
      <section className="max-w-6xl mx-auto px-4 pt-20 pb-16">
        <div className="max-w-3xl">
          <p className="text-emerald-700 dark:text-emerald-400 font-medium mb-4">
            Gestão e emissão fiscal para empresas brasileiras
          </p>
          <h1 className="text-4xl sm:text-5xl font-bold leading-tight tracking-tight">
            Emita nota fiscal sem virar refém da burocracia.
          </h1>
          <p className="text-lg text-slate-600 dark:text-slate-400 mt-5 leading-relaxed">
            NF-e, NFC-e, CT-e e NFS-e num só lugar — com controle de estoque,
            financeiro e vendas. Feito para quem quer vender, não para quem
            gosta de preencher formulário do fisco.
          </p>

          <div className="flex flex-col sm:flex-row gap-3 mt-8">
            <Link to="/registrar"
              className="inline-flex items-center justify-center gap-2 bg-emerald-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-emerald-700 transition-colors">
              Começar 14 dias grátis <ArrowRight size={18} />
            </Link>
            <Link to="/planos"
              className="inline-flex items-center justify-center gap-2 border border-slate-300 dark:border-slate-700 px-6 py-3 rounded-lg font-medium hover:bg-slate-50 dark:hover:bg-slate-900 transition-colors">
              Ver planos e preços
            </Link>
          </div>

          <p className="text-sm text-slate-500 mt-4 flex items-center gap-2">
            <CheckCircle2 size={16} className="text-emerald-600" />
            Sem cartão de crédito. Configure e emita no mesmo dia.
          </p>
        </div>
      </section>

      {/* Faixa de credibilidade */}
      <section className="border-y border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50">
        <div className="max-w-6xl mx-auto px-4 py-8 grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
          <Metrica valor="4" label="tipos de nota fiscal" />
          <Metrica valor="100%" label="na nuvem, multiempresa" />
          <Metrica valor="14 dias" label="grátis para testar" />
          <Metrica valor="PIX/boleto" label="pagamento facilitado" />
        </div>
      </section>

      {/* Recursos — o que resolve */}
      <section id="recursos" className="max-w-6xl mx-auto px-4 py-20">
        <h2 className="text-3xl font-bold max-w-2xl">
          Tudo o que a sua empresa precisa para operar em dia com o fisco.
        </h2>
        <p className="text-slate-600 dark:text-slate-400 mt-3 max-w-2xl">
          Cada módulo conversa com os outros: vendeu, baixa o estoque, gera a
          nota e lança no financeiro. Sem retrabalho, sem planilha paralela.
        </p>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-12">
          <Recurso icon={FileText} titulo="Emissão fiscal completa"
            texto="NF-e, NFC-e, CT-e e NFS-e com validação e envio à SEFAZ. Certificado digital seguro." />
          <Recurso icon={Wallet} titulo="Financeiro no controle"
            texto="Contas a pagar e receber, fluxo de caixa, conciliação e baixa de parcelas." />
          <Recurso icon={Package} titulo="Estoque em tempo real"
            texto="Custo médio, alertas de mínimo e movimentação automática a cada venda." />
          <Recurso icon={Truck} titulo="Transporte e CT-e"
            texto="Conhecimento de transporte rodoviário com todos os tipos e CIOT." />
          <Recurso icon={Wrench} titulo="Ordem de serviço"
            texto="Configurável para o seu ramo, com peças, mão de obra e garantia." />
          <Recurso icon={ShieldCheck} titulo="Multiempresa e seguro"
            texto="Cada empresa com seus dados isolados. Certificado criptografado." />
        </div>
      </section>

      {/* Como funciona — sequência real (numeração justificada) */}
      <section className="bg-slate-50 dark:bg-slate-900/50 border-y border-slate-100 dark:border-slate-800">
        <div className="max-w-6xl mx-auto px-4 py-20">
          <h2 className="text-3xl font-bold">Do cadastro à primeira nota em 3 passos.</h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 mt-12">
            <Passo n="1" titulo="Crie sua conta"
              texto="Cadastro em minutos com seu subdomínio próprio. 14 dias grátis, sem cartão." />
            <Passo n="2" titulo="Configure a empresa"
              texto="Dados fiscais e certificado digital. Nós validamos tudo antes de emitir." />
            <Passo n="3" titulo="Comece a emitir"
              texto="Emita sua primeira nota e acompanhe estoque e financeiro no painel." />
          </div>
        </div>
      </section>

      {/* Plano em destaque */}
      {planoDestaque && (
        <section className="max-w-6xl mx-auto px-4 py-20 text-center">
          <h2 className="text-3xl font-bold">Preço simples, sem surpresa.</h2>
          <p className="text-slate-600 dark:text-slate-400 mt-3">
            Escolha o plano que cabe no seu momento. Mude quando quiser.
          </p>
          <div className="inline-block mt-8 rounded-2xl border-2 border-emerald-500 p-8 text-left max-w-sm w-full">
            <p className="text-sm text-emerald-700 dark:text-emerald-400 font-medium">Mais popular</p>
            <h3 className="text-xl font-bold mt-1">{planoDestaque.nome}</h3>
            <p className="text-4xl font-bold mt-3">
              {formatMoeda(planoDestaque.preco_mensal)}
              <span className="text-base font-normal text-slate-500">/mês</span>
            </p>
            <Link to={`/registrar?plano=${planoDestaque.slug}`}
              className="block text-center mt-6 bg-emerald-600 text-white py-3 rounded-lg font-medium hover:bg-emerald-700 transition-colors">
              Testar grátis por 14 dias
            </Link>
            <Link to="/planos" className="block text-center mt-3 text-sm text-emerald-700 hover:underline">
              Comparar todos os planos
            </Link>
          </div>
        </section>
      )}

      {/* CTA final */}
      <section className="bg-emerald-600 text-white">
        <div className="max-w-6xl mx-auto px-4 py-16 text-center">
          <h2 className="text-3xl font-bold">Pronto para largar a planilha?</h2>
          <p className="mt-3 text-emerald-50 max-w-xl mx-auto">
            Junte-se às empresas que emitem nota, controlam o caixa e crescem
            sem susto com o fisco.
          </p>
          <Link to="/registrar"
            className="inline-flex items-center gap-2 bg-white text-emerald-700 px-8 py-3 rounded-lg font-medium mt-8 hover:bg-emerald-50 transition-colors">
            Criar minha conta grátis <ArrowRight size={18} />
          </Link>
        </div>
      </section>

      {/* Rodapé */}
      <footer className="border-t border-slate-100 dark:border-slate-800">
        <div className="max-w-6xl mx-auto px-4 py-10 flex flex-col sm:flex-row justify-between gap-4 text-sm text-slate-500">
          <span className="font-bold text-emerald-700 dark:text-emerald-400">NotaGestão</span>
          <div className="flex gap-6">
            <Link to="/planos" className="hover:text-emerald-700">Preços</Link>
            <Link to="/login" className="hover:text-emerald-700">Entrar</Link>
            <a href="#recursos" className="hover:text-emerald-700">Recursos</a>
          </div>
          <span>© {new Date().getFullYear()} NotaGestão. Todos os direitos reservados.</span>
        </div>
      </footer>
    </div>
  )
}

function Metrica({ valor, label }) {
  return (
    <div>
      <p className="text-2xl font-bold text-emerald-700 dark:text-emerald-400">{valor}</p>
      <p className="text-sm text-slate-500 mt-1">{label}</p>
    </div>
  )
}

function Recurso({ icon: Icon, titulo, texto }) {
  return (
    <div className="p-6 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-emerald-300 dark:hover:border-emerald-700 transition-colors">
      <div className="w-11 h-11 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-700 dark:text-emerald-400">
        <Icon size={22} />
      </div>
      <h3 className="font-semibold mt-4">{titulo}</h3>
      <p className="text-sm text-slate-600 dark:text-slate-400 mt-2 leading-relaxed">{texto}</p>
    </div>
  )
}

function Passo({ n, titulo, texto }) {
  return (
    <div>
      <div className="w-10 h-10 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold">
        {n}
      </div>
      <h3 className="font-semibold mt-4">{titulo}</h3>
      <p className="text-sm text-slate-600 dark:text-slate-400 mt-2 leading-relaxed">{texto}</p>
    </div>
  )
}
