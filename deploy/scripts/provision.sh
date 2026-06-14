#!/usr/bin/env bash
#
# provision.sh — Provisiona um VPS Ubuntu 22.04 do zero para o ERP SaaS.
#
# Funciona em qualquer VPS com root: Hostinger KVM, Oracle Cloud ARM, etc.
# Idempotente: pode rodar várias vezes sem quebrar.
#
# Uso (como root ou sudo):
#   curl -fsSL https://raw.githubusercontent.com/gilmariosantos/erp-saas/main/deploy/scripts/provision.sh | bash
#   ou
#   sudo bash deploy/scripts/provision.sh
#
set -euo pipefail

# ─── Cores ───────────────────────────────────────────────────────────────────
GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
log()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
err()  { echo -e "${RED}[✗]${NC} $1"; }

# ─── Detecção de arquitetura (suporta ARM da Oracle e x86 da Hostinger) ──────
ARCH=$(uname -m)
log "Arquitetura detectada: ${ARCH}"

# ─── Variáveis configuráveis ─────────────────────────────────────────────────
PHP_VERSION="${PHP_VERSION:-8.3}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"
APP_DIR="${APP_DIR:-/var/www/erp-saas}"

echo ""
echo "═══════════════════════════════════════════════════════"
echo "  ERP SaaS — Provisionamento de VPS"
echo "═══════════════════════════════════════════════════════"
echo ""

# ─── 1. Atualiza o sistema ───────────────────────────────────────────────────
log "Atualizando o sistema..."
apt-get update -qq
apt-get upgrade -y -qq

# ─── 2. Pacotes base ─────────────────────────────────────────────────────────
log "Instalando pacotes base..."
apt-get install -y -qq \
  software-properties-common curl git unzip zip \
  supervisor nginx certbot python3-certbot-nginx \
  ufw fail2ban

# ─── 3. PHP ──────────────────────────────────────────────────────────────────
log "Instalando PHP ${PHP_VERSION}..."
add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1 || true
apt-get update -qq
apt-get install -y -qq \
  php${PHP_VERSION}-fpm php${PHP_VERSION}-cli php${PHP_VERSION}-mysql \
  php${PHP_VERSION}-redis php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml \
  php${PHP_VERSION}-bcmath php${PHP_VERSION}-zip php${PHP_VERSION}-gd \
  php${PHP_VERSION}-intl php${PHP_VERSION}-soap php${PHP_VERSION}-curl \
  php${PHP_VERSION}-opcache php${PHP_VERSION}-gmp

# ─── 4. Composer ─────────────────────────────────────────────────────────────
if ! command -v composer &>/dev/null; then
  log "Instalando Composer..."
  curl -sS https://getcomposer.org/installer | php
  mv composer.phar /usr/local/bin/composer
else
  log "Composer já instalado."
fi

# ─── 5. MySQL 8 ──────────────────────────────────────────────────────────────
if ! command -v mysql &>/dev/null; then
  log "Instalando MySQL 8..."
  apt-get install -y -qq mysql-server
  systemctl enable mysql
  warn "Rode 'mysql_secure_installation' manualmente depois para definir senha root."
else
  log "MySQL já instalado."
fi

# ─── 6. Redis ────────────────────────────────────────────────────────────────
if ! command -v redis-server &>/dev/null; then
  log "Instalando Redis..."
  apt-get install -y -qq redis-server
  systemctl enable redis-server
else
  log "Redis já instalado."
fi

# ─── 7. Node.js 20 (para build do frontend) ──────────────────────────────────
if ! command -v node &>/dev/null; then
  log "Instalando Node.js 20..."
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash - >/dev/null 2>&1
  apt-get install -y -qq nodejs
else
  log "Node.js já instalado ($(node --version))."
fi

# ─── 8. Usuário de deploy ────────────────────────────────────────────────────
if ! id "${DEPLOY_USER}" &>/dev/null; then
  log "Criando usuário '${DEPLOY_USER}'..."
  adduser --disabled-password --gecos "" "${DEPLOY_USER}"
  usermod -aG www-data "${DEPLOY_USER}"
else
  log "Usuário '${DEPLOY_USER}' já existe."
fi

# ─── 9. Firewall ─────────────────────────────────────────────────────────────
log "Configurando firewall (UFW)..."
ufw --force enable >/dev/null 2>&1
ufw allow OpenSSH >/dev/null 2>&1
ufw allow 'Nginx Full' >/dev/null 2>&1

# ─── 10. Diretório da aplicação ──────────────────────────────────────────────
mkdir -p "${APP_DIR}"
chown -R "${DEPLOY_USER}:www-data" "${APP_DIR}"

echo ""
echo "═══════════════════════════════════════════════════════"
log "Provisionamento concluído!"
echo "═══════════════════════════════════════════════════════"
echo ""
echo "Próximos passos:"
echo "  1. mysql_secure_installation        # define senha do MySQL"
echo "  2. Crie o banco: bash deploy/scripts/setup-database.sh"
echo "  3. Faça o deploy:  bash deploy/scripts/deploy.sh"
echo "  4. Configure SSL:  bash deploy/scripts/setup-ssl.sh seudominio.com.br"
echo ""
