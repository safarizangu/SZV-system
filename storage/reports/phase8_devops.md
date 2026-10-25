# Phase 8 — DevOps / Deployment & Operations Audit
Safari Zangu Ventures booking system — deployment runbook, backups, logging, monitoring, server config, environment management, cron, email queue, file storage, and secret leakage.
Audit type: read-only. Static review of `docs/`, `.htaccess`, `app/`, `config/`, `cron/`, `api/cron/`, `.gitignore`, `composer.json/lock`, plus live, non-mutating `curl` probes against the running local Apache instance (`http://localhost/safari-zangu`) and read-only `git log`/`git status`/`git check-ignore` calls. No application file was modified; no state-changing git command was run.

Scope note: this is a solo/small-team Tanzania tour operator on Hostinger shared hosting with a local VPS dev box. Findings are scored against that scale — e.g. "no Kubernetes" is not a finding, but "the one documented backup mechanism likely can't run on the actual hosting target" is.

---

## Finding 1 — Root-level audit/scratch artifacts are sitting in the live document root and are publicly fetchable right now, including a file that enumerates the app's own vulnerability findings

**Severity:** Critical
**Impact:** Active information disclosure on the live server. `security_report.json` — a prior tool's vulnerability scan — is currently served at 200 and lists specific files it flagged for "Hardcoded Secrets" and "Command Injection" (`agent-portal/login.php`, `agent-portal/leads.php`, `config/app.php`, `app/Mail/EmailTemplate.php`, `app/Mail/Mailer.php`, `app/Services/PayPalClient.php`, etc.). This is a ready-made attack roadmap, served from the production document root.
**Risk:** Any visitor who guesses or crawls these filenames gets a prioritized list of where to look for exploitable issues, plus `folder_tree.txt` (full repo structure) and `project_inventory.json`/`technology_inventory.json` (stack fingerprinting) — all with zero authentication.
**Root Cause:** These are untracked files (confirmed via `git status --porcelain`: `folder_tree.txt`, `project_inventory.json`, `security_report.json`, `system_audit_report.json`, `technology_inventory.json`, `security_auditor.py`, six `_admin_*.php.png` screenshots, `audit/`, `screenshots/`) dropped directly into `/var/www/html/safari-zangu`, which is both the git working tree **and** the Apache document root. `.htaccess`'s sensitive-extension block (`<FilesMatch "\.(env|env\.example|py|sh|sql|log|bak|swp|gitignore|md)$">`) does not cover `.json`, `.txt`, or `.png`, so none of these are blocked. Live verification:
```
curl -s -o /dev/null -w '%{http_code}' .../folder_tree.txt         → 200
curl -s -o /dev/null -w '%{http_code}' .../project_inventory.json  → 200
curl -s -o /dev/null -w '%{http_code}' .../security_report.json    → 200
curl -s -o /dev/null -w '%{http_code}' .../n.py                    → 403  (extension already blocked)
```
`security_auditor.py` is correctly blocked (`.py` extension), proving the extension-based control works — it's simply the wrong control for files like these, and incomplete for the extensions that matter here.
**Recommended Fix:** Delete these files from the document root (they belong in the git-ignored scratch area this audit itself was told to use, never the live webroot) and never generate audit/analysis artifacts inside the project's own served directory. As a defense-in-depth backstop, extend the `.htaccess` `FilesMatch` to also deny `json|txt` at the root, and add `Options -Indexes` enforcement checks for any future stray directories (already set globally, confirmed working — bare `vendor/` and `storage/logs/` both 403 on directory listing).
**File Location:** `/var/www/html/safari-zangu/{folder_tree.txt,project_inventory.json,security_report.json,system_audit_report.json,technology_inventory.json,security_auditor.py,audit/,screenshots/,_admin_*.php.png}`; gap in `/var/www/html/safari-zangu/.htaccess:76-79`.

---

## Finding 2 — The deployment runbook and backup script mix two mutually incompatible hosting models (shared hosting vs. root-shell VPS); the single documented backup mechanism may never actually install in production

