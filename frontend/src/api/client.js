import axios from 'axios'
import toast from 'react-hot-toast'

const API_URL = import.meta.env.VITE_API_URL || '/api'

/**
 * Cliente HTTP central. Adiciona o token automaticamente,
 * trata erros 401 (sessão expirada) e 402 (assinatura inativa).
 */
const api = axios.create({
  baseURL: API_URL,
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
  timeout: 30000,
})

// ─── Request: injeta o token ──────────────────────────────────────────────
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('erp_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

// ─── Response: trata erros globais ─────────────────────────────────────────
api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status
    const message = error.response?.data?.message

    if (status === 401) {
      // Sessão expirada — limpa e redireciona ao login
      localStorage.removeItem('erp_token')
      if (!window.location.pathname.includes('/login')) {
        window.location.href = '/login'
      }
    } else if (status === 402) {
      // Assinatura inativa
      toast.error('Sua assinatura está inativa. Regularize para continuar.')
      window.location.href = '/assinatura'
    } else if (status === 403) {
      toast.error(message || 'Você não tem permissão para esta ação.')
    } else if (status === 422) {
      // Erros de validação — deixa o componente tratar
    } else if (status >= 500) {
      toast.error('Erro no servidor. Tente novamente em instantes.')
    } else if (error.code === 'ECONNABORTED') {
      toast.error('A requisição demorou demais. Verifique sua conexão.')
    }

    return Promise.reject(error)
  }
)

export default api
