# Safari Zangu Ventures — Deployment Checklist
## Push to Hostinger

### STEP 1 — Create production .env on Hostinger
SSH into Hostinger and create:
/home/u662187129/domains/safarizanguven.com/public_html/.env

Contents (replace YOUR_* values):
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://safarizanguven.com
DB_HOST=localhost
DB_NAME=u662187129_safari_zangu
DB_USER=u662187129_admin
DB_PASS=YOUR_HOSTINGER_DB_PASSWORD
SMTP_HOST=smtp.hostinger.com
SMTP_PORT=465
SMTP_SECURE=ssl
SMTP_USERNAME=admin@safarizanguven.com
SMTP_PASSWORD=YOUR_SMTP_PASSWORD
MAIL_FROM_EMAIL=admin@safarizanguven.com
MAIL_FROM_NAME=Safari Zangu Ventures
ADMIN_EMAIL=admin@safarizanguven.com
SITE_EMAIL=info@safarizanguven.com
PAYPAL_EMAIL=YOUR_PAYPAL_EMAIL
PAYPAL_MODE=live
BANK_NAME=ABSA Bank Tanzania
BANK_ACCOUNT_NAME=Safari Zangu Ventures Ltd
BANK_ACCOUNT_NO=YOUR_ACCOUNT_NUMBER
BANK_SWIFT=BARCTZTZXXX
WHATSAPP_NUMBER=255663430934
GA4_ID=G-XXXXXXXXXX
```

### STEP 2 — Upload files via FTP or SSH
Upload all files EXCEPT:
- .git/
- .env (already on server)
- storage/logs/*.log
- node_modules/ (if any)

### STEP 3 — Run DB migrations in phpMyAdmin
Import any new SQL migration files from sql/

**20 Sep 2026 session**: 11 new migrations, already combined in order into
`sql/2026_09_PRODUCTION_DEPLOY_COMBINED.sql` — paste that one file into
phpMyAdmin's SQL tab instead of hunting through sql/ individually. If a
single statement in it errors with "Duplicate column" or "table already
exists", that piece is already on production — delete just that line and
re-run the rest; every CREATE TABLE already uses IF NOT EXISTS so those
are always safe to re-run as-is. Verified by test-running the whole file
against a fresh copy of the schema before writing it here.

**19 Oct 2026 session**: 4 new migrations, not yet combined — run each of
these individually in order, same "Duplicate column" = already-applied
rule as above:
- `sql/custom_requests_agent_source.sql` — `custom_safari_requests.submitted_by_agent_id`
- `sql/bookings_tracking_password.sql` — `bookings.tracking_password_hash`
- `sql/bookings_amount_paid.sql` — `bookings.amount_paid` (also backfills existing `fully_paid` bookings so their invoice shows the right figure immediately, not $0.00)
- `sql/suppliers_contract_upload.sql` — `suppliers.contract_filename`/`contract_original_name`/`contract_uploaded_at`

Also create `storage/uploads/contracts/` (writable by the web server —
covered by Step 4's `storage/` chmod below, but the directory itself
needs to exist; `admin/suppliers.php` creates it on first upload if
missing, so this is a convenience, not a hard requirement).

Optional: add `ANTHROPIC_API_KEY=` to .env to enable the Poster Maker's
AI Caption feature (admin/poster_maker.php) — it degrades to a clear
"not configured" message without one, nothing breaks either way.

### STEP 4 — Set permissions
chmod -R 755 /home/u662187129/domains/safarizanguven.com/public_html/
chmod -R 775 /home/u662187129/domains/safarizanguven.com/public_html/assets/images/
chmod -R 775 /home/u662187129/domains/safarizanguven.com/public_html/storage/

### STEP 5 — Install composer dependencies on Hostinger
cd /home/u662187129/domains/safarizanguven.com/public_html/
composer install --no-dev --optimize-autoloader

### STEP 6 — Set up cron jobs in Hostinger Control Panel
*/2 * * * * php8.3 /home/u662187129/domains/safarizanguven.com/public_html/api/cron/process_email_queue.php
0 * * * * php8.3 /home/u662187129/domains/safarizanguven.com/public_html/api/cron/process_reminders.php
0 3 * * * php8.3 /home/u662187129/domains/safarizanguven.com/public_html/api/cron/process_quotation_expiry.php
0 4 * * 0 php8.3 /home/u662187129/domains/safarizanguven.com/public_html/api/cron/prune_analytics_events.php
0 5 * * 0 php8.3 /home/u662187129/domains/safarizanguven.com/public_html/api/cron/prune_old_records.php
0 2 * * * /home/u662187129/domains/safarizanguven.com/public_html/cron/backup.sh

### STEP 7 — Smoke test production
curl -I https://safarizanguven.com/
curl -s https://safarizanguven.com/api/track_lookup.php?ref=TEST&password=TEST
(expects {"success":false,...} for a non-existent ref — confirms the
endpoint and its rate-limit lock directory are both working; it no
longer takes a `date` parameter, see API_SPECIFICATION.md)

### STEP 8 — HSTS
After confirming HTTPS works, the HSTS header is already in .htaccess.
Verify with: curl -I https://safarizanguven.com/ | grep Strict-Transport

### STEP 9 — SPF/DKIM/DMARC DNS records
Add to your DNS (via Hostinger DNS panel):
TXT @ v=spf1 include:hostinger.com ~all
Add DKIM via Hostinger Email → Advanced Settings
TXT _dmarc v=DMARC1; p=quarantine; rua=mailto:admin@safarizanguven.com
