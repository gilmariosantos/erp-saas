#!/usr/bin/env bash
#
# setup-database.sh — Cria o banco central e o usuário MySQL do ERP.
#
# Uso: bash deploy/scripts/setup-database.sh
#
set -euo pipefail

GREEN='\033[0;32m'; NC='\033[0m'
log() { echo -e "${GREEN}[✓]${NC} $1"; }

DB_NAME="${DB_NAME:-erp_landlord}"
DB_USER="${DB_USER:-erp}"

read -rsp "Digite uma senha forte para o usuário MySQL '${DB_USER}': " DB_PASS
echo ""

log "Criando banco e usuário..."
sudo mysql <<MYSQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
-- Permissão para criar bancos de tenants (multi-tenancy)
GRANT ALL PRIVILEGES ON \`erp\_%\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`tenant\_%\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
-- Necessário para o stancl/tenancy criar bancos em runtime
GRANT CREATE, DROP ON *.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
MYSQL

log "Banco '${DB_NAME}' e usuário '${DB_USER}' criados."
echo ""
echo "Adicione ao seu .env:"
echo "  DB_DATABASE=${DB_NAME}"
echo "  DB_USERNAME=${DB_USER}"
echo "  DB_PASSWORD=<a senha que você digitou>"
