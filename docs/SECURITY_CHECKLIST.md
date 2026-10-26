# Safari Zangu Ventures — Security Checklist

## Before Going Live (Production)

### Must Do
- [ ] Change admin password (min 10 chars, uppercase + number)
- [ ] Create `.env` from `.env.example` — real DB credentials
- [ ] Remove `.git` from server: `rm -rf <public_html>/.git`
- [ ] Set `APP_ENV=production` in `.env`
- [ ] Uncomment HSTS header in `.htaccess` after confirming HTTPS
- [ ] Replace `G-XXXXXXXXXX` with real GA4 ID in `layouts/head.php`
- [ ] Switch PayPal to `live` mode in `config/app.php`
- [ ] Set real `ADMIN_EMAIL` in config
- [ ] Verify SPF/DKIM/DMARC DNS records on Hostinger
- [ ] Verify cron is set: Hostinger hPanel → Advanced → Cron Jobs (list,
      not `crontab -l` — Hostinger shared hosting gives no shell/sudo
      access to a real Unix crontab)

### Database
- [ ] Change DB password from default
- [ ] Remove phpMyAdmin from production (or restrict to admin IP)
- [ ] Backup DB weekly — covered by `cron/backup.sh` (see
      `docs/DEPLOYMENT_GUIDE.md` Step 6); also check whether the current
      Hostinger plan includes its own automatic backups as a second,
      independent layer

### File Permissions
See `docs/DEPLOYMENT_GUIDE.md` Step 4 — Hostinger shared hosting gives no
`sudo` and no separate `www-data` user to `chown` to (the hosting account
itself already owns every file), so the commands that used to be here
(`sudo find ... chmod`, `sudo chown www-data:www-data ...`) never actually
ran there. Permissions needing write access (`assets/images/`, `storage/`)
are `chmod 775`'d as that same account, nothing more.

## Implemented Security Features

| Feature | Status | Notes |
|---------|--------|-------|
| bcrypt password hashing (cost 12) | ✓ | admin_users table |
| Session timeout (30 min inactivity, default) | ✓ | AuthMiddleware — now configurable, Settings → General → Security |
| Track Booking password (bcrypt, cost 10) | ✓ | Replaces the old ref+travel-date pairing; see BookingTracking, SECURITY_ARCHITECTURE.md §5a |
| Supplier Contract PDFs stored outside web root | ✓ | storage/uploads/contracts/, .htaccess-blocked, served only via admin-gated supplier_contract.php |
| Server-side login lockout | ✓ Fixed | File-based, not session |
| REMOTE_ADDR only (no header spoofing) | ✓ Fixed | RateLimiter |
| Path traversal prevention (gallery) | ✓ Fixed | realpath() check |
| Gallery delete validation | ✓ Fixed | Path must be inside assets/ |
| Open redirect prevention | ✓ Fixed | Allowlist only |
| Email header injection prevention | ✓ Fixed | contact.php |
| Quotation PDF auth | ✓ Fixed | Admin session required |
| X-Frame-Options | ✓ | .htaccess |
| X-Content-Type-Options | ✓ | .htaccess |
| SQL blocked from web | ✓ Fixed | .htaccess |
| Session SameSite=Strict | ✓ Fixed | bootstrap.php |
| CSRF tokens on public forms | ✓ | book.php, contact.php |
| Rate limiting (public forms) | ✓ | 5 req/hour per IP |
| Error display off in production | ✓ Fixed | bootstrap.php |
| Cryptographic booking refs | ✓ Fixed | random_bytes() |
| Agent permission isolation | ✓ | leads_api.php |
| Content Security Policy | ✓ | .htaccess |

## Monitoring

DEVOPS-5: there is no automated alerting (no Sentry/Bugsnag/uptime
service) — a fatal error now also emails `ADMIN_EMAIL` (see
`app/bootstrap.php`'s exception handler, throttled to at most one email
per 15 minutes so an incident can't flood the inbox), but regularly
checking the below is still the only way to catch anything short of a
hard fatal (slow SMTP, a rising failed-payment rate, etc.). Recommended,
not yet set up (needs an external account, so it can't be done from
code): a free-tier uptime monitor (e.g. UptimeRobot) pinging
`https://safarizanguven.com/` and alerting by email/SMS on downtime.

Check these regularly:
```bash
# Failed login attempts
grep "login" /tmp/szv_ratelimit/*.json | head -20

# Email queue failures — now a real, checked-in file (previously
# /var/log/szv_email.log, which required root to create and that nothing
# ever wrote to — see admin/email_queue.php)
tail -f storage/logs/email.log
tail -f storage/logs/reminders.log

# PHP errors — Hostinger shared hosting has no shell access to
# /var/log/apache2/error.log; check hPanel → Advanced → PHP Configuration
# (error log viewer), or ask Hostinger support for the current path
```
