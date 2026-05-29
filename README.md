# ERP SaaS Brasil 🇧🇷

Sistema ERP multi-tenant para empresas brasileiras de todos os portes.
Desenvolvido em **Laravel 11 + MySQL 8** com arquitetura SaaS escalável.

---

## 📦 Módulos implementados

| Módulo | Status | Notas |
|--------|--------|-------|
| Multi-tenancy | ✅ | `stancl/tenancy` — banco isolado por empresa |
| Autenticação | ✅ | Sanctum + Passport + 2FA |
| Cadastros | ✅ | Clientes, fornecedores, produtos, tabelas de preço |
| Financeiro | 🔄 | Contas a pagar/receber, fluxo de caixa |
| Estoque | 🔄 | Entradas, saídas, inventário, lotes |
| Vendas / CRM | 🔄 | Pedidos, orçamentos, pipeline |
| **NF-e** | ✅ | Leiaute 4.00, emissão, cancelamento, CC-e |
| **NFC-e** | ✅ | Leiaute 4.00, contingência offline |
| **NFS-e** | 🔄 | Múltiplos padrões municipais |
| **CT-e** | ✅ | Leiaute 4.00 + CIOT/ANTT |
| **MDF-e** | 🔄 | Leiaute 3.00 |
| Relatórios | 🔄 | Dashboards, KPIs, exportação |

---

## 🚀 Início rápido

### Pré-requisitos
- Docker + Docker Compose
- Git

### Instalação

```bash
# Clone o repositório
git clone https://github.com/gilmariosantos/erp-saas.git
cd erp-saas

# Copie o .env
cp .env.example .env

# Suba os containers
docker-compose up -d

# Instale dependências
docker-compose exec app composer install
docker-compose exec app npm install && npm run build

# Gere a chave da aplicação
docker-compose exec app php artisan key:generate

# Rode as migrations
docker-compose exec app php artisan migrate --seed

# (Opcional) Dados de demonstração
docker-compose exec app php artisan db:seed --class=DemoSeeder
```

### URLs

| Serviço | URL |
|---------|-----|
| Aplicação | http://localhost:8080 |
| phpMyAdmin | http://localhost:8081 |
| Laravel Horizon | http://localhost:8080/horizon |
| Mailpit | http://localhost:8025 |
| MinIO Console | http://localhost:9001 |
| API Docs (Swagger) | http://localhost:8080/docs |

---

## 🏗 Arquitetura

### Multi-tenancy

Cada empresa (tenant) tem **banco de dados isolado** (`tenant_{id}`).
O banco `erp_landlord` armazena apenas: tenants, domínios, planos e assinaturas.

```
erp_landlord      — banco central (tenants, planos, assinaturas)
tenant_empresa-a  — banco da empresa A
tenant_empresa-b  — banco da empresa B
```

### Estrutura de pastas

```
app/
├── Enums/          # NFeStatus, CTeStatus, ...
├── Events/         # NFeAutorizada, CTeEmitido, ...
├── Exceptions/     # FiscalException, SefazException, ...
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/     # API REST versionada
│   │   └── Tenant/     # Controllers web multi-tenant
│   ├── Middleware/     # TenantMiddleware, AuditMiddleware
│   └── Requests/       # Form Requests com validação
├── Jobs/           # EmitirNFe, EmitirCTe, EnviarEmail, ...
├── Models/         # Eloquent com soft deletes e auditoria
├── Observers/      # NfeObserver, CteObserver, ...
├── Policies/       # Autorização por recurso
├── Services/
│   ├── Fiscal/     # NFeService, CTeService, SefazService, ...
│   ├── Financeiro/ # ContaService, FluxoCaixaService, ...
│   └── Tenancy/    # TenantService, PlanService, ...
└── Traits/         # HasAudit, HasTenant, BrazilianDates
```

---

## ✅ Testes

