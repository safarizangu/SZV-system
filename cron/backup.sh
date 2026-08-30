#!/bin/bash
# ============================================================
# Safari Zangu Ventures — Daily Database Backup
#
# TO INSTALL (run once on the server):
#   chmod +x /var/www/html/safari-zangu/cron/backup.sh
#   sudo crontab -u www-data -e
#   Add this line (runs at 2am daily):
#   0 2 * * * /var/www/html/safari-zangu/cron/backup.sh >> /var/log/szv_backup.log 2>&1
#
# Creates daily backups in /var/backups/szv_db/
# Keeps 30 days of backups, deletes older files automatically.
# ============================================================

DATE=$(date +%Y-%m-%d_%H%M)
BACKUP_DIR="/var/backups/szv_db"
BACKUP_FILE="$BACKUP_DIR/safari_zangu_$DATE.sql.gz"
KEEP_DAYS=30

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Detect environment
if [ -f "/var/www/html/safari-zangu/.env" ]; then
    export $(grep -v '^#' /var/www/html/safari-zangu/.env | xargs)
fi

# Use env vars if available, fall back to local defaults
DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-safari_zangu}"

echo "[$DATE] Starting backup of $DB_NAME..."

# Run mysqldump and compress
mysqldump \
    --host="$DB_HOST" \
    --user="$DB_USER" \
    --password="$DB_PASS" \
    --single-transaction \
    --routines \
    --triggers \
    --add-drop-table \
    "$DB_NAME" | gzip > "$BACKUP_FILE"

if [ $? -eq 0 ]; then
    SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    echo "[$DATE] Backup successful: $BACKUP_FILE ($SIZE)"
else
    echo "[$DATE] ERROR: Backup failed for $DB_NAME"
    exit 1
fi

# Delete backups older than KEEP_DAYS days
find "$BACKUP_DIR" -name "safari_zangu_*.sql.gz" -mtime +$KEEP_DAYS -delete
COUNT=$(ls "$BACKUP_DIR" | wc -l)
echo "[$DATE] Cleanup done. $COUNT backup(s) retained."
