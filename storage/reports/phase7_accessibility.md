# Phase 7 — WCAG 2.2 Accessibility Audit
**Safari Zangu Ventures — Tanzania Safari Booking System**
Scope: public site (`views/*.php`), admin panel (`admin/*.php`), agent portal (`agent-portal/*.php`)
Method: static source review (PHP/CSS/JS) + live DOM/computed-style inspection via Playwright/Chromium + direct HTTP verification (curl)
Audit type: **read-only** — no application file was modified. Only this report and `screenshots/accessibility/*.png` were written.

---

## Methodology

1. Read markup directly for every image, form control, heading, and ARIA-bearing element in: `views/home.php`, `views/about.php`, `views/packages.php`, `views/contact.php`, `views/book.php`, `views/track.php`, `views/review.php`, `views/confirmation.php`, `layouts/{app,navbar,head,footer,admin,agent}.php`, `admin/{login,index,leads,bookings,package_edit,gallery}.php`, `admin/js/admin.js`, `admin/css/admin.css`, `agent-portal/{login,index,leads}.php`, `agent-portal/css/agent.css`, `assets/css/{main,forms}.css`, `assets/js/main.js`.
2. Logged in live with the provided disposable QA accounts (`qa_audit_bot` / admin, `qa_audit_agent` / agent — not created, only used) and drove the real DOM with Playwright/Chromium: inspected `document.activeElement` and `getComputedStyle()` at each Tab stop, opened the Add‑Lead modal and the row-actions kebab menu, submitted the admin login form with a wrong password, and screenshotted focus states.
3. Cross-checked one case directly with `curl` (bypassing JS) to get the exact rendered HTML of a failed login, independent of the browser.
4. Computed contrast ratios by hand from the actual hex values declared in each stylesheet's `:root` block, using the WCAG relative-luminance formula (sRGB → linearized → `L = 0.2126R + 0.7152G + 0.0722B` → `(L1+0.05)/(L2+0.05)`), not visual estimation.

Screenshots saved to `screenshots/accessibility/`: `login_error.png`, `admin_login_tabstop_2.png`, `contact_tabstop_2.png`, `admin_row_menu_open.png`, `admin_add_lead_modal.png`, `admin_input_focus.png`.

---

## Findings

### F1 — [Critical] Admin/agent `.admin-label` elements are never associated with their form controls
**WCAG:** 1.3.1 Info and Relationships, 3.3.2 Labels or Instructions, 4.1.2 Name, Role, Value (Level A)
**Impact:** A screen-reader user focusing any admin text field, select, or textarea hears only "edit text" / "combo box" with no name at all — not even the wrong name, no name. Every field in the Add Lead, Add Booking, Package Edit, Quotation, etc. forms is effectively unlabeled to assistive tech, even though sighted users see a label right above it.
**Risk:** The admin/agent panel is the primary work surface for staff. A blind or low-vision employee (or one using voice control, which also relies on accessible names) cannot reliably fill in any back-office form — this is a workplace accessibility/legal-exposure issue, not just a public-facing nice-to-have.
**Root cause:** `admin.css` defines a `.admin-label` class (styling only, `admin/css/admin.css:434`) that visually looks like a label and is literally the HTML `<label>` element — but **none of the 269 occurrences across `admin/*.php`** (`grep -c` confirmed: 269 matches, 0 with a `for` attribute) carry a `for` attribute pointing at the adjacent control's `id`, even though the inputs already have stable `id`s (`lf-name`, `lf-email`, etc.) sitting right next to them. Confirmed live via Playwright on the real DOM of the Add Lead modal (`admin/leads.php`):
   ```json
   {"text": "Full Name *", "hasFor": false, "forVal": null}
   {"text": "Email *",     "hasFor": false, "forVal": null}
   ```
   Example, `admin/leads.php:283-284`:
   ```html
   <label class="admin-label">Full Name *</label>
   <input id="lf-name" class="admin-input" placeholder="Jane Smith" required>
   ```
   The agent portal reuses the same broken pattern: 29 of 32 `<label>` elements in `agent-portal/*.php` have no `for` (the 3 exceptions are `agent-portal/login.php:289,298` and one checkbox label in `agent-portal/leads.php:557`, which are correct).
   By contrast, `admin/login.php:411,421` and `agent-portal/login.php:289,298` use a *different* class, `form-label`, that **does** carry `for="username"`/`for="password"` correctly — proving the team knows the right pattern but didn't carry it into `.admin-label`.
