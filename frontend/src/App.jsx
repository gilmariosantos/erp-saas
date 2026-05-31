import { useEffect } from 'react'
import { Routes, Route, Navigate, useLocation } from 'react-router-dom'
import { useAuthStore } from '@/contexts/authStore'
import { AppLayout } from '@/components/layout/AppLayout'
import { Spinner } from '@/components/ui/Spinner'

import Login from '@/pages/auth/Login'
import Registrar from '@/pages/auth/Registrar'
import Dashboard from '@/pages/Dashboard'
import Pessoas from '@/pages/cadastros/Pessoas'
import Produtos from '@/pages/cadastros/Produtos'
import Financeiro from '@/pages/financeiro/Financeiro'
import Estoque from '@/pages/estoque/Estoque'
import Vendas from '@/pages/vendas/Vendas'
import NotasFiscais from '@/pages/fiscal/NotasFiscais'
import Ctes from '@/pages/fiscal/Ctes'

/**
 * Protege rotas que exigem autenticação.
 * Redireciona ao login se não houver usuário.
 */
function RotaProtegida({ children }) {
  const { user, loading } = useAuthStore()
  const location = useLocation()

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Spinner size={40} />
      </div>
    )
  }

  if (!user) {
    return <Navigate to="/login" state={{ from: location }} replace />
  }

  return <AppLayout>{children}</AppLayout>
}

export default function App() {
  const { carregarUsuario, user } = useAuthStore()

  useEffect(() => {
    carregarUsuario()
  }, [carregarUsuario])

  return (
    <Routes>
      {/* Rotas públicas */}
      <Route path="/login" element={user ? <Navigate to="/" replace /> : <Login />} />
      <Route path="/registrar" element={user ? <Navigate to="/" replace /> : <Registrar />} />

      {/* Rotas protegidas */}
      <Route path="/" element={<RotaProtegida><Dashboard /></RotaProtegida>} />
      <Route path="/pessoas" element={<RotaProtegida><Pessoas /></RotaProtegida>} />
      <Route path="/produtos" element={<RotaProtegida><Produtos /></RotaProtegida>} />
      <Route path="/financeiro" element={<RotaProtegida><Financeiro /></RotaProtegida>} />
      <Route path="/estoque" element={<RotaProtegida><Estoque /></RotaProtegida>} />
      <Route path="/vendas" element={<RotaProtegida><Vendas /></RotaProtegida>} />
      <Route path="/fiscal/nfe" element={<RotaProtegida><NotasFiscais /></RotaProtegida>} />
      <Route path="/fiscal/cte" element={<RotaProtegida><Ctes /></RotaProtegida>} />

      {/* Fallback */}
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
