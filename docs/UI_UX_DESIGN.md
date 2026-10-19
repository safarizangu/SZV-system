# UI/UX Design

**Status:** Active
**Owner:** Project Team
**Version:** 1.0.0
**Last Updated:** 2026-10-19

## Purpose

How the interface actually looks and is built — design tokens, component
patterns, and the (deliberate) differences between the public site, the
admin panel, the agent portal, and generated PDFs. Verified against the
live stylesheets, not reconstructed from memory. For how pages are
assembled server-side see
[SYSTEM_ARCHITECTURE.md §3](SYSTEM_ARCHITECTURE.md#3-layers); for the
booking-workflow diagram itself see
[Booking_Workflow_Map.pdf](reports/Booking_Workflow_Map.pdf).

---

## 1. Two typographic systems, one palette

There are genuinely two separate visual identities in this codebase, not
one shared design system split across files:

| | Public site | Admin / Agent Portal / PDFs |
|---|---|---|
| Display face | `Playfair Display` (editorial serif) | `Times New Roman` |
| Body/UI face | `Jost` (geometric sans) | `Times New Roman` (same face for both roles) |
| Stylesheet | `assets/css/main.css` | `admin/css/admin.css` |
| Character | Editorial, marketing-led — the public face of the brand | Formal, utilitarian, document-like — deliberately reads closer to a printed business letter than a SaaS dashboard |

Both systems share the **same black/white/gold/gray palette** (`--black:
#0a0a0a`, `--gold: #c8963e`, `--gold-light: #f5edd9`), but as two
independently-maintained token sets kept numerically aligned by
convention, not by a shared tokens file — `main.css`'s `--gray-1/2/3`
equal `admin.css`'s `--gray-100/200/300` exactly, but `--gray-4/5` and
`--gray-500/700` are *close, not identical* (`#888880` vs `#7a7a72`,
`#48483e` vs `#3a3a34`). Both files carry a comment explaining this is
deliberate — renaming either file's tokens to alias the other would
silently shift colors in whichever file didn't get touched.

`admin/css/admin.css` also `@import`s Playfair Display and Jost from
Google Fonts, but **never uses either one** — `--font-body`/`--font-disp`
both resolve to Times New Roman. This is dead weight (an unused
~30–40KB font fetch on every admin page load), most plausibly left over
from an earlier copy-paste off `main.css`, not a deliberate choice; worth
removing in a future pass rather than treating as intentional.

## 2. Design tokens

### Admin panel (`admin/css/admin.css` `:root`)

```css
--gold: #c8963e;            --gold-light: #f5edd9;
--black: #0a0a0a;            --black-hover: #1e1e1e;   /* dark-mode-safe hover state */
--white: #ffffff;
--gray-50/100/200/300/500/700: #f9f9f8 … #3a3a34;      /* light → dark */
--green/red/blue/amber: #16a34a / #dc2626 / #1d4ed8 / #d97706;
--{color}-border / --{color}-light: tinted card/badge pairs for each status color
--font-body / --font-disp: 'Times New Roman', Times, serif    /* identical — see §1 */
--radius: 2px                /* sharp, not rounded — a deliberate brand choice, not a default */
--shadow-sm / --shadow-md
--space-1…7: .25rem → 3rem   /* matches main.css's scale so page work can share vocabulary */
--sidebar-w: 240px   --topbar-h: 72px
```

### Public site (`assets/css/main.css` `:root`)

```css
--black: #0a0a0a;   --black-soft: #1e1e1e;   --white: #ffffff;   --off-white: #f8f8f6;
--gray-1…5: #f3f3f1 → #48483e
--gold: #c8963e;   --gold-light: #f5edd9;    /* identical values to admin.css */
--font-display: 'Playfair Display', Georgia, serif
--font-body: 'Jost', system-ui, Helvetica, sans-serif
--ease-out / --ease-in: named easing curves for transitions
```

### Dark mode

Opt-in only, per-admin-account (`admin/settings.php`'s Appearance card
writes `theme_preference` to `admin_users`, carried into the session and
stamped as `data-theme="dark"` on `<html>`) — **not** an OS
`prefers-color-scheme` media query, since this is an account setting, not
a browser one. `:root[data-theme="dark"]` redefines the same token names
(`--black` becomes near-white, `--white` becomes near-black, etc.), which
is what makes dark mode apply almost everywhere for free: nearly every
rule in `admin.css` and in `admin/*.php`'s inline styles reads color
through a token, never a literal hex. The handful of places that do use a
literal status-color hex (badges, alerts, filter pills, tint buttons) get
their own explicit dark-mode override next to each original rule. The
public site and PDFs have no dark mode — both are always the light
palette.

## 3. Component patterns (admin panel)

| Component | Classes | Notes |
|---|---|---|
| Card | `.admin-card`, `.admin-card-header`, `.admin-card-body` | The base content container everywhere |
| Modal | `.admin-modal-overlay`, `.admin-modal` (+ `.admin-modal--wide`) | `openModal(id)`/`closeModal(id)` in `admin.js`; backdrop click and Escape both close it |
| Confirm dialog | `confirmDialog(message, opts)`, `confirmStatusChange(sel, message, opts)`, `confirmSubmitGuard(e, form, message, opts)`, `confirmNavGuard(e, href, message, opts)` | Promise-based replacement for `window.confirm()` everywhere in the panel — native `confirm()` can't be restyled or themed for dark mode |
| Prompt dialog | `promptDialog(message, opts)` | Same replacement pattern, for `window.prompt()` — added this session for "Deposit Paid"'s amount-received capture (`admin/bookings.php`); resolves to the entered string or `null` |
| Status/filter pills | `.admin-filter-pills`, `.admin-filter-pill` (+ `--amber`/`--green`/etc.) | Replaced bulkier metric-grid status cards on list pages (quotations, custom requests, email queue, leads) — same information, far less vertical space |
| Buttons | `.admin-btn` + `--primary`/`--outline`/`--danger`/`--success`/`--tint-blue` | |
| Status badges | `.badge-status` + `--green`/`--gray`/`--blue`/`--amber`/`--red` | |
| Toasts | `toast(msg, type)` from `admin.js`, `.toast-container` | Auto-dismiss, `type` is `'ok'`/`'err'`/omitted |
| Sidebar nav accordion | `.admin-nav-group`, `.admin-nav-items`, `.admin-nav-link.active` (gold) | One `localStorage` key (`szv_admin_nav_open_group`) tracks the single non-active group currently expanded — not a per-group map, which previously let a group's expanded/collapsed state go stale. The active page's own group is always exempt from auto-collapse. |
| Sign-out | `.admin-signout-btn` | Bordered red button, not a bare text link — fills solid red on hover |

### Mobile tables

```css
@media (max-width: 480px) { .admin-table { zoom: .4; } }
```

Shrinks a wide admin table to fit a phone screen at a glance; reading or
tapping a specific cell is a pinch-zoom away (native pinch-zoom already
works everywhere `maximum-scale`/`user-scalable` aren't restricted in the
viewport meta, which this project deliberately leaves unrestricted).
`zoom` isn't supported in Firefox — the one place this degrades, tables
fall back to native horizontal scroll there.

### Grid overflow pattern

A generic mobile grid-collapse rule in `admin.css` deliberately skips
`auto-fit`/`auto-fill` grids (assumed self-responsive), but
`minmax(420px, 1fr)` with `auto-fit` doesn't actually shrink below its
stated minimum — causing real horizontal overflow on a narrow phone. The
fix used project-wide wherever this pattern appears:

```css
grid-template-columns: repeat(auto-fit, minmax(min(420px, 100%), 1fr));
```

## 4. Layout shells

- **`layouts/app.php`** (public) — top nav + footer, Playfair/Jost.
- **`layouts/admin.php`** (admin panel) — fixed 240px sidebar, grouped
  accordion nav (Dashboard / Operations / Communications / Catalog /
  People & Partners / Analytics / Agent / Gallery / Settings — see
  [SYSTEM_ARCHITECTURE.md §7](SYSTEM_ARCHITECTURE.md#7-admin-panel-surface)
  for the current grouping), role-filtered per session.
- **`layouts/agent.php`** (agent portal) — a simple top navbar, not a
  sidebar, since the portal has only a handful of pages
  (Dashboard/Leads/Commissions/Profile). Loads `admin/css/admin.css`
  *first* so every shared component class (`.admin-card`, `.admin-btn`,
  `.admin-table`, `.badge-status`, `.admin-input`, `.admin-modal*`)
  renders identically to the admin panel, then its own
  `agent-portal/css/agent.css` for just the navbar shell those components
  don't need. The active nav link is gold (`.agent-nav-link.active`,
  matching `.admin-nav-link.active`'s treatment), and Sign Out is the
  same bordered-button treatment as `.admin-signout-btn`, not a bare
  link — the portal was deliberately brought in line with the admin
  panel's own nav polish rather than developing its own separate style.

## 5. The agent portal's "New Client" flow

A multi-screen modal pattern introduced this session
(`agent-portal/leads.php`), worth naming since it's the one place in the
codebase with this shape: a choice screen (two large icon + label +
one-line-description cards — Direct Booking / Custom Request) that routes
into one of two form screens, each with its own "← Back" to return to the
choice screen, and a direct-booking submission ends on a dedicated
"Booking Submitted" screen showing the Track Booking credentials once
(same one-time-reveal reasoning as `confirmation.php`'s own password
flash — see
[SECURITY_ARCHITECTURE.md §5a](SECURITY_ARCHITECTURE.md#5a-track-booking-authentication))
rather than relying on a toast that a subsequent page reload would wipe
before it could be read.

## 6. PDF document design

Every generated PDF (`api/quotation_pdf.php`, `api/booking_pdf.php`, and
their itinerary/voucher/manifest counterparts) is its own self-contained
HTML+CSS document — there is no shared PDF stylesheet or template system;
`App\Services\PdfRenderer` just captures whichever file's own HTML output
and feeds it to dompdf (see
[SYSTEM_ARCHITECTURE.md §5](SYSTEM_ARCHITECTURE.md#5-pdf-generation)).
Despite the duplication, the visual language is consistent by convention
across all of them:

- Times New Roman throughout (no exception — this is the one place the
  admin panel's and the public site's typography don't diverge, since
  these documents are meant to read as formal business correspondence)
- A single gold `.brandbar` rule (4px) as the one recurring brand mark
- Black header bar / gold accent dash before each `.sec-title`
- A `.deposit-box` (green-tinted) or `.total-block` (gold-left-border)
  pattern for payment figures — reused, with different field labels, for
  the suggested-deposit view, the real partial-payment view, and the
  paid-in-full view (see
  [SYSTEM_ARCHITECTURE.md §5](SYSTEM_ARCHITECTURE.md#5-pdf-generation)
  for the three-state model these feed)
- `-webkit-print-color-adjust: exact` on every document — without it, a
  real browser's print/save-as-PDF path silently lightens every
  background color to save ink, which looked fine in dompdf's own render
  but washed out once an admin actually printed a live preview

## 7. Known gaps

- **The admin.css Google Fonts import is dead weight** — see §1. Low
  risk to remove (nothing references either face), not yet done.
- **No shared PDF stylesheet** — each `api/*_pdf.php` file repeats
  roughly the same ~150 lines of layout CSS (`.page`, `.brandbar`,
  `.sec-title`, `.deposit-box`, the running `.page-footer`). A shared
  `pdf-base.css` included by each would remove the duplication, but
  isn't needed for correctness today — it's a maintenance-cost
  observation, not a bug.
- **`main.css` and `admin.css` tokens are not literally shared** — see
  §1's `--gray-4/5` vs `--gray-500/700` note. A future refactor toward
  one real token source would need to resolve that the two systems are
  already *slightly* different, not just renamed.
