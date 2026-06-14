#!/usr/bin/env bash
#
# backup.sh — Backup automático do banco central + todos os tenants.
# Agende no cron: 0 2 * * * bash /var/www/erp-saas/deploy/scripts/backup.sh
#
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/backups/erp-saas}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p "${BACKUP_DIR}"

# Backup de todos os bancos que começam com erp_ ou tenant_
DBS=$(mysql -N -e "SHOW DATABASES LIKE 'erp\_%'; SHOW DATABASES LIKE 'tenant\_%';" 2>/dev/null)

for DB in ${DBS}; do
  mysqldump --single-transaction --quick "${DB}" | gzip > "${BACKUP_DIR}/${DB}_${DATE}.sql.gz"
  echo "[✓] Backup: ${DB}"
done

# Remove backups antigos
find "${BACKUP_DIR}" -name "*.sql.gz" -mtime +${RETENTION_DAYS} -delete
echo "[✓] Backups com mais de ${RETENTION_DAYS} dias removidos."

# Opcional: enviar para S3/MinIO
# aws s3 sync "${BACKUP_DIR}" s3://seu-bucket-backup/ --delete
