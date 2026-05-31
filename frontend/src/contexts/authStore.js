import { create } from 'zustand'
import { authApi } from '@/api/auth'

/**
 * Estado global de autenticação.
 * Persiste o token no localStorage e mantém o usuário em memória.
 */
export const useAuthStore = create((set, get) => ({
  user: null,
  token: localStorage.getItem('erp_token'),
  permissions: [],
  roles: [],
  loading: true,

  // Carrega o usuário atual a partir do token salvo
  carregarUsuario: async () => {
    const token = localStorage.getItem('erp_token')
    if (!token) {
      set({ loading: false, user: null })
      return
    }
    try {
      const { data } = await authApi.me()
      set({
        user: data.user,
        permissions: data.user.permissions || [],
        roles: data.user.roles || [],
        loading: false,
      })
    } catch {
      localStorage.removeItem('erp_token')
      set({ user: null, token: null, loading: false })
    }
  },

  // Login com tratamento de 2FA
  login: async (email, password) => {
    const { data } = await authApi.login(email, password)

    if (data.requires_two_factor) {
      // Salva token temporário para a etapa do 2FA
      localStorage.setItem('erp_temp_token', data.temp_token)
      return { requires2fa: true }
    }

    localStorage.setItem('erp_token', data.token)
    set({
      token: data.token,
      user: data.user,
      permissions: data.user.permissions || [],
      roles: data.user.roles || [],
    })
    return { requires2fa: false }
  },

  // Conclui login com código 2FA
  validar2fa: async (codigo) => {
    const tempToken = localStorage.getItem('erp_temp_token')
    // Usa o token temporário para validar
    const { data } = await authApi.validar2fa(codigo)
    localStorage.removeItem('erp_temp_token')
    localStorage.setItem('erp_token', data.token)
    set({ token: data.token })
    await get().carregarUsuario()
  },

  logout: async () => {
    try {
      await authApi.logout()
    } catch {
      // ignora erro de logout
    }
    localStorage.removeItem('erp_token')
    set({ user: null, token: null, permissions: [], roles: [] })
  },

  // Verifica se o usuário tem uma permissão
  can: (permission) => {
    const { permissions, roles } = get()
    if (roles.includes('administrador')) return true
    return permissions.includes(permission)
  },
}))
