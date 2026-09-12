#!/usr/bin/env bash
#
# montar-laravel.sh — Monta o scaffold do Laravel sobre o código-fonte do ERP.
#
# CONTEXTO: este repositório contém o código de aplicação (app/, database/,
# routes/, resources/, config/ customizados) mas NÃO o scaffold de runtime
# do Laravel (artisan, bootstrap/, public/, configs padrão). Este script
# baixa um Laravel 11 limpo e sobrepõe o código do repositório nele.
#
# ONDE RODAR: numa máquina/VM/servidor com PHP 8.3 e Composer instalados.
#             NÃO roda no ambiente de dev do assistente (sem PHP lá).
#
# USO:
#   bash montar-laravel.sh /caminho/destino
#
set -euo pipefail

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
log()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
err()  { echo -e "${RED}[✗]${NC} $1"; }

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEST="${1:-./erp-montado}"

echo "═══════════════════════════════════════════════════════"
echo "  Montagem do Laravel — ERP SaaS"
echo "═══════════════════════════════════════════════════════"
echo "  Código-fonte: ${REPO_DIR}"
echo "  Destino:      ${DEST}"
echo ""

# ─── Pré-requisitos ──────────────────────────────────────────────────────────
command -v php >/dev/null || { err "PHP não encontrado. Instale PHP 8.3+."; exit 1; }
command -v composer >/dev/null || { err "Composer não encontrado."; exit 1; }

PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
log "PHP ${PHP_VER} encontrado"

# ─── 1. Cria o scaffold Laravel 11 limpo ─────────────────────────────────────
if [ -d "${DEST}" ]; then
  warn "Destino já existe. Usando o que está lá."
else
  log "Criando scaffold Laravel 11 limpo..."
  composer create-project laravel/laravel "${DEST}" "^11.0" --no-interaction
fi

# ─── 2. Sobrepõe o código de aplicação do repositório ────────────────────────
log "Sobrepondo o código do repositório..."
# app/ — código de aplicação (mantém os providers padrão do Laravel que não conflitam)
cp -r "${REPO_DIR}/app/." "${DEST}/app/"
# database/ — migrations, factories, seeders
cp -r "${REPO_DIR}/database/." "${DEST}/database/"
# routes/ — as rotas do sistema
cp -r "${REPO_DIR}/routes/." "${DEST}/routes/"
# resources/ — views de e-mail etc.
cp -r "${REPO_DIR}/resources/." "${DEST}/resources/"
# config/ customizados (billing, fiscal, tenancy) — sem sobrescrever os padrão
cp "${REPO_DIR}/config/"*.php "${DEST}/config/"
# composer.json com as dependências corretas
cp "${REPO_DIR}/composer.json" "${DEST}/composer.json"
# .env.example do projeto
cp "${REPO_DIR}/.env.example" "${DEST}/.env.example" 2>/dev/null || true
# frontend/
[ -d "${REPO_DIR}/frontend" ] && cp -r "${REPO_DIR}/frontend" "${DEST}/frontend"
# deploy/ e docs/
[ -d "${REPO_DIR}/deploy" ] && cp -r "${REPO_DIR}/deploy" "${DEST}/deploy"
[ -d "${REPO_DIR}/docs" ] && cp -r "${REPO_DIR}/docs" "${DEST}/docs"

log "Código sobreposto"

# ─── 3. Instala as dependências declaradas no composer.json ──────────────────
cd "${DEST}"
log "Instalando dependências (pode demorar — são muitas libs fiscais)..."
composer install --no-interaction 2>&1 | tail -5 || {
  warn "composer install acusou conflitos. Rode manualmente e resolva:"
  warn "  cd ${DEST} && composer update"
}

# ─── 4. Configura o ambiente ─────────────────────────────────────────────────
[ -f .env ] || cp .env.example .env
php artisan key:generate --no-interaction || warn "key:generate falhou — verifique o .env"

# ─── 5. Publica configs de pacotes (stancl, spatie, horizon…) ────────────────
log "Publicando configs de pacotes..."
php artisan vendor:publish --tag=tenancy-config --no-interaction 2>/dev/null || true
php artisan vendor:publish --provider="Spatie\\Permission\\PermissionServiceProvider" --no-interaction 2>/dev/null || true

echo ""
echo "═══════════════════════════════════════════════════════"
log "Scaffold montado! Agora a VALIDAÇÃO (o passo que nunca rodou):"
echo "═══════════════════════════════════════════════════════"
echo ""
echo "  cd ${DEST}"
echo "  php artisan route:list          # 1º teste: as rotas carregam?"
echo "  # configure o banco no .env, depois:"
echo "  php artisan migrate             # valida as 67 migrations"
echo "  php artisan test                # roda os testes pela 1ª vez"
echo ""
warn "É ESPERADO que algo quebre na primeira vez — o código nunca rodou."
warn "Anote os erros e leve ao assistente para correção precisa."
