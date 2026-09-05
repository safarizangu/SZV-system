# Safari Zangu — Local Development Setup

## Why BASE_PATH is now empty

BASE_PATH is always `''` (empty string) — same as production.
This means URLs like `<?= BASE_PATH ?>/about.php` become just `/about.php`.

On local dev, Apache serves the project via an Alias, so
`http://localhost/safari-zangu/` still works transparently.

## Apache Alias (already configured)

Your Apache config has:
```apache
Alias /safari-zangu /var/www/html/safari-zangu/public
```

This means:
- `http://localhost/safari-zangu/` → serves from `/var/www/html/safari-zangu/public/`
- BASE_PATH = '' so all links are root-relative: `/about.php`, `/admin/`, etc.
- On production: `https://safarizanguven.com/` → same files at root

## Starting local dev

```bash
cd /var/www/html/safari-zangu
sudo systemctl start apache2
# Open: http://localhost/safari-zangu/
# Admin: http://localhost/safari-zangu/admin/
```

## No changes needed for production

When you push to Hostinger:
- Files go into public_html/ root (not a subfolder)
- BASE_PATH = '' → all URLs work at safarizanguven.com/
- .htaccess handles routing automatically
