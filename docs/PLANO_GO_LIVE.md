# Plano de Colocar no Ar — ERP SaaS

Guia consolidado para tirar o sistema do código e colocá-lo operando de verdade.

---

## Onde estamos (estado real)

- **19 commits** em `develop` além do `main`
- **25 controllers, 27 services, 49 models, 67 tabelas, 24 páginas React**
- **Frontend:** validado de verdade (build passa sem erros)
- **Backend:** nunca executado — este repositório contém o código-fonte, mas
  **não tem o scaffold do Laravel instalado** (sem `bootstrap/app.php`,
  sem `Console/Kernel.php`). O framework precisa ser montado no servidor.

---

## Auditoria estática pré-deploy (feita)

Como não há PHP no ambiente de desenvolvimento, foi feita uma auditoria
estática em Python. Resultado:

| Checagem | Resultado |
|----------|-----------|
| Sintaxe estrutural PHP (chaves/parênteses) | ✅ OK |
| Namespaces PSR-4 batem com caminho | ✅ OK |
| Models referenciados existem | ⚠️ 1 bug corrigido (`RegrasComissao` → `RegraComissao`) |
| Controllers das rotas existem | ✅ OK (25) |
| Services referenciados existem | ✅ OK (24) |
| Métodos de integração da OS existem | ✅ OK |
| Imports do frontend resolvem | ✅ OK |
| Migrations sem tabela duplicada | ✅ OK (67 tabelas) |
| Build do frontend | ✅ Passa |

**Importante:** auditoria estática pega erros grosseiros (nomes, imports,
estrutura). Ela **NÃO substitui** rodar o PHP — erros de lógica, tipos,
queries SQL e compatibilidade com a SEFAZ só aparecem na execução real.

---

## Passo a passo do go-live

### Fase A — Montar o Laravel (primeira vez)

O código-fonte precisa ser montado sobre um Laravel instalado:

1. Num servidor/VM com PHP 8.3, criar projeto Laravel 11 base
2. Sobrepor os arquivos deste repositório (app/, database/, routes/, resources/)
3. `composer require` das dependências fiscais:
   - `nfephp-org/sped-nfe`, `nfephp-org/sped-cte`, `nfephp-org/sped-nfse`
   - `stancl/tenancy`, `laravel/horizon`, `pragmarx/google2fa`
4. `composer install` e resolver conflitos de versão que aparecerem

### Fase B — Validar (o passo que falta há muito)

5. `php artisan config:clear && php artisan route:list`
   → aqui aparecem os primeiros erros reais (classes, assinaturas)
6. `php artisan migrate` no banco central
   → valida todas as 67 migrations
7. `php artisan test`
   → roda os 16 arquivos de teste pela primeira vez
8. **Corrigir o que quebrar** (é esperado que quebre algo na 1ª vez)

### Fase C — CI/CD verde

9. Confirmar o pipeline no GitHub Actions passando
10. Só então: mergear `develop` → `main`

### Fase D — Deploy (scripts já prontos em deploy/)

11. Provisionar VPS: `bash deploy/scripts/provision.sh`
12. Banco: `bash deploy/scripts/setup-database.sh`
13. DNS wildcard `*.dominio` + SSL: `bash deploy/scripts/setup-ssl.sh`
14. Deploy: `bash deploy/scripts/deploy.sh`
15. Supervisor (Horizon + scheduler) + backups

### Fase E — Fiscal real

16. Subir certificado A1 pela tela (nunca por fora)
17. `php artisan fiscal:testar-homologacao {empresa}`
18. Emitir nota de teste em homologação
19. Validar XML aceito pela SEFAZ → migrar para produção

### Fase F — Negócio (fora do código)

- CNPJ da operação, contrato de prestação de serviço
- Política de privacidade + termos de uso (LGPD)
- Configurar gateways reais (Asaas/Mercado Pago) com chaves de produção
- Configurar servidor de e-mail (SMTP) para os transacionais
- Canal de suporte ao cliente

---

## Riscos conhecidos (honestos)

1. **Primeiro `artisan` vai acusar erros** — normal, código nunca rodou.
   Melhor descobrir agora que com cliente usando.
2. **XML fiscal não validado contra XSD** — NF-e e CT-e montam o XML, mas
   só a SEFAZ de homologação confirma se está 100% no leiaute.
3. **Itens da NF-e** — o formulário cria o cabeçalho; adicionar produtos
   com impostos na nota ainda precisa de trabalho no backend.
4. **Sem scaffold Laravel** — a montagem inicial (Fase A) é trabalho de
   integração que pode revelar incompatibilidades de versão.

---

## Resumo

O sistema está **funcionalmente completo e estaticamente auditado**, mas
**operacionalmente não-validado**. O caminho crítico não é mais código de
feature — é montar, validar e homologar. As Fases A e B são o gargalo real:
até o PHP rodar uma vez, tudo é potencial, não garantia.