**Severity:** High
**Impact:** Business continuity. If the DB backup cron job was never actually installed on the real Hostinger account, there is currently no verified, working backup of the production database at all — only an unverified checkbox.
**Risk:** Silent backup failure is the worst kind: nothing alerts anyone, and the gap is only discovered during a disaster, when it's too late.
**Root Cause:** The docs describe two different worlds without reconciling them:
- `docs/DEPLOYMENT_GUIDE.md` Step 1–6 describes classic Hostinger **shared hosting**: SSH/FTP upload to `/home/u662187129/domains/safarizanguven.com/public_html/`, SQL migrations pasted into **phpMyAdmin** (not a CLI), and the three PHP cron jobs installed via the **"Hostinger Control Panel"** cron UI — none of which implies root/sudo shell access, because Hostinger shared-hosting plans generally don't grant it.
- That same Step 6 then lists a 4th line, `0 2 * * * .../cron/backup.sh`, as if it can be installed the same way (Hostinger Control Panel) — but `cron/backup.sh`'s own header comment says to install it via `sudo crontab -u www-data -e`, and `docs/SECURITY_CHECKLIST.md:15` separately instructs `sudo crontab -u www-data -l` to verify it. Both of those require root, which the rest of the same guide shows the account doesn't have.
- `backup.sh` writes to `/var/backups/szv_db/`, a path outside the hosting account's home directory — not writable by a shared-hosting PHP/cron user.
- Meanwhile `docs/SECURITY_CHECKLIST.md:20` documents a **completely different, simpler** backup strategy: `- [ ] Backup DB weekly (Hostinger scheduled backups)` — i.e. relying on Hostinger's built-in panel backup feature instead of the custom script, with a different schedule (weekly vs. the script's daily) and no evidence either was ever ticked off or confirmed on the live account.
Separately, `admin/email_queue.php:77,208,227` and `admin/reminders.php:245` read and display `/var/log/szv_email.log` / `/var/log/szv_reminders.log` as a "Live Email Log" panel — but no cron line in `DEPLOYMENT_GUIDE.md` redirects script output to those paths (the documented lines have no `>>` redirect at all), no script in the codebase writes to them directly, and `/var/log/` is not writable by a shared-hosting account anyway. On production, these admin log-viewer panels are almost certainly reading a file that doesn't exist.
**Recommended Fix:** Pick one hosting reality and make every doc agree with it. If production really is Hostinger shared hosting (as the file paths strongly suggest), `backup.sh` cannot use `sudo crontab`/`/var/backups/` and must instead write inside the hosting account's own home directory and be installed through the Hostinger cron UI like the other three jobs — or the plan's built-in scheduled-backup feature should be the documented, single source of truth, with `SECURITY_CHECKLIST.md`'s item 20 upgraded from an unverified checkbox to something with a verification step (e.g. "confirm a backup file appears in hPanel → Backups within 24h of enabling, then check again monthly"). Either way, fix the `/var/log/szv_*.log` references or add the missing output redirection to the documented crontab lines so the admin UI panels that depend on them actually work.
**File Location:** `docs/DEPLOYMENT_GUIDE.md:81-85`, `cron/backup.sh:1-13`, `docs/SECURITY_CHECKLIST.md:15,20`, `admin/email_queue.php:77,208,227`, `admin/reminders.php:245`.

---

## Finding 3 — No backup coverage at all for uploaded files (supplier contracts, quotation PDFs, team/gallery images)

