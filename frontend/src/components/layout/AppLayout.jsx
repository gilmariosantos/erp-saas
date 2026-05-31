import { useState } from 'react'
import { NavLink, useNavigate } from 'react-router-dom'
import {
  LayoutDashboard, Users, Package, DollarSign, Warehouse,
  ShoppingCart, FileText, Truck, LogOut, Menu, X, Moon, Sun,
} from 'lucide-react'
import { useAuthStore } from '@/contexts/authStore'

const menuItems = [
  { to: '/', icon: LayoutDashboard, label: 'Dashboard', permission: 'dashboard.ver' },
  { to: '/pessoas', icon: Users, label: 'Clientes / Fornecedores', permission: 'pessoas.ver' },
  { to: '/produtos', icon: Package, label: 'Produtos', permission: 'produtos.ver' },
  { to: '/financeiro', icon: DollarSign, label: 'Financeiro', permission: 'financeiro.ver' },
  { to: '/estoque', icon: Warehouse, label: 'Estoque', permission: 'estoque.ver' },
  { to: '/vendas', icon: ShoppingCart, label: 'Vendas', permission: 'vendas.ver' },
  { to: '/fiscal/nfe', icon: FileText, label: 'Notas Fiscais', permission: 'nfe.ver' },
  { to: '/fiscal/cte', icon: Truck, label: 'CT-e / CIOT', permission: 'cte.ver' },
]

export function AppLayout({ children }) {
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [dark, setDark] = useState(document.documentElement.classList.contains('dark'))
  const { user, logout, can } = useAuthStore()
  const navigate = useNavigate()

  const handleLogout = async () => {
    await logout()
    navigate('/login')
  }

  const toggleDark = () => {
    document.documentElement.classList.toggle('dark')
    setDark(!dark)
  }

  const itensVisiveis = menuItems.filter((item) => can(item.permission))

  return (
    <div className="min-h-screen flex">
      {/* Sidebar */}
      <aside
        className={`fixed lg:static inset-y-0 left-0 z-40 w-64 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 transform transition-transform lg:translate-x-0 ${
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="h-16 flex items-center px-6 border-b border-slate-200 dark:border-slate-800">
          <span className="text-lg font-semibold text-brand-600">ERP SaaS</span>
        </div>

        <nav className="p-3 space-y-1">
          {itensVisiveis.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              onClick={() => setSidebarOpen(false)}
              className={({ isActive }) =>
                `flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
                  isActive
                    ? 'bg-brand-50 text-brand-700 dark:bg-brand-900/20 dark:text-brand-400'
                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                }`
              }
            >
              <item.icon size={18} />
              {item.label}
            </NavLink>
          ))}
        </nav>
      </aside>

      {/* Overlay mobile */}
      {sidebarOpen && (
        <div className="fixed inset-0 bg-black/30 z-30 lg:hidden" onClick={() => setSidebarOpen(false)} />
      )}

      {/* Conteúdo */}
      <div className="flex-1 flex flex-col min-w-0">
        <header className="h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-4 lg:px-6">
          <button className="lg:hidden" onClick={() => setSidebarOpen(true)}>
            <Menu size={22} />
          </button>

          <div className="flex items-center gap-3 ml-auto">
            <button onClick={toggleDark} className="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800">
              {dark ? <Sun size={18} /> : <Moon size={18} />}
            </button>
            <div className="flex items-center gap-3 pl-3 border-l border-slate-200 dark:border-slate-700">
              <div className="text-right hidden sm:block">
                <p className="text-sm font-medium">{user?.name}</p>
                <p className="text-xs text-slate-400">{user?.empresa_ativa?.razao_social}</p>
              </div>
              <button onClick={handleLogout} className="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500" title="Sair">
                <LogOut size={18} />
              </button>
            </div>
          </div>
        </header>

        <main className="flex-1 p-4 lg:p-6 overflow-auto">{children}</main>
      </div>
    </div>
  )
}
