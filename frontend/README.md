# ERP SaaS — Frontend (React SPA)

Interface web do ERP SaaS, construída em React + Vite, consumindo a API REST do backend Laravel.

## Stack
- React 18 + Vite 5
- React Router 6 (roteamento)
- TanStack Query (cache e estado de servidor)
- Zustand (estado de autenticação)
- React Hook Form + Zod (formulários)
- Tailwind CSS (estilização)
- Recharts (gráficos)
- Axios (cliente HTTP)

## Instalação

```bash
cd frontend
npm install
cp .env.example .env   # ajuste VITE_API_URL
npm run dev            # http://localhost:3000
```

## Build de produção

```bash
npm run build          # gera dist/
```

## Estrutura

```
src/
├── api/          # Cliente HTTP e módulos por domínio
├── components/
│   ├── ui/       # Button, Card, Table, Badge, Spinner
│   └── layout/   # AppLayout (sidebar + header)
├── contexts/     # authStore (Zustand)
├── lib/          # Utilitários (formatação BRL, datas)
├── pages/
│   ├── auth/     # Login, Registrar (com 2FA)
│   ├── cadastros/# Pessoas, Produtos
│   ├── financeiro/
│   ├── estoque/
│   ├── vendas/
│   └── fiscal/   # NotasFiscais, Ctes
└── App.jsx       # Roteamento + proteção de rotas
```

## Funcionalidades implementadas

- ✅ Login com 2FA (TOTP)
- ✅ Auto-registro de empresa (onboarding)
- ✅ Dashboard com KPIs reais e gráficos
- ✅ Listagem de Clientes/Fornecedores
- ✅ Listagem de Produtos com alerta de estoque
- ✅ Financeiro (contas a pagar/receber)
- ✅ Estoque (posição + alertas)
- ✅ Vendas (pedidos e orçamentos)
- ✅ NF-e (listar + emitir + baixar XML)
- ✅ CT-e / CIOT
- ✅ Controle de permissões no menu (RBAC)
- ✅ Dark mode
- ✅ Responsivo (mobile + desktop)

## A implementar (próximas iterações)
- Formulários de criação/edição (modais)
- Telas de detalhe de cada registro
- Filtros avançados e exportação
- Conciliação bancária (OFX)
- Configurações da empresa e certificado digital