**Severity:** Medium-High
**Impact:** Permanent, unrecoverable data loss for anything that isn't a database row. `cron/backup.sh` is a `mysqldump`-only script — it backs up table rows, not files.
**Risk:** `storage/uploads/contracts/` (signed supplier contract PDFs, `admin/suppliers.php`) and `storage/uploads/quotations/` (generated quotation PDFs, `app/Services/PdfRenderer.php:116`) are never captured by any backup process found in this codebase. `assets/images/{team,gallery}/` (public photos uploaded via `api/upload_team_image.php` and `api/gallery/upload.php`) are similarly uncovered. If the server disk is lost, the DB backup would still contain a `suppliers.contract_filename` value pointing at a file that no longer exists anywhere — the reference survives, the asset doesn't.
**Root Cause:** Backup strategy (such as it is — see Finding 2) was designed around the database only; file storage was never brought into scope.
**Recommended Fix:** Extend `cron/backup.sh` (or add a sibling script) to `tar.gz` `storage/uploads/` and `assets/images/{team,gallery}/` alongside the SQL dump, applying the same 30-day retention policy, or — simpler for a shared-hosting target — rely on Hostinger's whole-account backup feature if available on the plan, and confirm it actually covers these paths rather than assuming it does.
**File Location:** `cron/backup.sh` (no file-backup step); storage destinations at `admin/suppliers.php:24`, `app/Services/PdfRenderer.php:116`, `api/upload_team_image.php:51`, `api/gallery/upload.php:23`.

---

## Finding 4 — `vendor/`, `composer.json`, and `composer.lock` are directly web-accessible, disclosing exact third-party library versions

**Severity:** Medium
**Impact:** Information disclosure that aids targeted exploitation. Live verification:
```
curl .../composer.json                      → 200, body: phpmailer/phpmailer ^7.1, dompdf/dompdf ^3.1
curl .../vendor/autoload.php                → 200
curl .../vendor/composer/installed.json     → 200, body includes: "name":"dompdf/dompdf","version":"v3.1.6"
curl .../composer.lock                      → 200
curl .../vendor/                            → 403  (directory listing itself is blocked — Options -Indexes works)
```
**Risk:** An attacker gets the exact installed version of every third-party dependency without any guesswork, which is exactly the information needed to check for known CVEs against that specific version. Directory listing is blocked, but every individual file path is still directly servable if guessed or found via a public vulnerability database's typical file layout (e.g. `vendor/composer/installed.json` is a very common, well-known path).
**Root Cause:** `.htaccess`'s sensitive-directory block list — `RewriteRule ^(app|config|database|storage|sql|tests|cron)/ - [F,L]` (`.htaccess:39`) — omits `vendor/`. Root-level `composer.json`/`composer.lock` aren't covered by the extension block either (`.json` isn't in the `FilesMatch` pattern at `.htaccess:76`).
**Recommended Fix:** Add `vendor` to the blocked-directory rewrite list (`RewriteRule ^vendor/ - [F,L]`) and add `composer\.(json|lock)$` to the blocked `FilesMatch` pattern, consistent with how `sql/`, `app/`, and `config/` are already handled.
**File Location:** `.htaccess:39-48` (directory block list), `.htaccess:76-79` (extension block list).

---

## Finding 5 — No CI/CD pipeline; the existing custom test suite is never run automatically before deploy

**Severity:** Low (appropriately scaled for a one-person operator) — but cheap to fix and worth doing
**Impact:** Nothing gates a deploy on the app's own tests passing. `tests/run.php` with 12 suites exists (`AuthMiddlewareTest`, `BookingValidatorTest`, `ItineraryQuoteBuilderTest`, `JsPricingConsistencyTest`, `RateLimiterTest`, etc. — real, substantive coverage of exactly the areas this codebase's own audits have flagged as risky), but deployment is a fully manual FTP/SSH + phpMyAdmin checklist (`docs/DEPLOYMENT_GUIDE.md`) with no automated step that runs them.
**Risk:** A regression caught by, say, `ItineraryQuoteBuilderTest` or `JsPricingConsistencyTest` would only be caught if a human remembers to run `php tests/run.php` locally before manually uploading files — nothing enforces it.
**Root Cause:** No `.github/workflows/` or any other CI config exists anywhere in the repository (confirmed: no `.yml`/`.yaml` files at all outside `vendor/`).
**Recommended Fix:** Given the scale, a single lightweight GitHub Actions workflow (`php tests/run.php` on every push/PR to `main`) is a near-zero-cost addition that would catch regressions before the manual deploy step, without requiring any change to the actual Hostinger deployment process. Full CD automation is not warranted at this scale and is not being recommended.
**File Location:** repository root (no `.github/workflows/`); existing tests at `tests/run.php`, `tests/suites/*.php`.

---

