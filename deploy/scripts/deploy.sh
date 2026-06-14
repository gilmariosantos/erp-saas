#!/usr/bin/env bash
#
# deploy.sh — Faz o deploy/atualização do ERP SaaS.
#
# Estratégia: zero-downtime via modo de manutenção curto.
# Roda como usuário 'deploy' dentro do diretório da aplicação.
#
# Uso:
#   bash deploy/scripts/deploy.sh
#
set -euo pipefail

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
log()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }

APP_DIR="${APP_DIR:-/var/www/erp-saas}"
BRANCH="${DEPLOY_BRANCH:-main}"

cd "${APP_DIR}"

echo "═══════════════════════════════════════════════════════"
echo "  Deploy do ERP SaaS — branch ${BRANCH}"
echo "═══════════════════════════════════════════════════════"

# ─── 1. Modo de manutenção ───────────────────────────────────────────────────
log "Ativando modo de manutenção..."
php artisan down --render="errors::503" --retry=15 || true

# ─── 2. Atualiza o código ────────────────────────────────────────────────────
log "Baixando última versão (${BRANCH})..."
git fetch origin "${BRANCH}"
git reset --hard "origin/${BRANCH}"

# ─── 3. Dependências PHP ─────────────────────────────────────────────────────
log "Instalando dependências PHP..."
composer install --no-dev --optimize-autoloader --no-interaction

# ─── 4. Build do frontend ────────────────────────────────────────────────────
if [ -d "frontend" ]; then
  log "Buildando frontend React..."
  cd frontend
  npm ci --silent
  npm run build
  cd ..
fi

# ─── 5. Migrations ───────────────────────────────────────────────────────────
log "Rodando migrations do banco central..."
php artisan migrate --force

log "Rodando migrations de todos os tenants..."
php artisan tenants:migrate --force || warn "Comando tenants:migrate pulado (sem tenants ainda)."

# ─── 6. Otimizações de produção ──────────────────────────────────────────────
log "Otimizando para produção..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# ─── 7. Storage link ─────────────────────────────────────────────────────────
php artisan storage:link 2>/dev/null || true

# ─── 8. Reinicia workers e filas ─────────────────────────────────────────────
log "Reiniciando Horizon e filas..."
php artisan horizon:terminate || true
sudo supervisorctl restart erp-horizon || warn "Supervisor erp-horizon não configurado ainda."

# ─── 9. Permissões ───────────────────────────────────────────────────────────
log "Ajustando permissões..."
sudo chown -R deploy:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# ─── 10. Sai do modo de manutenção ───────────────────────────────────────────
log "Desativando modo de manutenção..."
php artisan up

# ─── 11. Recarrega PHP-FPM ───────────────────────────────────────────────────
sudo systemctl reload php8.3-fpm

echo ""
echo "═══════════════════════════════════════════════════════"
log "Deploy concluído com sucesso!"
echo "═══════════════════════════════════════════════════════"
