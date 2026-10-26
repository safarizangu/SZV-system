# Pre-Fix Snapshot — Audit Remediation Safety Net

**Generated:** 2026-10-25, immediately before remediation work began.

## Branch / Version Control

- **Starting branch:** `main`
- **Remediation branch:** `audit-remediation` (created from `main` at commit `33e69ff`)
- **Baseline commit:** `33e69ff` — "Fix day/night duration mismatch and add Day 0 arrival-day numbering" (this was pre-existing, completed, tested work from earlier in the same session, committed first so it wouldn't get mixed into remediation history)
- Prior to that: `9e1dc30`, `b3f88e2` (dated commits, pre-existing project history)

## Environment

- **PHP:** 8.3.31 (cli) (NTS)
- **Apache:** 2.4.52 (Ubuntu)
- **MySQL:** 8.0.46-0ubuntu0.22.04.4
- **Database:** `safari_zangu`

## File Count

573 tracked files (excluding `.git/` and `vendor/`)

## Database Row Counts (at snapshot time)

| Table | Rows |
|---|---|
| admin_users | 4 |
| bookings | 17 |
| quotations | 18 |
| leads | 3 |
| customers | 7 |
| commissions | 1 |
| packages | 6 |
| itineraries | 13 |
| custom_safari_requests | 8 |
| booking_reminders | 52 *(34 of these are confirmed orphans per DB-1 — see phase3_database.md)* |
| analytics_events | 1,235 |
| activity_log | 57 |
| email_queue | 42 |

## Backups

Both created before any remediation file change beyond the already-urgent DEVOPS-1 fix (see note below):

- **Database dump:** `safari_zangu_20261025_214619.sql` (804 KB) — full `mysqldump` of `safari_zangu`.
- **Project tree:** `safari-zangu_20261025_214619.tar.gz` (67 MB) — full working tree excluding `vendor/`, `node_modules/`, `.git/`.

Both stored in this session's scratchpad directory (outside the project and outside the web root entirely — the most durable option available given this host's permissions don't allow writing outside `/var/www/html/safari-zangu`): `/tmp/claude-1000/-var-www-html-safari-zangu/f283556b-938a-46dd-9bfb-331f88712c76/scratchpad/pre_remediation_backup/`.

**This is a session-scoped location, not a permanent backup destination.** For a real durable backup, these two files should be copied somewhere outside this machine's `/tmp` before the session ends — flagging this explicitly rather than implying it's handled, since DEVOPS-3 in the audit found backup coverage is already a known gap in this project.

## Note on Sequencing

DEVOPS-1 (the live-exposed audit-artifact files in the web root, Critical) was fixed *before* this snapshot was generated, not after — it was actively live and leaking information (including this very audit's own `security_report.json`-equivalent) for the entire time it remained unfixed, so closing it took priority over strict phase ordering. That fix is documented in full under Phase 1 of `final_remediation_report.md` once complete. Every other remediation phase follows the planned order from this point on.