## Finding 6 — No error/uptime monitoring; operational visibility is 100% manual `tail -f`

**Severity:** Medium
**Impact:** Nobody is alerted when something breaks. The app's own documentation confirms the current process is entirely manual and reactive:
```
docs/SECURITY_CHECKLIST.md:60-72 — "## Monitoring / Check these regularly:"
  grep "login" /tmp/szv_ratelimit/*.json | head -20
  sudo tail -f /var/log/apache2/error.log
  sudo tail -f /var/log/szv_email.log
```
**Risk:** For a site that processes live PayPal payments (`api/paypal_create_order.php`, `api/record_payment.php`) and runs unattended cron jobs (email queue, reminders, quotation expiry), a silent failure — a cron job that stops running, a PayPal webhook that starts erroring, SMTP credentials expiring — would only be noticed if someone happens to SSH in and read a log file. No composer dependency, PHP include, or script in this codebase integrates Sentry, Bugsnag, Rollbar, New Relic, Datadog, or any uptime checker (confirmed via grep across `composer.json`, `composer.lock`, and all `.php` files — zero matches).
**Root Cause:** Never built; understandable at MVP stage, but the gap is now live with real payment processing enabled (`PAYPAL_MODE=live` in `.env.example`/`docs/DEPLOYMENT_GUIDE.md`).
**Recommended Fix:** Two cheap, proportionate additions: (1) a free-tier uptime monitor (UptimeRobot, Better Uptime, or similar) pinging the homepage and one API endpoint every few minutes with email/SMS alert on failure; (2) wire the existing `set_exception_handler`/`register_shutdown_function` in `app/bootstrap.php:104-113` to also fire a webhook (e.g. a free Sentry PHP SDK call, or even a simple `error_log` → admin email for 5xx-class fatals only, rate-limited) instead of only writing to the Apache error log that nobody is alerted about.
**File Location:** `app/bootstrap.php:79-114` (existing fatal/exception handler — good hook point, currently log-only); `docs/SECURITY_CHECKLIST.md:60-72` (confirms manual-only process).

---

## Finding 7 — `storage/logs/` is a dead convention: the directory exists but nothing writes to it; all actual error logging goes to Apache's error log instead

