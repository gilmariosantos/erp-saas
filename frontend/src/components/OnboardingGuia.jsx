import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import {
  CheckCircle2, Circle, Building2, ShieldCheck,
  Package, Users, FileText, X, ArrowRight,
} from 'lucide-react'
import { Card } from '@/components/ui/Card'

/**
 * Onboarding guiado — checklist dos primeiros passos do cliente novo.
 *
 * O progresso é derivado de dados reais (tem produtos? tem certificado?),
 * passados via prop `status`. Fica dispensável (o usuário pode ocultar).
 *
 * Como não há tabela de "onboarding" no backend, o estado de "dispensado"
 * fica em memória da sessão + é recalculado pelos dados reais a cada visita.
 */
export function OnboardingGuia({ status = {} }) {
  const [oculto, setOculto] = useState(false)

  // Passos derivados de dados reais do tenant
  const passos = [
    {
      id: 'empresa',
      icon: Building2,
      titulo: 'Configure sua empresa',
      texto: 'Dados fiscais, endereço e regime tributário.',
      to: '/configuracoes/empresa',
      concluido: status.empresa_configurada,
    },
    {
      id: 'certificado',
      icon: ShieldCheck,
      titulo: 'Envie seu certificado digital',
      texto: 'Necessário para emitir notas fiscais.',
      to: '/fiscal/certificado',
      concluido: status.certificado_enviado,
    },
    {
      id: 'produtos',
      icon: Package,
      titulo: 'Cadastre seus produtos',
      texto: 'O que você vende ou os serviços que presta.',
      to: '/produtos',
      concluido: status.tem_produtos,
    },
    {
      id: 'clientes',
      icon: Users,
      titulo: 'Cadastre seus clientes',
      texto: 'Para quem você vende e emite notas.',
      to: '/pessoas',
      concluido: status.tem_clientes,
    },
    {
      id: 'primeira_nota',
      icon: FileText,
      titulo: 'Emita sua primeira nota',
      texto: 'Teste em homologação antes de valer para o fisco.',
      to: '/fiscal/nfe',
      concluido: status.tem_nota,
    },
  ]

  const concluidos = passos.filter((p) => p.concluido).length
  const total = passos.length
  const percentual = Math.round((concluidos / total) * 100)

  // Quando tudo estiver concluído, o guia não aparece mais
  useEffect(() => {
    if (concluidos === total) setOculto(true)
  }, [concluidos, total])

  if (oculto || concluidos === total) return null

  return (
    <Card className="border-brand-200 dark:border-brand-800">
      <div className="flex items-start justify-between">
        <div>
          <h3 className="font-semibold">Primeiros passos</h3>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            Configure o essencial para começar a emitir notas.
          </p>
        </div>
        <button onClick={() => setOculto(true)} className="p-1 text-slate-400 hover:text-slate-600" title="Ocultar">
          <X size={18} />
        </button>
      </div>

      {/* Barra de progresso */}
      <div className="mt-4">
        <div className="flex items-center justify-between text-xs text-slate-500 mb-1">
          <span>{concluidos} de {total} concluídos</span>
          <span>{percentual}%</span>
        </div>
        <div className="h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
          <div className="h-full bg-brand-600 rounded-full transition-all duration-500"
            style={{ width: `${percentual}%` }} />
        </div>
      </div>

      {/* Lista de passos */}
      <div className="mt-4 space-y-1">
        {passos.map((passo) => (
          <Link key={passo.id} to={passo.to}
            className={`flex items-center gap-3 p-3 rounded-lg transition-colors ${
              passo.concluido
                ? 'opacity-60'
                : 'hover:bg-slate-50 dark:hover:bg-slate-800/50'
            }`}>
            {passo.concluido
              ? <CheckCircle2 size={20} className="text-green-500 shrink-0" />
              : <Circle size={20} className="text-slate-300 dark:text-slate-600 shrink-0" />}
            <div className="flex-1 min-w-0">
              <p className={`text-sm font-medium ${passo.concluido ? 'line-through' : ''}`}>
                {passo.titulo}
              </p>
              {!passo.concluido && (
                <p className="text-xs text-slate-500 dark:text-slate-400">{passo.texto}</p>
              )}
            </div>
            {!passo.concluido && <ArrowRight size={16} className="text-slate-400 shrink-0" />}
          </Link>
        ))}
      </div>
    </Card>
  )
}
