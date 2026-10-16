# Safari Zangu Ventures — Security Checklist

## Before Going Live (Production)

### Must Do
- [ ] Change admin password (min 10 chars, uppercase + number)
- [ ] Create `.env` from `.env.example` — real DB credentials
- [ ] Remove `.git` from server: `rm -rf /var/www/html/safari-zangu/.git`
- [ ] Set `APP_ENV=production` in `.env`
- [ ] Uncomment HSTS header in `.htaccess` after confirming HTTPS
- [ ] Replace `G-XXXXXXXXXX` with real GA4 ID in `layouts/head.php`
- [ ] Switch PayPal to `live` mode in `config/app.php`
- [ ] Set real `ADMIN_EMAIL` in config
- [ ] Verify SPF/DKIM/DMARC DNS records on Hostinger
- [ ] Run `sudo crontab -u www-data -l` — verify cron is set

### Database
- [ ] Change DB password from default
- [ ] Remove phpMyAdmin from production (or restrict to admin IP)
- [ ] Backup DB weekly (Hostinger scheduled backups)

### File Permissions
```bash
# Web server should not own PHP files
sudo find /var/www/html/safari-zangu -type f -name "*.php" -exec chmod 644 {} \;
sudo find /var/www/html/safari-zangu -type d -exec chmod 755 {} \;
# Assets need write access for gallery uploads
sudo chmod -R 775 /var/www/html/safari-zangu/assets/images/
sudo chown -R www-data:www-data /var/www/html/safari-zangu/assets/images/
# Storage must not be world-readable
sudo chmod 700 /tmp/szv_ratelimit/
```

## Implemented Security Features

| Feature | Status | Notes |
|---------|--------|-------|
| bcrypt password hashing (cost 12) | ✓ | admin_users table |
| Session timeout (30 min inactivity) | ✓ | AuthMiddleware |
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

Check these regularly:
```bash
# Failed login attempts
grep "login" /tmp/szv_ratelimit/*.json | head -20

# PHP errors
sudo tail -f /var/log/apache2/error.log

# Email queue failures
sudo tail -f /var/log/szv_email.log
```
