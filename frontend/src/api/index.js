import api from './client'

const crud = (resource) => ({
  list: (params) => api.get(`/v1/${resource}`, { params }),
  get: (id) => api.get(`/v1/${resource}/${id}`),
  create: (data) => api.post(`/v1/${resource}`, data),
  update: (id, data) => api.put(`/v1/${resource}/${id}`, data),
  remove: (id) => api.delete(`/v1/${resource}/${id}`),
})

export const pessoasApi = crud('pessoas')
export const produtosApi = crud('produtos')

export const financeiroApi = {
  ...crud('financeiro/lancamentos'),
  baixar: (id, data) => api.post(`/v1/financeiro/lancamentos/${id}/baixar`, data),
  fluxoCaixa: (params) => api.get('/v1/financeiro/fluxo-caixa', { params }),
}

export const estoqueApi = {
  posicao: () => api.get('/v1/estoque/posicao'),
  alertas: () => api.get('/v1/estoque/alertas'),
  movimentar: (data) => api.post('/v1/estoque/movimentar', data),
  pedidosCompra: crud('estoque/pedidos-compra'),
}

export const vendasApi = {
  ...crud('vendas/pedidos'),
  aprovar: (id) => api.post(`/v1/vendas/pedidos/${id}/aprovar`),
  faturar: (id, data) => api.post(`/v1/vendas/pedidos/${id}/faturar`, data),
  cancelar: (id, data) => api.post(`/v1/vendas/pedidos/${id}/cancelar`, data),
  resumo: (params) => api.get('/v1/vendas/resumo', { params }),
}

export const fiscalApi = {
  nfes: {
    ...crud('fiscal/nfes'),
    emitir: (id) => api.post(`/v1/fiscal/nfes/${id}/emitir`),
    cancelar: (id, data) => api.post(`/v1/fiscal/nfes/${id}/cancelar`, data),
    downloadXml: (id) => api.get(`/v1/fiscal/nfes/${id}/xml`),
    downloadPdf: (id) => api.get(`/v1/fiscal/nfes/${id}/pdf`),
  },
  ctes: {
    ...crud('fiscal/ctes'),
    emitir: (id) => api.post(`/v1/fiscal/ctes/${id}/emitir`),
    gerarCiot: (data) => api.post('/v1/fiscal/ciot/gerar', data),
  },
}
