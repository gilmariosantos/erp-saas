import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Check, Loader2 } from 'lucide-react'
import api from '@/api/client'
import { formatMoeda } from '@/lib/utils'

/**
 * Página pública de planos (pricing).
 * Acessível sem login — usada na landing e no fluxo de registro.
 */
export default function Planos() {
  const [ciclo, setCiclo] = useState('mensal')

  const { data, isLoading } = useQuery({
    queryKey: ['planos-publico'],
    queryFn: async () => (await api.get('/planos')).data,
  })

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Loader2 className="animate-spin text-brand-600" size={40} />
      </div>
    )
  }

  const planos = data?.data || []

  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-900 py-12 px-4">
      <div className="max-w-5xl mx-auto">
        {/* Cabeçalho */}
        <div className="text-center mb-10">
          <h1 className="text-3xl font-bold text-slate-900 dark:text-white">
            Planos que crescem com a sua empresa
          </h1>
          <p className="text-slate-500 dark:text-slate-400 mt-2">
            14 dias grátis. Sem cartão de crédito. Cancele quando quiser.
          </p>

          {/* Toggle mensal/anual */}
          <div className="inline-flex items-center gap-1 mt-6 p-1 bg-slate-200 dark:bg-slate-800 rounded-lg">
            <button
              onClick={() => setCiclo('mensal')}
              className={`px-4 py-1.5 rounded-md text-sm font-medium transition-colors ${
                ciclo === 'mensal' ? 'bg-white dark:bg-slate-700 shadow' : 'text-slate-500'
              }`}
            >
              Mensal
            </button>
            <button
              onClick={() => setCiclo('anual')}
              className={`px-4 py-1.5 rounded-md text-sm font-medium transition-colors ${
                ciclo === 'anual' ? 'bg-white dark:bg-slate-700 shadow' : 'text-slate-500'
              }`}
            >
              Anual <span className="text-green-600 text-xs">economize</span>
            </button>
          </div>
        </div>

        {/* Cards de planos */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {planos.map((plano) => (
            <div
              key={plano.id}
              className={`relative bg-white dark:bg-slate-800 rounded-2xl p-6 border-2 transition-transform hover:scale-[1.02] ${
                plano.destaque
                  ? 'border-brand-500 shadow-lg'
                  : 'border-slate-200 dark:border-slate-700'
              }`}
            >
              {plano.destaque && (
                <span className="absolute -top-3 left-1/2 -translate-x-1/2 bg-brand-600 text-white text-xs font-medium px-3 py-1 rounded-full">
                  Mais popular
                </span>
              )}

              <h3 className="text-lg font-semibold">{plano.nome}</h3>
              <p className="text-sm text-slate-500 dark:text-slate-400 mt-1 min-h-[40px]">
                {plano.descricao}
              </p>

              <div className="mt-4">
                <span className="text-3xl font-bold">
                  {formatMoeda(ciclo === 'anual' ? plano.preco_anual / 12 : plano.preco_mensal)}
                </span>
                <span className="text-slate-500 text-sm">/mês</span>
                {ciclo === 'anual' && plano.economia_anual > 0 && (
                  <p className="text-xs text-green-600 mt-1">
                    Economize {plano.economia_anual}% no plano anual
                  </p>
                )}
              </div>

              <Link
                to={`/registrar?plano=${plano.slug}&ciclo=${ciclo}`}
                className={`block text-center mt-5 py-2.5 rounded-lg font-medium text-sm transition-colors ${
                  plano.destaque
                    ? 'bg-brand-600 text-white hover:bg-brand-700'
                    : 'bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600'
                }`}
              >
                Começar teste grátis
              </Link>

              {/* Limites */}
              <div className="mt-6 space-y-2 text-sm">
                <LimiteItem label="Usuários" valor={plano.limites.usuarios} />
                <LimiteItem label="Empresas" valor={plano.limites.empresas} />
                <LimiteItem label="NF-e/mês" valor={plano.limites.nfe_mes} />
                <LimiteItem label="CT-e/mês" valor={plano.limites.cte_mes} />
                <LimiteItem label="Armazenamento" valor={`${plano.limites.storage_gb} GB`} />
              </div>

              {/* Features */}
              {plano.features?.length > 0 && (
                <div className="mt-5 pt-5 border-t border-slate-100 dark:border-slate-700 space-y-2">
                  {plano.features.map((f, i) => (
                    <div key={i} className="flex items-center gap-2 text-sm">
                      <Check size={16} className="text-green-500 shrink-0" />
                      <span className="text-slate-600 dark:text-slate-300">{f}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          ))}
        </div>

        <p className="text-center text-sm text-slate-400 mt-8">
          Já tem conta?{' '}
          <Link to="/login" className="text-brand-600 hover:underline">Fazer login</Link>
        </p>
      </div>
    </div>
  )
}

function LimiteItem({ label, valor }) {
  const exibir = valor >= 9999 ? 'Ilimitado' : valor
  return (
    <div className="flex justify-between">
      <span className="text-slate-500 dark:text-slate-400">{label}</span>
      <span className="font-medium">{exibir}</span>
    </div>
  )
}
