import { useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import toast from 'react-hot-toast'
import { LogIn, ShieldCheck } from 'lucide-react'
import { useAuthStore } from '@/contexts/authStore'
import { Button } from '@/components/ui/Button'

export default function Login() {
  const [etapa, setEtapa] = useState('login') // 'login' | '2fa'
  const [carregando, setCarregando] = useState(false)
  const { login, validar2fa } = useAuthStore()
  const navigate = useNavigate()
  const { register, handleSubmit, formState: { errors } } = useForm()

  const onLogin = async (dados) => {
    setCarregando(true)
    try {
      const resultado = await login(dados.email, dados.password)
      if (resultado.requires2fa) {
        setEtapa('2fa')
        toast('Informe o código do seu autenticador', { icon: '🔐' })
      } else {
        toast.success('Bem-vindo!')
        navigate('/')
      }
    } catch (err) {
      const msg = err.response?.data?.errors?.email?.[0]
        || err.response?.data?.message
        || 'Erro ao fazer login.'
      toast.error(msg)
    } finally {
      setCarregando(false)
    }
  }

  const on2fa = async (dados) => {
    setCarregando(true)
    try {
      await validar2fa(dados.codigo)
      toast.success('Autenticado!')
      navigate('/')
    } catch {
      toast.error('Código inválido.')
    } finally {
      setCarregando(false)
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-50 dark:bg-slate-900 px-4">
      <div className="w-full max-w-md">
        <div className="text-center mb-8">
          <h1 className="text-2xl font-bold text-brand-600">ERP SaaS</h1>
          <p className="text-slate-500 dark:text-slate-400 mt-1">Gestão empresarial completa</p>
        </div>

        <div className="card">
          {etapa === 'login' ? (
            <form onSubmit={handleSubmit(onLogin)} className="space-y-4">
              <h2 className="text-lg font-semibold">Entrar na sua conta</h2>

              <div>
                <label className="label">E-mail</label>
                <input
                  type="email"
                  className="input"
                  placeholder="seu@email.com"
                  {...register('email', { required: 'E-mail obrigatório' })}
                />
                {errors.email && <p className="text-xs text-red-500 mt-1">{errors.email.message}</p>}
              </div>

              <div>
                <label className="label">Senha</label>
                <input
                  type="password"
                  className="input"
                  placeholder="••••••••"
                  {...register('password', { required: 'Senha obrigatória' })}
                />
                {errors.password && <p className="text-xs text-red-500 mt-1">{errors.password.message}</p>}
              </div>

              <div className="flex justify-end">
                <Link to="/esqueci-senha" className="text-sm text-brand-600 hover:underline">
                  Esqueci minha senha
                </Link>
              </div>

              <Button type="submit" disabled={carregando} className="w-full">
                <LogIn size={18} />
                {carregando ? 'Entrando...' : 'Entrar'}
              </Button>
            </form>
          ) : (
            <form onSubmit={handleSubmit(on2fa)} className="space-y-4">
              <div className="flex items-center gap-2 text-brand-600">
                <ShieldCheck size={22} />
                <h2 className="text-lg font-semibold">Autenticação de dois fatores</h2>
              </div>
              <p className="text-sm text-slate-500">
                Digite o código de 6 dígitos do seu aplicativo autenticador.
              </p>

              <input
                type="text"
                inputMode="numeric"
                maxLength={6}
                className="input text-center text-2xl tracking-widest"
                placeholder="000000"
                autoFocus
                {...register('codigo', { required: true, minLength: 6 })}
              />

              <Button type="submit" disabled={carregando} className="w-full">
                {carregando ? 'Verificando...' : 'Confirmar'}
              </Button>
            </form>
          )}
        </div>

        <p className="text-center text-sm text-slate-500 mt-6">
          Ainda não tem conta?{' '}
          <Link to="/registrar" className="text-brand-600 font-medium hover:underline">
            Comece o teste grátis
          </Link>
        </p>
      </div>
    </div>
  )
}
