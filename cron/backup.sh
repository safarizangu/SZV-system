#!/bin/bash
# ============================================================
# Safari Zangu Ventures — Daily Backup (database + uploaded files)
#
# DEVOPS-2/DEVOPS-3: this used to assume root/sudo access
# (`sudo crontab -u www-data -e`, writing to /var/backups/ and
# /var/log/, both root-owned) and a hardcoded local dev path
# (/var/www/html/safari-zangu/.env) to read DB credentials from — none
# of which exist on the actual production host (Hostinger shared
# hosting), so this backup may never have actually run successfully
# there. It also only ever backed up the database — nothing covered
# supplier contracts, quotation PDFs, or team/gallery images.
#
# TO INSTALL on Hostinger (hPanel → Advanced → Cron Jobs — same place as
# every other cron job in docs/DEPLOYMENT_GUIDE.md Step 6, no SSH/sudo
# needed):
#   chmod +x <public_html>/cron/backup.sh
#   Add a cron job, command:
#   <public_html>/cron/backup.sh
#   Schedule: once daily (e.g. 2:00 AM)
#
# Backups are written inside the project itself, under storage/backups/
# — already denied to web requests by storage/.htaccess, and always
# writable by whichever user owns the project (no root path needed).
# Keeps 30 days, deletes older files automatically.
# ============================================================

# Resolve the project root from this script's own location, so the same
# script works unmodified in any environment (local dev, Hostinger, or
# anywhere else) instead of a path hardcoded to one of them.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

DATE=$(date +%Y-%m-%d_%H%M)
BACKUP_DIR="$PROJECT_ROOT/storage/backups"
DB_BACKUP_FILE="$BACKUP_DIR/db/safari_zangu_$DATE.sql.gz"
FILES_BACKUP_FILE="$BACKUP_DIR/files/uploads_$DATE.tar.gz"
KEEP_DAYS=30

mkdir -p "$BACKUP_DIR/db" "$BACKUP_DIR/files"

# Load DB credentials from this project's own .env (same file app/bootstrap.php reads).
if [ -f "$PROJECT_ROOT/.env" ]; then
    export $(grep -v '^#' "$PROJECT_ROOT/.env" | xargs)
fi

DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-safari_zangu}"

echo "[$DATE] Starting database backup of $DB_NAME..."

mysqldump \
    --host="$DB_HOST" \
    --user="$DB_USER" \
    --password="$DB_PASS" \
    --single-transaction \
    --routines \
    --triggers \
    --add-drop-table \
    "$DB_NAME" | gzip > "$DB_BACKUP_FILE"

if [ $? -eq 0 ]; then
    SIZE=$(du -h "$DB_BACKUP_FILE" | cut -f1)
    echo "[$DATE] Database backup successful: $DB_BACKUP_FILE ($SIZE)"
else
    echo "[$DATE] ERROR: Database backup failed for $DB_NAME"
fi

# DEVOPS-3: uploaded/generated files with no other backup coverage —
# supplier contracts + quotation PDFs (storage/uploads/) and team/
# gallery images (assets/images/). Excludes storage/backups/ itself.
echo "[$DATE] Starting files backup (storage/uploads/, assets/images/)..."

tar -czf "$FILES_BACKUP_FILE" \
    -C "$PROJECT_ROOT" \
    --exclude="storage/backups" \
    storage/uploads assets/images 2>/dev/null

if [ $? -eq 0 ] || [ $? -eq 1 ]; then
    # tar exits 1 for "file changed as we read it", harmless for this purpose
    SIZE=$(du -h "$FILES_BACKUP_FILE" | cut -f1)
    echo "[$DATE] Files backup successful: $FILES_BACKUP_FILE ($SIZE)"
else
    echo "[$DATE] ERROR: Files backup failed"
fi

# Delete backups older than KEEP_DAYS days
find "$BACKUP_DIR/db" -name "safari_zangu_*.sql.gz" -mtime +$KEEP_DAYS -delete
find "$BACKUP_DIR/files" -name "uploads_*.tar.gz" -mtime +$KEEP_DAYS -delete
DB_COUNT=$(ls "$BACKUP_DIR/db" 2>/dev/null | wc -l)
FILES_COUNT=$(ls "$BACKUP_DIR/files" 2>/dev/null | wc -l)
echo "[$DATE] Cleanup done. $DB_COUNT DB backup(s), $FILES_COUNT files backup(s) retained."
