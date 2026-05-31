import api from './client'

export const authApi = {
  login: (email, password) => api.post('/auth/login', { email, password }),
  logout: () => api.post('/auth/logout'),
  me: () => api.get('/auth/me'),
  validar2fa: (codigo) => api.post('/auth/2fa/validar', { codigo }),
  esqueciSenha: (email) => api.post('/auth/esqueci-senha', { email }),
  alterarSenha: (data) => api.post('/auth/alterar-senha', data),
}

export const onboardingApi = {
  registrar: (data) => api.post('/onboarding/registrar', data),
  verificarSubdominio: (sub) => api.get(`/onboarding/verificar-subdominio/${sub}`),
}
