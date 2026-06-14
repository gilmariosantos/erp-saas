# Guia de Deploy & Go-Live — ERP SaaS

Guia completo para colocar o ERP SaaS em produção. Funciona em qualquer VPS Ubuntu 22.04 com acesso root: **Hostinger KVM**, **Oracle Cloud ARM**, DigitalOcean, etc.

> ⚠️ **Não funciona em hospedagem compartilhada.** O sistema exige Redis, Horizon (filas), Supervisor e criação de bancos em runtime — só disponíveis em VPS com root.

---

## Pré-requisitos

- VPS Ubuntu 22.04 com no mínimo **8 GB RAM** (KVM 2 / Oracle ARM)
- Domínio próprio com acesso ao painel de DNS
- Acesso SSH como root

---

## Passo 1 — DNS Wildcard (multi-tenancy)

No painel de DNS do seu domínio, crie:

| Tipo | Nome | Valor |
|------|------|-------|
| A | `@` | IP do seu VPS |
| A | `*` | IP do seu VPS |

O registro **wildcard (`*`)** é o que permite o auto-registro: qualquer `empresa.seudominio.com.br` aponta para o servidor, e o sistema identifica o tenant pelo subdomínio.

Aguarde a propagação (pode levar até algumas horas).

---

## Passo 2 — Provisionar o servidor

Conecte via SSH e rode o script de provisionamento:

```bash
ssh root@SEU_IP
git clone https://github.com/gilmariosantos/erp-saas.git /tmp/erp
sudo bash /tmp/erp/deploy/scripts/provision.sh
```

Isso instala: PHP 8.3, MySQL 8, Redis, Nginx, Node.js 20, Composer, Supervisor, Certbot, firewall.

---

## Passo 3 — Configurar o banco de dados

```bash
sudo mysql_secure_installation     # define a senha root do MySQL
sudo bash /var/www/erp-saas/deploy/scripts/setup-database.sh
```

O script cria o banco `erp_landlord` e um usuário com permissão para **criar bancos de tenants em runtime** (essencial para o multi-tenancy).

---

## Passo 4 — Configurar o `.env`

```bash
cd /var/www/erp-saas
sudo -u deploy cp .env.example .env
sudo -u deploy nano .env
```

Ajuste os valores essenciais:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seudominio.com.br
APP_DOMAIN_BASE=seudominio.com.br
CENTRAL_DOMAIN=seudominio.com.br

DB_DATABASE=erp_landlord
DB_USERNAME=erp
DB_PASSWORD=<senha definida no passo 3>

REDIS_HOST=127.0.0.1
QUEUE_CONNECTION=redis
CACHE_STORE=redis

# Fiscal — comece SEMPRE em homologação
SEFAZ_AMBIENTE=2

# Billing (preencha com suas chaves)
ASAAS_API_KEY=
MERCADOPAGO_ACCESS_TOKEN=

# Super-admin inicial
ADMIN_EMAIL=seu@email.com.br
ADMIN_PASSWORD=<senha forte>
```

---

## Passo 5 — Primeiro deploy

```bash
cd /var/www/erp-saas
sudo -u deploy bash deploy/scripts/deploy.sh
sudo -u deploy php artisan key:generate
sudo -u deploy php artisan migrate --seed --force
```

---

## Passo 6 — Nginx

```bash
sudo cp deploy/nginx/erp-saas.conf /etc/nginx/sites-available/erp-saas
sudo nano /etc/nginx/sites-available/erp-saas   # troque "seudominio.com.br"
sudo ln -s /etc/nginx/sites-available/erp-saas /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

---

## Passo 7 — SSL Wildcard

```bash
sudo bash deploy/scripts/setup-ssl.sh seudominio.com.br
```

Wildcard exige **validação DNS** — o Certbot vai pedir para você criar um registro TXT no DNS. Siga as instruções na tela.

---

## Passo 8 — Filas e agendador (Supervisor)

```bash
sudo cp deploy/supervisor/erp-horizon.conf /etc/supervisor/conf.d/
sudo cp deploy/supervisor/erp-scheduler.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start erp-horizon erp-scheduler
```

---

## Passo 9 — Backups automáticos

```bash
sudo crontab -e
# Adicione:
0 2 * * * bash /var/www/erp-saas/deploy/scripts/backup.sh
```

---

## Passo 10 — Deploy automático (CI/CD)

No GitHub, em **Settings → Secrets → Actions**, adicione:

| Secret | Valor |
|--------|-------|
| `PROD_HOST` | IP do VPS |
| `PROD_USER` | `deploy` |
| `PROD_SSH_KEY` | chave SSH privada do usuário deploy |

A partir daí, todo push na branch `main` que passar nos testes faz deploy automático.

---

## Checklist final de go-live

- [ ] DNS wildcard propagado (`dig teste.seudominio.com.br` resolve)
- [ ] HTTPS funcionando (cadeado verde)
- [ ] Registro de empresa cria tenant com sucesso
- [ ] Login funciona
- [ ] Horizon processando filas (`php artisan horizon:status`)
- [ ] Backup rodou ao menos uma vez
- [ ] `SEFAZ_AMBIENTE=2` (homologação) até validar fiscal
- [ ] Certificado digital testado (`fiscal:testar-homologacao`)
- [ ] Primeiro cliente piloto cadastrado

---

## Custos mensais estimados

| Item | Custo |
|------|-------|
| VPS KVM 2 (8GB) ou Oracle ARM (grátis) | R$ 0 – 40 |
| Domínio (.com.br) | ~R$ 40/ano |
| SSL (Let's Encrypt) | Grátis |
| **Total inicial** | **~R$ 40/mês** |

Conforme crescer (mais tenants, volume fiscal), escale para KVM 4 (16GB).