**Severity:** Low
**Impact:** Minor confusion/maintainability only — no security impact, since the actual destination (Apache's error log) is itself correctly protected.
**Risk:** A future developer, or an admin following `.gitignore`'s own comments (`# Storage (runtime, not committed) / storage/logs/*`), would reasonably expect application errors to land in `storage/logs/` and look there first — they won't find anything, because the app's 118 `error_log()` call sites (across `admin/*.php`, `api/*.php`, `app/**/*.php` — confirmed via repo-wide grep) all use PHP's bare `error_log()`, which — with no `ini_set('error_log', ...)` override anywhere in the codebase (confirmed via grep) — writes to the SAPI's default destination. This server runs `libapache2-mod-php8.3`, whose default error destination is Apache's own error log:
```
apache2ctl -S → Main ErrorLog: "/var/log/apache2/error.log"
php -i | grep error_log → error_log => no value => no value   (no override; falls through to SAPI default)
```
Confirmed live: the Apache error log is actively receiving PHP warnings/notices right now (e.g. repeated `Undefined variable $__icons` from `views/home.php:731` during this audit session), proving this is where output genuinely lands — not `storage/logs/`, which contains only an empty `.gitkeep`.
**Root Cause:** `storage/logs/` was scaffolded (directory + `.gitignore` entries + `.gitkeep`) but no code was ever written to actually log there; `error_log()` was used as-is everywhere instead.
**Recommended Fix:** Either remove the unused `storage/logs/` scaffolding to avoid the false impression it's in use, or — better — point the app's error logging there explicitly via `ini_set('error_log', __DIR__.'/../storage/logs/app.log')` in `app/bootstrap.php` for errors that matter operationally (keeping the Apache log as the OS-level catch-all). Either choice should be reflected in `docs/SECURITY_CHECKLIST.md`'s monitoring section so it stays accurate.
**File Location:** `storage/logs/.gitkeep`, `.gitignore:5-11`; `app/bootstrap.php` (no `error_log` ini override); 118 call sites via `grep -rn "error_log(" --include="*.php" .` (list available on request).

---

## Finding 8 — Log rotation exists but is an unexamined OS default, not a deliberate policy for this app's actual log volume

**Severity:** Low (informational)
**Impact:** None currently observed, but the 14-day/daily rotation policy was never chosen with this app in mind.
**Risk:** Minimal at current traffic, but if error volume grows (e.g. a misbehaving cron job logging in a tight loop — not a hypothetical; this session's own log showed the same two warnings repeated dozens of times per request on `views/home.php`), 14 days of uncompressed-then-compressed Apache logs could grow large before anyone notices, since nothing alerts on log size (see Finding 6).
**Root Cause:** `/etc/logrotate.d/apache2` is Ubuntu's stock package default (`daily, rotate 14, compress, delaycompress`) applying to all of `/var/log/apache2/*.log` server-wide — it was never customized for this specific application, and no corresponding entry exists in any `docs/*.md` acknowledging that rotation is handled at the OS level rather than the app level.
**Recommended Fix:** No change needed, but add one line to `docs/DEPLOYMENT_GUIDE.md` or `SECURITY_CHECKLIST.md` noting that log rotation is handled by the OS's stock `apache2` logrotate policy (14 days) so a future maintainer doesn't go looking for an app-level rotation mechanism that doesn't exist.
**File Location:** `/etc/logrotate.d/apache2` (OS-level, outside the repository).

---

## Finding 9 — Minor: duplicated `.env` parsing logic in two places

**Severity:** Low
**Impact:** Maintainability only.
**Risk:** `app/Helpers/Env.php` (a class-based loader used by `app/bootstrap.php:38-41`) and `config/app.php:12-24`'s own inline `loadEnv()` function implement near-identical `.env` parsing independently. Any future fix to one (e.g. quoted-value handling, comment handling) is unlikely to be mirrored in the other.
**Root Cause:** Historical — `config/app.php`'s loader appears to predate `app/Helpers/Env.php`, and the newer helper was never used to replace the older inline one.
**Recommended Fix:** Have `config/app.php` call `\App\Helpers\Env::load()` instead of defining its own `loadEnv()`, or vice versa. Not urgent.
**File Location:** `app/Helpers/Env.php:8-36`, `config/app.php:12-25`.

---

## Strengths confirmed (not findings, but verified and worth recording since they were explicitly in scope)

- **Secret leakage:** `.env` has never been committed to git history (`git log --all --full-history -- .env` returns nothing; `git ls-files | grep .env` returns nothing; `git check-ignore -v .env` confirms it's actively ignored via `.gitignore:2`). A repo-wide regex sweep for common real-secret shapes (`AIza...`, `sk_live_...`, `AKIA...`, PEM private key headers, Slack `xox...` tokens) across `.php/.md/.json/.sql/.sh` returned zero hits. `.env` itself contains only local-dev placeholder-shaped values, consistent with its own header comment ("LOCAL DEV only").
- **No hardcoded production secret fallbacks:** `config/app.php` explicitly documents and follows a "no literal fallback for secrets" rule — `DB_PASS`, `SMTP_PASSWORD`, `PAYPAL_CLIENT_SECRET`, and `CRON_SECRET` all default to `''` (fail closed) if `.env` is missing; only non-secret identifiers (e.g. a sandbox PayPal client-id placeholder string) have hardcoded defaults. `CRON_SECRET`'s comment explicitly calls out that this fails closed by design.
- **`.env.example` exists and is complete** — every key the app actually reads has a documented placeholder entry, which is good onboarding hygiene for a future deployment.
- **Sensitive paths correctly blocked**, verified live: `.env` → 403, `.env.example` → 403, `app/bootstrap.php` → 403, `config/app.php` → 403, `storage/logs/` → 403, `sql/baseline_schema.sql` → 403, `.git/config` → 403, `README.md` → 403, `api/cron/process_email_queue.php` → 403. Uploaded contract PDFs (`storage/uploads/contracts/`) are correctly outside direct web access and served only via an admin-gated script (`api/supplier_contract.php`), confirmed by the same `^storage/` rewrite block.
- **Email queue has a genuine dead-letter mechanism, not infinite retry:** `app/Mail/Mailer.php:260` explicitly runs `UPDATE email_queue SET status=IF(attempts>=3,'failed',status), last_error=? WHERE id=?` after each failed attempt, and `api/cron/process_email_queue.php`'s own query only selects `WHERE status='pending' AND attempts < 3`, so a permanently-failing email stops being retried after 3 attempts and surfaces as `status='failed'` with a visible, searchable, manually-retriable row in `admin/email_queue.php` (single or bulk retry UI, confirmed at `admin/email_queue.php:20,33`). No infinite-retry risk found.
- **Cron documentation cross-reference is clean:** every file in `api/cron/` is accounted for — `process_email_queue.php`, `process_reminders.php`, and `process_quotation_expiry.php` are all documented with matching schedules in `docs/DEPLOYMENT_GUIDE.md:82-85`; `send_single_email.php` is intentionally absent from the crontab list because it's dispatched via `exec()` from `App\Mail\Mailer::dispatchBackground()` (`app/Mail/Mailer.php:172-187`), not run on a schedule — and that background-dispatch path has a documented, working synchronous fallback if `exec()` is disabled (`attemptImmediateSend()`, same file, lines 142-162), which is good defensive engineering for a shared-hosting target where `exec()` is often locked down.
- **Public image uploads are correctly scoped inside the web root by design** (`assets/images/{team,gallery}/`, since they must be displayed on the site), while private documents (contracts, generated quotation PDFs) are correctly kept outside it (`storage/uploads/`) — the split is intentional and consistent, not accidental.
- **Defense-in-depth on security headers:** `app/bootstrap.php:44-58` sets the same security headers (CSP, X-Frame-Options, etc.) at the PHP level in addition to `.htaccess`'s `mod_headers` block, with a code comment explicitly noting `mod_headers` was found disabled in this environment and PHP-level headers were added specifically to not depend on it — good awareness of a real platform risk, already mitigated.
- **No error_log call site was found concatenating `$e->getMessage()` with raw SQL/bound parameter values or credentials** in a repo-wide grep for that pattern; messages logged are exception text plus a static context label, and production forces `display_errors=0`/`error_reporting=0` in two independent places (`app/bootstrap.php:62-65` and `config/app.php:76-78`), so even an exception message that happened to include partial DB error text cannot reach the browser.
- **Dependency pinning is sound:** `composer.json` uses caret ranges (`^7.1`, `^3.1`) but `composer.lock` pins exact resolved versions (confirmed: `dompdf/dompdf v3.1.6`), and `docs/DEPLOYMENT_GUIDE.md` Step 5 correctly runs `composer install` (which respects the lock file) rather than `composer update` for production deploys.

---

## DevOps Score: 61 / 100

**Justification:** The fundamentals that are hardest to retrofit are genuinely solid — secret handling is disciplined and fails closed, sensitive paths are correctly blocked and independently verified live, the email queue has real retry-limit/dead-letter logic, and cron documentation is internally consistent for the files it covers. These are not small things to get right and this codebase gets them right.

What pulls the score down is that the *operational* layer — the parts that matter once the code is actually running unattended on someone else's server — is thinner than the code itself suggests, and in one case actively contradicts itself in a way that could mean the single documented backup mechanism has never worked in production (Finding 2, High). There is currently no automated monitoring of any kind (Finding 6) for a site handling live payments, no CI gate on an already-decent test suite (Finding 5), file uploads have zero backup coverage (Finding 3), and — most urgently — the live document root is currently serving a prior security scan's own findings to anyone who requests the URL (Finding 1, Critical, and the only finding in this report that needs same-day attention regardless of the others). None of these require sophisticated infrastructure to fix; they require reconciling documentation with reality and a handful of cheap, proportionate additions (an uptime pinger, a `vendor/` block, deleting some files, one GitHub Action). That gap between "the hard parts are done well" and "the cheap parts were never finished" is what the score reflects.
