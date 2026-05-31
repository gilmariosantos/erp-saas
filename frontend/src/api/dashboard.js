import api from './client'

export const dashboardApi = {
  kpis: (dataInicio, dataFim) =>
    api.get('/v1/dashboard', { params: { data_inicio: dataInicio, data_fim: dataFim } }),
  dre: (dataInicio, dataFim) =>
    api.get('/v1/dashboard/dre', { params: { data_inicio: dataInicio, data_fim: dataFim } }),
  saldos: () => api.get('/v1/dashboard/saldos'),
}
