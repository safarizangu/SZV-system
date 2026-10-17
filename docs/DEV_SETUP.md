# Safari Zangu — Local Development Setup

## How BASE_PATH actually works (corrected 2026-09-06)

This file previously claimed an Apache `Alias /safari-zangu -> public/`
was already configured on this machine. **It wasn't** — confirmed live:
`http://localhost/assets/css/main.css` 404s, while
`http://localhost/safari-zangu/assets/css/main.css` returns 200. With
`BASE_PATH` hardcoded to `''`, every stylesheet, image, and internal
link on the site pointed to the wrong place, because the shared
`/var/www/html` docroot on this machine hosts many unrelated projects
side by side with no per-project Alias set up for any of them.

`config/app.php` now sets `BASE_PATH` per environment again:

```php
define('BASE_PATH', $_isLocal ? '/safari-zangu' : '');
```

- **Local** (`localhost` / `127.0.0.1`): `BASE_PATH = '/safari-zangu'` —
  matches how this machine actually serves the project, as a plain
  subfolder of the shared docroot, routed by this project's own
  `.htaccess` (`RewriteRule ^([a-zA-Z0-9_-]+\.php)$ public/$1`).
- **Production**: `BASE_PATH = ''` — Hostinger serves the project files
  from `public_html/` root, so root-relative links are correct there.

## Starting local dev

Both services are independent and neither auto-starts on this machine —
starting only one is the most common cause of "it doesn't load" or
"Database connection failed." on a fresh terminal session.

```bash
cd /var/www/html/safari-zangu
sudo systemctl start apache2
sudo systemctl start mysql      # or: sudo systemctl start mariadb
# Open: http://localhost/safari-zangu/
# Admin: http://localhost/safari-zangu/admin/
```

Verify both are actually up before debugging anything else:

```bash
systemctl is-active apache2
systemctl is-active mysql       # or mariadb
```

"Database connection failed. Please check configuration." on the site
almost always means the DB service above is still `inactive`, not a
real config problem — start it and reload.

## If you'd rather add the Apache Alias instead

Adding a real Alias would let `BASE_PATH` stay `''` on local too,
matching production exactly. Requires root — this session couldn't do
it (no passwordless sudo in this sandbox), so it was fixed at the
`BASE_PATH` level instead, which needs no server access. If you want
the Alias instead:

```apache
# /etc/apache2/conf-available/safari-zangu.conf
Alias /safari-zangu /var/www/html/safari-zangu/public
<Directory /var/www/html/safari-zangu/public>
    AllowOverride All
    Require all granted
</Directory>
```

```bash
sudo a2enconf safari-zangu && sudo systemctl reload apache2
```

Then switch `config/app.php` back to `define('BASE_PATH', '');`
unconditionally.

## No changes needed for production

When you push to Hostinger:
- Files go into `public_html/` root (not a subfolder)
- `BASE_PATH = ''` there → all URLs work at `safarizanguven.com/`
- `.htaccess` handles routing automatically
