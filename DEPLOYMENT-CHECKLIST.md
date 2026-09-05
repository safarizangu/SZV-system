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
0 2 * * * /home/u662187129/domains/safarizanguven.com/public_html/cron/backup.sh

### STEP 7 — Smoke test production
curl -I https://safarizanguven.com/
curl -s https://safarizanguven.com/api/track_lookup.php?ref=TEST&date=2026-01-01

### STEP 8 — HSTS
After confirming HTTPS works, the HSTS header is already in .htaccess.
Verify with: curl -I https://safarizanguven.com/ | grep Strict-Transport

### STEP 9 — SPF/DKIM/DMARC DNS records
Add to your DNS (via Hostinger DNS panel):
TXT @ v=spf1 include:hostinger.com ~all
Add DKIM via Hostinger Email → Advanced Settings
TXT _dmarc v=DMARC1; p=quarantine; rua=mailto:admin@safarizanguven.com