```bash
# Todos os testes
docker-compose exec app php artisan test

# Com cobertura (mínimo 80%)
docker-compose exec app php artisan test --coverage --min=80

# Paralelo (mais rápido)
docker-compose exec app php artisan test --parallel

# Apenas fiscal
docker-compose exec app php artisan test --filter=Fiscal

# Apenas CT-e
docker-compose exec app php artisan test --filter=CTeService
```

### Regra de qualidade

> **Nenhum PR é aceito sem testes.** O CI/CD bloqueia merges com cobertura < 80%.

---

## 📄 Documentos Fiscais

### NF-e (modelo 55)

```php
// Emitir
$nfe = Nfe::find($id);
app(NFeService::class)->emitir($nfe);

// Cancelar
app(NFeService::class)->cancelar($nfe, 'Justificativa com mínimo 15 caracteres');

// Carta de Correção
app(NFeService::class)->cartaCorrecao($nfe, 'Correto o bairro do destinatário.');
```

### CT-e + CIOT (modelo 57)

```php
// Gerar CIOT antes de emitir CT-e com autônomo
$ciot = app(CTeService::class)->gerarCiot(
    cpfCnpjContratado: '123.456.789-00',   // CPF do motorista
    cpfCnpjContratante: '11.222.333/0001-81',
    valorFrete: 1500.00,
    valorPedagio: 80.00,
    placaVeiculo: 'ABC1D23',
    ufOrigem: 'SP',
    ufDestino: 'RJ',
    empresa: $empresa,
);

// Salvar CIOT no CT-e e emitir
$cte->update(['ciot' => $ciot['ciot']]);
app(CTeService::class)->emitir($cte);
```

### Ambientes SEFAZ

Configure no `.env`:
```
SEFAZ_AMBIENTE=2   # 2 = Homologação (testes)
SEFAZ_AMBIENTE=1   # 1 = Produção
```

> ⚠️ **NUNCA faça deploy em produção com ambiente de homologação.**
> O CI/CD verifica esta variável antes do deploy.

---

## 🔐 Segurança

- Senhas de certificados digitais **criptografadas** com `encrypt()` (AES-256-CBC)
- XMLs e PDFs fiscais armazenados no S3 com acesso **privado**
- URLs assinadas com expiração para download de documentos
- Rate limiting na API: 60 req/min (autenticado), 10 req/min (anônimo)
- Auditoria completa de todas as ações em `audit_logs`
- CORS configurado apenas para domínios autorizados
- Headers de segurança: HSTS, X-Frame-Options, CSP

---

## 📡 API REST

Documentação completa disponível em `/docs` (gerada pelo Scribe).

```bash
# Regenerar docs
docker-compose exec app php artisan scribe:generate
```

Base URL: `https://app.erpsaas.com.br/api/v1`

Autenticação: `Bearer {token}` (Sanctum)

---

## 🔄 CI/CD

| Branch | Ação |
|--------|------|
| `feature/**` | Lint + Testes |
| `develop` | Lint + Testes + Deploy Staging |
| `main` | Lint + Testes + Deploy Produção |

---

## 📋 Roadmap

- [ ] Sprint 1–2: ✅ Fundação, multi-tenancy, cadastros
- [ ] Sprint 3–4: Financeiro completo
- [ ] Sprint 5–6: Estoque + Compras
- [ ] Sprint 7–8: Vendas / CRM
- [ ] Sprint 9–10: NF-e + NFC-e homologação
- [ ] Sprint 11–12: NFS-e múltiplos municípios
- [ ] Sprint 13–14: CT-e + CIOT + MDF-e
- [ ] Sprint 15–16: Relatórios e BI
- [ ] Sprint 17–18: Produção

---

## 👨‍💻 Desenvolvido por

Gilmário Santos — [github.com/gilmariosantos](https://github.com/gilmariosantos)

---

## 📝 Licença

Proprietário — todos os direitos reservados.
