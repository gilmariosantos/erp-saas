#!/usr/bin/env bash
#
# setup-ssl.sh — Configura SSL (Let's Encrypt) com suporte a wildcard.
#
# Para multi-tenancy, precisamos de certificado wildcard (*.dominio).
# Wildcard exige validação DNS-01 (não HTTP), então requer um plugin DNS
# ou validação manual via TXT record.
#
# Uso: bash deploy/scripts/setup-ssl.sh seudominio.com.br
#
set -euo pipefail

DOMAIN="${1:?Informe o domínio: bash setup-ssl.sh seudominio.com.br}"

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
log()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }

echo "Configurando SSL para ${DOMAIN} e *.${DOMAIN}"
echo ""
warn "Certificado WILDCARD exige validação DNS-01."
warn "O Certbot vai pedir para você criar um registro TXT no DNS."
echo ""

# Certificado wildcard via validação DNS manual
certbot certonly --manual --preferred-challenges dns \
  -d "${DOMAIN}" -d "*.${DOMAIN}" \
  --agree-tos --no-eff-email

log "Certificado emitido. Configurando renovação automática..."

# Hook de renovação
cat > /etc/cron.d/certbot-renew <<CRON
0 3 * * * root certbot renew --quiet --post-hook "systemctl reload nginx"
CRON

log "SSL configurado! Atualize o Nginx com os caminhos dos certificados."
echo "  ssl_certificate     /etc/letsencrypt/live/${DOMAIN}/fullchain.pem;"
echo "  ssl_certificate_key /etc/letsencrypt/live/${DOMAIN}/privkey.pem;"
