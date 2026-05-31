import { useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import toast from 'react-hot-toast'
import { Check, X, Loader2 } from 'lucide-react'
import { onboardingApi } from '@/api/auth'
import { Button } from '@/components/ui/Button'

export default function Registrar() {
  const [carregando, setCarregando] = useState(false)
  const [subStatus, setSubStatus] = useState(null) // null | 'checking' | 'ok' | 'taken'
  const navigate = useNavigate()
  const { register, handleSubmit, watch, setValue, formState: { errors } } = useForm()

  const subdominio = watch('subdominio')

  const verificarSubdominio = async (valor) => {
    if (!valor || valor.length < 3) { setSubStatus(null); return }
    setSubStatus('checking')
    try {
      const { data } = await onboardingApi.verificarSubdominio(valor)
      setValue('subdominio', data.subdominio)
      setSubStatus(data.disponivel ? 'ok' : 'taken')
    } catch {
      setSubStatus(null)
    }
  }

  const onSubmit = async (dados) => {
    if (subStatus === 'taken') {
      toast.error('Escolha outro subdomínio.')
      return
    }
    setCarregando(true)
    try {
      const { data } = await onboardingApi.registrar(dados)
      toast.success(data.message, { duration: 6000 })
      navigate('/login')
    } catch (err) {
      const msg = err.response?.data?.message || 'Erro ao criar conta.'
      toast.error(msg)
    } finally {
      setCarregando(false)
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-50 dark:bg-slate-900 px-4 py-8">
      <div className="w-full max-w-lg">
        <div className="text-center mb-6">
          <h1 className="text-2xl font-bold text-brand-600">Comece grátis por 14 dias</h1>
          <p className="text-slate-500 dark:text-slate-400 mt-1">Sem cartão de crédito</p>
        </div>

        <form onSubmit={handleSubmit(onSubmit)} className="card space-y-4">
          <div>
            <label className="label">Razão social da empresa</label>
            <input className="input" {...register('razao_social', { required: 'Campo obrigatório' })} />
            {errors.razao_social && <p className="text-xs text-red-500 mt-1">{errors.razao_social.message}</p>}
          </div>

          <div>
            <label className="label">CNPJ (opcional)</label>
            <input className="input" placeholder="00.000.000/0000-00" {...register('cnpj')} />
          </div>

          <div>
            <label className="label">Endereço do seu sistema</label>
            <div className="flex items-center">
              <input
                className="input rounded-r-none"
                placeholder="minhaempresa"
                {...register('subdominio', { required: 'Campo obrigatório' })}
                onBlur={(e) => verificarSubdominio(e.target.value)}
              />
              <span className="px-3 py-2 bg-slate-100 dark:bg-slate-700 border border-l-0 border-slate-300 dark:border-slate-600 rounded-r-lg text-sm text-slate-500">
                .erpsaas.com.br
              </span>
            </div>
            {subStatus === 'checking' && (
              <p className="text-xs text-slate-400 mt-1 flex items-center gap-1">
                <Loader2 size={12} className="animate-spin" /> Verificando...
              </p>
            )}
            {subStatus === 'ok' && (
              <p className="text-xs text-green-600 mt-1 flex items-center gap-1">
                <Check size={12} /> Disponível!
              </p>
            )}
            {subStatus === 'taken' && (
              <p className="text-xs text-red-500 mt-1 flex items-center gap-1">
                <X size={12} /> Já está em uso. Escolha outro.
              </p>
            )}
          </div>

          <hr className="border-slate-200 dark:border-slate-700" />

          <div>
            <label className="label">Seu nome</label>
            <input className="input" {...register('nome_responsavel', { required: 'Campo obrigatório' })} />
            {errors.nome_responsavel && <p className="text-xs text-red-500 mt-1">{errors.nome_responsavel.message}</p>}
          </div>

          <div>
            <label className="label">E-mail</label>
            <input type="email" className="input" {...register('email', { required: 'Campo obrigatório' })} />
            {errors.email && <p className="text-xs text-red-500 mt-1">{errors.email.message}</p>}
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="label">Senha</label>
              <input type="password" className="input" {...register('senha', { required: 'Campo obrigatório', minLength: { value: 8, message: 'Mínimo 8 caracteres' } })} />
              {errors.senha && <p className="text-xs text-red-500 mt-1">{errors.senha.message}</p>}
            </div>
            <div>
              <label className="label">Confirmar senha</label>
              <input type="password" className="input" {...register('senha_confirmation', { required: 'Confirme a senha' })} />
              {errors.senha_confirmation && <p className="text-xs text-red-500 mt-1">{errors.senha_confirmation.message}</p>}
            </div>
          </div>

          <Button type="submit" disabled={carregando} className="w-full">
            {carregando ? 'Criando sua conta...' : 'Criar conta grátis'}
          </Button>
        </form>

        <p className="text-center text-sm text-slate-500 mt-6">
          Já tem conta?{' '}
          <Link to="/login" className="text-brand-600 font-medium hover:underline">Fazer login</Link>
        </p>
      </div>
    </div>
  )
}