**Recommended fix:** Add `for="<input-id>"` to every `.admin-label` (the IDs already exist on nearly every field — this is a template-level fix in each form, or a build-time lint rule that fails CI if `.admin-label` is emitted without a matching `for`).
**File locations:** `admin/css/admin.css:434` (class definition); pervasive in `admin/leads.php` (lines 283,287,291,295,299,306,310,320,333,348,358 and more), `admin/bookings.php`, `admin/package_edit.php` (47 occurrences), `admin/quotation_new.php`, and 15+ other admin files; `agent-portal/leads.php` (lines 423,427,431,435,439,448,452,456,460,470,507,511,515,519,523,527,531,535,539,543 and more).

---

### F2 — [Critical] Admin/agent modal dialogs have no ARIA dialog semantics and do not manage focus
**WCAG:** 4.1.2 Name, Role, Value; 2.4.3 Focus Order (Level A)
**Impact:** When "Add New Lead" (or any of the 21 pages' edit/view modals) opens, a screen reader gives no indication that a dialog appeared, what it's called, or that the rest of the page is now inert. Keyboard focus is not moved into the dialog, so a keyboard user who opens it has to manually Tab forward from wherever they were (through unrelated page content) to reach the first field, and nothing stops Tab from leaving the dialog into the page behind it.
**Risk:** Users relying on assistive tech can miss that a dialog opened entirely, continue interacting with the page underneath (which visually looks "overlaid" but is not programmatically blocked), and lose their place.
**Root cause:** `.admin-modal-overlay` markup is authored with **no `role`, no `aria-modal`, no `aria-labelledby`** anywhere. Confirmed live by opening `#addLeadModal` via `window.openModal()` and reading the DOM:
   ```json
   {"overlayRole": null, "overlayAriaModal": null, "dialogRole": null,
    "dialogAriaModal": null, "dialogAriaLabelledby": null,
    "activeElementTag": "BUTTON", "activeElementId": ""}
   ```
   (`activeElementTag: BUTTON` with no id shows focus stayed on the trigger button — it never moved into the dialog.) `window.openModal()`/`closeModal()` in `admin/js/admin.js:45-52` do nothing but toggle an `open` CSS class:
   ```js
   window.openModal = window.openModal || function (id) {
     var el = document.getElementById(id);
     if (el) el.classList.add('open');
   };
   ```
   There is no focus trap, no initial-focus move, no focus restoration on close.
   Tellingly, the codebase's *own* custom `confirmDialog()`/`promptDialog()` helpers (which replace `window.confirm()`/`window.prompt()`, `admin/js/admin.js:86-190`) **do** this correctly — `role="alertdialog" aria-modal="true" aria-labelledby="confirmDialogTitle"`, move focus to the OK button on open, and restore `prevFocus` on close. The pattern exists in the codebase; it was simply never applied to the 21 page-authored `.admin-modal-overlay` dialogs (`grep -rln "admin-modal-overlay" admin/*.php agent-portal/*.php` → 21 files; `grep -rn 'role="dialog"'` across the same files → 0 matches).
**Recommended fix:** Add `role="dialog" aria-modal="true" aria-labelledby="<id-of-title>"` to each `.admin-modal`, and extend `openModal()`/`closeModal()` to move focus to the first field (or the dialog itself) on open and restore the triggering element's focus on close — essentially reuse the logic already written for `confirmDialog()`.
**File locations:** `admin/js/admin.js:45-52` (openModal/closeModal); `admin/leads.php:274-280` (`#addLeadModal` example); same pattern in 20 other files (`admin/bookings.php`, `admin/package_edit.php`, `admin/quotation_new.php`, `admin/itinerary_edit.php`, etc.).

---

### F3 — [High] Keyboard focus indicator is suppressed on every text input/select/textarea in the admin panel and agent portal
**WCAG:** 2.4.7 Focus Visible (Level AA); 2.4.11 Focus Not Obscured — relevant in spirit (WCAG 2.2)
**Impact:** A sighted keyboard-only user tabbing through any admin form cannot reliably tell which field currently has focus.
**Risk:** Keyboard-only users (motor-impairment, switch devices, power users) lose track of position in long forms (Add Lead has 10 fields, Add Booking has more) — they either submit the wrong field's edit or have to click with a mouse to recover, defeating the point of keyboard operability.
**Root cause:** `.admin-input` in `admin/css/admin.css:443-454` sets `outline: none;` and its only `:focus` rule (line 455) changes `border-color` by roughly one shade (`var(--gray-200)` `#e6e6e2` → `var(--black)` `#0a0a0a`) — no outline, no box-shadow. There is **no** `.admin-input:focus-visible` rule anywhere in the file (confirmed by `grep -n ":focus-visible" admin/css/admin.css` — only `.admin-nav-group`, `.admin-signout-btn`, `.admin-btn/.admin-link/.admin-nav-link/.admin-icon-btn`, and `.confirm-dialog-*` get a visible focus ring; `.admin-input` and `.search-bar input` (line 714) are absent from that list). Confirmed live by focusing `#lf-name` inside the real Add Lead modal:
   ```json
   {"tag": "INPUT", "id": "lf-name",
    "outline": "none 3px rgb(10, 10, 10)", "boxShadow": "none",
    "borderColor": "rgb(10, 10, 10)"}
   ```
   (`outline style: none` — i.e. nothing is drawn regardless of the stated width/color.) `agent-portal/css/agent.css` has **zero** `:focus`/`:focus-visible` rules at all (confirmed by grep), so the agent portal inherits exactly this same gap via `admin.css` with nothing to compensate.
**Recommended fix:** Add `.admin-input:focus-visible, .search-bar input:focus-visible { outline: 2px solid var(--black); outline-offset: 1px; }` (matching the pattern already used for `.admin-btn`/`.admin-icon-btn` on line 485-488).
**File locations:** `admin/css/admin.css:443-455` (`.admin-input`), `admin/css/admin.css:714` (`.search-bar input`), `admin/login.php:265-277` (a third, page-local `.form-input` with the same `outline: none` and no restore — confirmed live: focusing `#username`/`#password` on the real login page returns `outline: "none 3px rgb(10, 10, 10)"`).

---

### F4 — [High] Public-site form inputs also lose their focus ring — because a later stylesheet silently cancels the one fix that exists
**WCAG:** 2.4.7 Focus Visible (Level AA)
**Impact:** Keyboard users filling in the public Contact, Book Now, Custom Safari, or Review forms get no visible focus indicator on any text field, exactly like the admin panel — despite the codebase clearly having *tried* to fix this globally.
**Risk:** Same as F3, but on the public, customer-facing booking funnel — a keyboard-only visitor trying to book a safari cannot see which field they're typing into.
**Root cause — a genuine cascade bug, not just a missing rule:** `assets/css/main.css:2944-2947` defines what looks like a correct, global remediation:
   ```css
   :focus-visible {
     outline: 2.5px solid var(--gold);
     outline-offset: 2px;
   }
   ```
   But `layouts/head.php:83-84` loads `forms.css` **after** `main.css`:
   ```html
   <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/main.css">
   <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/forms.css">
   ```
   and `assets/css/forms.css:41-51` independently re-declares `outline: none;` on the exact same selectors (`.form-control-sz, .sz-input`). Both rules have identical specificity (one class selector), so standard CSS cascade order applies: the rule that appears later in the combined stylesheet load order wins — and `forms.css` loads after `main.css`, so its `outline: none` always wins over `main.css`'s `:focus-visible` fix, on every page that includes both files (every public page, via `layouts/head.php`). This was confirmed live, not just read in source — focusing the real `#ct-name` field on `/contact.php`:
   ```json
   {"outline": "none 3px rgb(15, 15, 13)",
    "boxShadow": "rgba(0, 0, 0, 0) 0px 0px 0px 0px",
    "borderColor": "rgb(216, 216, 210)"}
   ```
   `outline-style: none` confirms the gold `:focus-visible` ring the developer wrote at `main.css:2944` is dead code for any `.form-control-sz`/`.sz-input` element — it is completely shadowed by the later-loaded `forms.css`.
**Recommended fix:** Either remove the duplicate `outline: none` from `forms.css:50`, or add an explicit `.form-control-sz:focus-visible, .sz-input:focus-visible { outline: 2.5px solid var(--gold); outline-offset: 2px; }` inside `forms.css` itself so it isn't dependent on load order.
**File locations:** `assets/css/main.css:2944-2947` (the shadowed rule), `assets/css/forms.css:41-51` (the rule that shadows it), `layouts/head.php:83-84` (the load order that causes it).

---

### F5 — [High] Form and login errors are visual-only — nothing is announced to assistive technology
**WCAG:** 4.1.3 Status Messages (Level AA); 3.3.1 Error Identification (Level A, partially)
**Impact:** A screen-reader user who submits the admin login with a wrong password, or the public Contact/Book/Custom Safari/Review forms with invalid data, receives no spoken/announced feedback that anything went wrong (or right).
**Risk:** The user is left not knowing whether their submission succeeded, failed, or is still loading — a hard blocker for a booking-conversion flow.
**Root cause:**
   - Admin login: verified directly with `curl` (bypassing the browser/JS entirely) that a wrong-password submission renders:
     ```html
     <div class="login-error">Incorrect credentials. 4 attempts remaining.</div>
     ```
     `admin/login.php:404-405`, with CSS at `admin/login.php:318-326` (`.login-error`) — no `role="alert"`, no `aria-live` anywhere in the element or an ancestor. `grep -n "aria-live\|role=\"alert\""` across `admin/login.php`, `agent-portal/login.php`, `views/contact.php`, `views/book.php` returns **zero matches** in all four files.
   - Public Contact/Book forms: errors/success are injected dynamically via JS, which is worse for a live region because there's no page reload to even coincidentally re-announce content. `assets/js/main.js:204-208`:
     ```js
     function showMsg(el, text, type) {
       if (!el) return;
       el.innerHTML = `<div class="alert-sz ${type}">${text}</div>`;
       el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
     }
     ```
     The target element, `<div class="form-message" id="contact-msg">` (`views/contact.php:106`) and `id="booking-msg"` (`views/book.php:269`), has no `aria-live` or `role="status"/"alert"` attribute, so screen readers never pick up the injected text at all — the only cue given is a visual `scrollIntoView`.
**Recommended fix:** Add `role="alert"` (or `aria-live="assertive"`) to `.login-error` server-side, and `aria-live="polite"` (or `role="status"`) permanently on `#contact-msg`/`#booking-msg` so injected content is announced automatically without any JS change.
**File locations:** `admin/login.php:404-405`, `assets/js/main.js:204-208`, `views/contact.php:106`, `views/book.php:269`.

---

### F6 — [Medium] Gold accent color fails WCAG AA contrast on white — used for required-field markers, breadcrumb links, and star ratings site-wide
**WCAG:** 1.4.3 Contrast (Minimum) (Level AA)
**Impact:** The asterisk marking required fields, the breadcrumb navigation links on every page, and the star-rating glyphs are all low-contrast and hard to read for users with low vision.
**Risk:** Required-field indicators that are hard to see defeat their own purpose (silent form-validation failures); breadcrumb links that are hard to read reduce navigability for low-vision users site-wide.
**Root cause / calculation:** `--gold: #c8963e` (declared identically in `admin/css/admin.css:8` and `assets/css/main.css:55`) against `--white: #ffffff`:
   - Gold luminance L = 0.2126×0.5778 + 0.7152×0.3051 + 0.0722×0.0484 = **0.3446**
   - White luminance L = **1.0**
   - Contrast ratio = (1.0+0.05)/(0.3446+0.05) = **2.66 : 1**
   This is below both the 4.5:1 normal-text threshold *and* the 3:1 large-text/UI-component threshold. The color-override rules making gold the *actual rendered* color (later `!important` declarations win the cascade over earlier plain declarations in the same file) are:
   - `.sz-required { color: var(--gold) !important; }` — `assets/css/main.css:2001` (overrides the earlier, compliant `color: var(--black)` at line 1441) — rendered on every required-field asterisk across `views/book.php` (6×), `views/contact.php` (3×), `views/review.php` (2×), `views/home.php` (custom-safari form).
   - `.breadcrumb-sz a { color: var(--gold) !important; }` — `assets/css/main.css:2037` (overrides compliant `color: var(--black)` at line 964) — rendered on the "Home" breadcrumb link on `views/about.php:7`, `views/book.php:42`, `views/contact.php:8`, `views/packages.php:52`, `views/track.php:8`, `views/confirmation.php:90`, `views/review.php:6` — i.e. every page that has a breadcrumb.
   - `.trust-stars { color: var(--gold) !important; }` — `assets/css/main.css:1918` (overrides `#f59e0b` at line 1348).
**Recommended fix:** Either darken the accent for text use (e.g. a `--gold-text` token around `#8a6420`, which would land near 4.5:1 on white) or keep `--gold` for backgrounds/borders only and use `--black`/`--gray-700` for text that happens to sit on a light background.
**File locations:** `admin/css/admin.css:8`, `assets/css/main.css:55,1441,1918,1920,2001,2036-2037`.

---

### F7 — [Medium] Team-role caption text fails WCAG AA contrast
**WCAG:** 1.4.3 Contrast (Minimum) (Level AA)
**Impact:** Job-title captions under each team member's name on the About page (e.g. "Lead Guide") are hard to read for low-vision users.
**Root cause / calculation:** `.team-role { color: #9ca3af; }` at `assets/css/main.css:1628` (this later, non-`!important` declaration wins over the compliant `color: var(--black)` at line 1120, since both are single-class selectors and source order decides the tie) rendered on white (`views/about.php:101`, `<p class="team-role"><?= $role ?></p>`, text size 0.65rem ≈ 10.4px — not "large text"):
   - #9ca3af → R=156,G=163,B=175 → L = 0.2126×0.3321 + 0.7152×0.3661 + 0.0722×0.4297 = **0.3634**
   - Contrast ratio = 1.05 / (0.3634+0.05) = **2.54 : 1** — needs 4.5:1, fails by nearly half.
**Recommended fix:** Use `var(--gray-5)` (`#48483e`, ~9:1 on white) instead of the lighter `#9ca3af`.
**File location:** `assets/css/main.css:1628,1120` (duplicate/conflicting `.team-role` rules — the file defines this class twice with different colors, which is itself worth consolidating); `views/about.php:101`.

---

### F8 — [Medium] Row-actions kebab menu does not move focus into the open menu (incomplete ARIA menu pattern)
**WCAG:** 4.1.2 Name, Role, Value; 2.1.1 Keyboard (Level A)
**Impact:** Keyboard/screen-reader users can open the three-dot "Actions" menu (used in every admin table — leads, bookings, etc.) but focus stays on the toggle button; there's no arrow-key navigation among menu items, so a `role="menu"`/`role="menuitem"` pattern is only half-implemented.
**What does work (confirmed live, so this is a partial rather than total failure):** `ensureMenuRoles()` in `admin/js/admin.js:247-253` **does run** and correctly sets `role="menu"` on `.admin-row-actions-menu` and `role="menuitem"` on each `.admin-row-actions-item`, confirmed by inspecting the real DOM of `admin/leads.php` before any interaction:
   ```json
   "row_menu_role_before_click": ["menu", "menu", "menu"]
   ```
   The toggle button itself also correctly carries `aria-haspopup="true"` and toggles `aria-expanded` (`admin/leads.php:236`). Escape closes it (`admin/js/admin.js:297`).
**What's missing:** After clicking the toggle, the active element is still the toggle `<button>`, not the first menu item:
   ```json
   "row_menu_after_open": {"role": "menu", "display": "block",
     "itemRoles": ["menuitem","menuitem","menuitem","menuitem"],
     "activeElementTag": "BUTTON", "activeElementIsInsideMenu": false}
   ```
   `openMenu()` (`admin/js/admin.js:265-285`) only sets `display`/position styles and `aria-expanded` — it never calls `.focus()` on the first `.admin-row-actions-item`, and there is no `keydown` handler for ArrowUp/ArrowDown inside the menu, so a screen-reader user who does tab forward manually will hear "menu item" announcements but has no way to navigate them with arrow keys as the `menu`/`menuitem` role pattern implies they should.
**Recommended fix:** On open, move focus to the first `.admin-row-actions-item`; add ArrowUp/ArrowDown handling to cycle focus between items while the menu is open.
**File locations:** `admin/js/admin.js:247-301`.

---

### F9 — [Medium] Filename-used-as-alt-text in the admin image gallery
**WCAG:** 1.1.1 Non-text Content (Level A)
**Impact:** Screen-reader users browsing the gallery hear raw filenames (e.g. whatever the uploaded file was called) instead of a description of the image content.
**Root cause:** `admin/gallery.php:371-373` (server-rendered thumbnails):
   ```php
   <img src="<?= htmlspecialchars(BASE_PATH . $imgUrl) ?>"
        alt="<?= htmlspecialchars($img) ?>"
        loading="lazy">
   ```
   `$img` is the raw filename, reused verbatim as both the visible caption text (line 376-378) *and* the `alt` attribute — the exact filename-as-alt anti-pattern. The same bug is duplicated in the JS path used when an image is uploaded without a full page reload, `admin/gallery.php:612`:
   ```js
   '<img src="'+BASE+url+'" alt="'+filename+'" loading="lazy">'
   ```
**Recommended fix:** Either use `alt=""` (the filename is already shown as visible text right below the thumbnail at line 376-378, so the image is arguably decorative/redundant in this admin context) or populate a real caption field at upload time and use that for `alt`.
**File locations:** `admin/gallery.php:371-373`, `admin/gallery.php:612`.

---

### F10 — [Medium] Missing `alt` attribute entirely on package-photo thumbnails in the package editor
**WCAG:** 1.1.1 Non-text Content (Level A)
**Impact:** These `<img>` tags have no `alt` attribute at all (not even `alt=""`), which is worse than an empty one — assistive tech falls back to announcing the image's `src` URL.
**Root cause:** `admin/package_edit.php:353`:
   ```php
   <img src="<?= htmlspecialchars(BASE_PATH . $imgUrl) ?>" style="width:100%;aspect-ratio:4/3;object-fit:cover;display:block" loading="lazy">
   ```
   repeated in the two JS-driven insert paths at `admin/package_edit.php:1761` and `admin/package_edit.php:1805`.
**Recommended fix:** Add `alt=""` at minimum (thumbnails are purely decorative previews with their own delete/reorder controls next to them), or a real caption if one exists.
**File locations:** `admin/package_edit.php:353,1761,1805`.

---

### F11 — [Low] Fabricated/inaccurate alt text on the About page hero image
**WCAG:** 1.1.1 Non-text Content (Level A) — text alternative does not serve the equivalent purpose
**Impact:** The image is literally the company's logo (`assets/images/logo.jpeg`), but its `alt` text describes an entirely different, nonexistent scene. A screen-reader user is told something false about the page's content.
**Root cause:** `views/about.php:11`:
   ```html
   <img loading="lazy" src="assets/images/logo.jpeg" alt="Safari Zangu guide showing a lion to guests in the Serengeti" class="page-hero-img" width="350" height="350">
   ```
**Recommended fix:** Either replace `src` with an actual photo matching that description, or correct `alt` to describe the logo (or `alt=""` if treated as decorative, since the brand name is already in the page `<h1>`).
**File location:** `views/about.php:11`.

---

### F12 — [Low] Heading level is skipped (h1 → h3, no h2) on several public pages
**WCAG:** 1.3.1 Info and Relationships / 2.4.6 Headings and Labels (best-practice technique G141)
**Impact:** Screen-reader users navigating by heading level get a structurally inconsistent outline; not fatal, but a code smell that suggests headings were chosen for visual size rather than document structure.
**Evidence:**
   - `views/contact.php`: `h1` (line 9) → `h3` (line 25, "Send a Message") → `h3` (line 112, "Contact Details") — no `h2`.
   - `views/track.php`: `h1` (line 9) only — fine on its own, but breaks the site-wide pattern elsewhere.
   - `views/book.php`: `h1` (line 43) is the only heading on the entire page — the package selector, traveler details, etc. have no section headings at all.
   By contrast `views/home.php` and `views/about.php` nest correctly (h1→h2→h3/h4).
**Recommended fix:** Insert `h2`s for "Send a Message"/"Contact Details" sections, and add section headings to `book.php`'s form steps.
**File locations:** `views/contact.php:9,25,112`; `views/book.php:43`.

---

### F13 — [Low] Skip-to-content link fails contrast; no equivalent exists in the admin/agent panels
**WCAG:** 1.4.3 Contrast (Minimum); 2.4.1 Bypass Blocks (Level A)
**Impact:** The public site's keyboard "Skip to main content" link is functionally correct (appears on focus, targets a real `#main-content`) but its own text is hard to read; and admin/agent staff have no bypass mechanism at all.
**Root cause:**
   - `layouts/app.php:73`: `background:#c8963e;color:#fff` — white-on-gold is the same pair computed in F6: **2.66:1**, below the 4.5:1 AA threshold for this 0.8rem (non-"large") text.
   - `layouts/admin.php` and `layouts/agent.php` (both read in full) contain no skip link at all. A keyboard user on, say, `admin/leads.php` must Tab through the entire sidebar — 1 (Dashboard) + 8 (Operations) + 3 (Communications) + 8 (Catalog) + 3 (People & Partners) + 4 (Analytics) + 2 (Agent) + 2 (Gallery) + 4 (Settings) = **35 nav links**, plus the 9 group-toggle buttons, the hamburger, the notification bell, and the profile link — roughly **45+ focusable controls** — before reaching the page's actual content on *every single page load*, confirmed by reading the `$groups` array that drives the sidebar (`layouts/admin.php:90-144`).
**Recommended fix:** Fix the skip-link color pair (e.g. `color:#1a1a1a` on gold, or keep white text but switch the background to `--black`); add an equivalent `<a href="#admin-content" class="skip-nav">Skip to content</a>` as the very first focusable element in `layouts/admin.php`/`layouts/agent.php`.
**File locations:** `layouts/app.php:73,77`; `layouts/admin.php:19-207` (sidebar, no skip link); `layouts/admin.php:90-144` (`$groups` array used for the link count above).

---

## What already works (for calibration)

- **`ensureMenuRoles()`** (F8) correctly and idempotently applies `role="menu"`/`role="menuitem"` to every row-actions dropdown, confirmed live on the real DOM.
- **`confirmDialog()`/`promptDialog()`** (the custom replacements for `window.confirm()`/`window.prompt()`, `admin/js/admin.js:86-190`) have fully correct `role="alertdialog"`, `aria-modal="true"`, `aria-labelledby`, initial-focus-to-OK-button, and focus-restoration-on-close — proving the team can do this right; it just didn't carry over to the page-authored modals (F2).
- **Public-facing form labels** (`views/contact.php`, `views/book.php`, `views/review.php`, `views/home.php` custom-safari form) correctly use `<label class="form-label" for="...">` matched to real input `id`s — this is the opposite pattern from the broken admin `.admin-label` (F1), and it works.
- **`admin/login.php` / `agent-portal/login.php`** use the correct `form-label`/`for` pattern too (`admin/login.php:411,421`).
- **Mobile hamburger nav** (`layouts/navbar.php` + `assets/js/main.js:28-59`) correctly toggles `aria-expanded` on the button and `aria-hidden` on the panel, and the panel is `display:none` while closed (`assets/css/main.css:272-273`) so its links are not reachable by Tab while hidden — no "focusable but invisible" trap.
- **Viewport meta tag** (`layouts/head.php:40`) does not disable pinch-zoom (no `maximum-scale`/`user-scalable=no`), so 1.4.4/1.4.10 are not blocked at the viewport level.
- **Gold-on-black contrast** (e.g. gold text over the dark hero overlay) computes to **7.44:1**, comfortably passing AAA — the gold accent itself is fine, it's specifically gold-on-white usage (F6) that fails.
- **Skip link mechanism** on the public site is structurally sound (appears on focus via `onfocus`/`onblur`, targets a real landmark) — only its color pair is wrong (F13).

---

## Accessibility Score: 42 / 100

### Justification

**Criteria checked (18 relevant WCAG 2.2 success criteria, across both A and AA):**
1.1.1 Non-text Content — **FAIL** (F9, F10, F11)
1.3.1 Info and Relationships — **FAIL** (F1, F12)
1.4.3 Contrast (Minimum) — **FAIL** (F6, F7, F13)
1.4.4 Resize Text — PASS
1.4.10 Reflow — PASS (not independently re-verified beyond viewport meta, scoped out)
2.1.1 Keyboard — **PARTIAL FAIL** (F2, F8 — reachable but not fully operable per pattern)
2.4.1 Bypass Blocks — **PARTIAL FAIL** (F13 — exists on public site only)
2.4.3 Focus Order — PASS (tab order through admin login and public contact form was logical/sequential)
2.4.6 Headings and Labels — **FAIL** (F12)
2.4.7 Focus Visible — **FAIL** (F3, F4 — admin panel and public site both)
3.3.1 Error Identification — **PARTIAL FAIL** (text identifies the error, F5, but isn't reliably perceivable by AT)
3.3.2 Labels or Instructions — **FAIL** (F1 — visually present, not programmatically associated)
4.1.2 Name, Role, Value — **FAIL** (F1, F2, F8)
4.1.3 Status Messages — **FAIL** (F5)
2.5.3 Label in Name — not independently tested (scoped out)
1.4.11 Non-Text Contrast — not independently tested beyond F6/F13's text cases (scoped out)
2.4.11 Focus Not Obscured (WCAG 2.2) — not independently tested (scoped out)
2.5.8 Target Size Minimum (WCAG 2.2) — not independently tested (scoped out)

**Of the 14 criteria actually exercised with live evidence, 10 fail outright or partially, 4 pass.** That 71% criterion-failure rate, weighted down further by the fact that three of the failures are **Critical** and hit the primary admin workflow (F1, F2) rather than edge cases, drives the score to the low end of the "Poor" band. The score is not lower only because: (a) the public-facing site's basic label-association and heading structure are *mostly* sound (the breakage is concentrated in the admin/agent back office), (b) a working ARIA-menu helper and a working custom-dialog helper already exist in the codebase, proving the pattern is known and just needs to be applied consistently, and (c) no outright keyboard traps or `display:none`-while-focusable issues were found anywhere.

**Priority order for remediation:** F1 and F2 first (they make the entire admin panel's primary forms unusable via screen reader), then F3/F4 (focus visibility — cheap, mechanical CSS fixes with outsized impact), then F5 (status messages), then the contrast fixes (F6/F7/F13), then the alt-text and heading cleanups (F9-F12).
