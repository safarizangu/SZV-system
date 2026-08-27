#!/bin/bash
# Self-contained restore script — all clean content embedded
# Run: sudo bash restore_clean_files.sh
set -e
SERVER=/var/www/html/safari-zangu
echo "=================================================="
echo "  RESTORING CLEAN FILES FROM ZIP SOURCE"
echo "=================================================="

echo '  Writing: css/main.css'
mkdir -p $(dirname $SERVER/css/main.css)
cat > $SERVER/css/main.css << 'FILEOF'
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,700;1,400;1,500&family=Jost:wght@300;400;500;600;700&display=swap');

/* ============================================================
   SAFARI ZANGU VENTURES — Main Stylesheet
   Design: Editorial safari luxury — ink on paper
   Fonts:  Playfair Display (display) × Jost (body/UI)
   Palette: Pure black / white / structured grays only
   ============================================================ */

/* ── TOKENS ────────────────────────────────────────────────── */
:root {
  --black:      #0a0a0a;
  --black-soft: #1e1e1e;
  --white:      #ffffff;
  --off-white:  #f8f8f6;
  --gray-1:     #f3f3f1;
  --gray-2:     #e6e6e2;
  --gray-3:     #c4c4be;
  --gray-4:     #888880;
  --gray-5:     #48483e;

  --font-display: 'Playfair Display', Georgia, serif;
  --font-body:    'Jost', system-ui, Helvetica, sans-serif;

  --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
  --ease-in:  cubic-bezier(0.4, 0, 1, 1);
  --dur:      0.28s;

  --nav-h:     72px;
  --max-w:     1320px;
  --max-width: 1320px; /* alias for inline-style compatibility */
  --pad:       clamp(1.25rem, 4vw, 2.5rem);

  --text-xs:   0.65rem;
  --text-sm:   0.78rem;
  --text-base: 0.925rem;
  --text-md:   1.05rem;
}

/* ── RESET ─────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }

body {
  background: var(--white);
  color: var(--black);
  font-family: var(--font-body);
  font-weight: 400;
  font-size: 16px;
  line-height: 1.72;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  overflow-x: hidden;
}

img    { display: block; max-width: 100%; height: auto; }
a      { color: inherit; }
button { font-family: var(--font-body); }

/* ── TYPOGRAPHY ─────────────────────────────────────────────── */
h1,h2,h3,h4,h5,h6 {
  font-family: var(--font-display);
  font-weight: 500;
  line-height: 1.15;
  color: var(--black);
  letter-spacing: -0.01em;
}

.display-1 { font-size: clamp(2.6rem, 5.5vw, 4.8rem); line-height: 1.05; letter-spacing: -0.02em; }
.display-2 { font-size: clamp(2rem,   4vw,   3.4rem); line-height: 1.1;  letter-spacing: -0.015em; }
.display-3 { font-size: clamp(1.5rem, 2.8vw, 2.4rem); }

.section-label {
  font-family: var(--font-body);
  font-weight: 600;
  font-size: var(--text-xs);
  letter-spacing: 0.28em;
  text-transform: uppercase;
  color: var(--black);
  display: block;
  margin-bottom: 0.85rem;
}

.eyebrow {
  font-family: var(--font-body);
  font-size: var(--text-xs);
  font-weight: 600;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--black);
  display: block;
}

/* ── LAYOUT ─────────────────────────────────────────────────── */
.section    { padding: clamp(4rem, 8vw,  7rem) var(--pad); }
.section-sm { padding: clamp(3rem, 6vw,  5rem) var(--pad); }
.section-lg { padding: clamp(5rem, 10vw, 9rem) var(--pad); }

.container-sz { max-width: var(--max-w); margin: 0 auto; }

.section-header              { margin-bottom: clamp(2.5rem, 5vw, 4rem); }
.section-header.centered     { text-align: center; }

.divider-line {
  width: 48px; height: 1px;
  background: var(--black);
  margin: 1.25rem 0;
  transition: width 0.55s var(--ease-out);
}
.section-header.centered .divider-line { margin: 1.25rem auto; }
.section-header:hover .divider-line    { width: 88px; }

/* ── NAVIGATION ─────────────────────────────────────────────── */
.sz-nav {
  position: fixed;
  inset: 0 0 auto 0;
  z-index: 1000;
  height: var(--nav-h);
  background: var(--white);
  border-bottom: 1px solid var(--gray-2);
  transition: background var(--dur) ease, border-color var(--dur) ease, box-shadow var(--dur) ease;
}
.sz-nav.over-hero { background: transparent; border-bottom-color: transparent; }
.sz-nav.scrolled  { background: var(--white); border-bottom-color: var(--gray-2); box-shadow: 0 2px 24px rgba(0,0,0,0.07); }

.nav-inner {
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 0 var(--pad);
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 2rem;
}

.sz-logo {
  font-family: var(--font-display);
  font-size: 1.85rem;
  font-weight: 700;
  color: var(--black);
  text-decoration: none;
  letter-spacing: 0.04em;
  line-height: 1;
  flex-shrink: 0;
  transition: color var(--dur) ease;
}
.sz-nav.over-hero .sz-logo { color: var(--white); }
.sz-nav.scrolled  .sz-logo { color: var(--black); font-weight: 500; }

.sz-nav-links {
  display: flex;
  align-items: center;
  gap: clamp(1.25rem, 2.5vw, 2.25rem);
  list-style: none;
}
.sz-nav-links a {
  font-size: var(--text-xs);
  font-weight: 600;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--black);
  text-decoration: none;
  white-space: nowrap;
  position: relative;
  padding-bottom: 3px;
  transition: color var(--dur) ease;
}
.sz-nav-links a::after {
  content: '';
  position: absolute;
  bottom: 0; left: 0;
  width: 0; height: 1px;
  background: currentColor;
  transition: width 0.3s var(--ease-out);
}
.sz-nav-links a:hover::after { width: 100%; }
.sz-nav.over-hero .sz-nav-links a { color: rgba(255,255,255,0.9); }
.sz-nav.scrolled  .sz-nav-links a { color: var(--black); font-weight: 500; }


/* WhatsApp nav link */
.sz-nav-wa {
  color: #25D366 !important;
  font-weight: 700 !important;
  border: 1.5px solid #25D366;
  padding: 0.4rem 0.9rem !important;
  transition: background 0.2s ease, color 0.2s ease !important;
}
.sz-nav-wa:hover { background: #25D366 !important; color: #fff !important; }
.sz-nav-wa::after { display: none !important; }
.sz-nav.over-hero .sz-nav-wa { color: #25D366 !important; border-color: #25D366; }

.sz-nav-cta {
  background: var(--black) !important;
  color: var(--white) !important;
  padding: 0.55rem 1.35rem !important;
  border: 1.5px solid var(--black) !important;
  font-size: var(--text-xs) !important;
  font-weight: 700 !important;
  letter-spacing: 0.14em !important;
  border-radius: 0;
  transition: background var(--dur) ease, color var(--dur) ease, border-color var(--dur) ease !important;
  white-space: nowrap;
}
.sz-nav-cta::after { display: none !important; }
.sz-nav-cta:hover  { background: var(--black-soft) !important; border-color: var(--black-soft) !important; }
.sz-nav.over-hero .sz-nav-cta       { background: rgba(255,255,255,0.12) !important; color: var(--white) !important; border-color: rgba(255,255,255,0.55) !important; }
.sz-nav.over-hero .sz-nav-cta:hover { background: var(--white) !important; color: var(--black) !important; border-color: var(--white) !important; }
.sz-nav.scrolled  .sz-nav-cta       { background: var(--black) !important; color: var(--white) !important; border-color: var(--black) !important; }

/* Mobile toggle */
.nav-toggle {
  display: none;
  flex-direction: column;
  justify-content: center;
  gap: 6px;
  cursor: pointer;
  background: none;
  border: none;
  padding: 6px;
  width: 42px; height: 42px;
  flex-shrink: 0;
}
.nav-toggle span {
  display: block;
  width: 24px; height: 2px;
  background: var(--black);
  border-radius: 1px;
  transition: all 0.3s var(--ease-out);
  transform-origin: center;
}
.sz-nav.over-hero .nav-toggle span        { background: var(--white); }
.sz-nav.scrolled  .nav-toggle span        { background: var(--black); }
.nav-toggle.is-open span:nth-child(1)     { transform: translateY(8px) rotate(45deg); }
.nav-toggle.is-open span:nth-child(2)     { opacity: 0; transform: scaleX(0); }
.nav-toggle.is-open span:nth-child(3)     { transform: translateY(-8px) rotate(-45deg); }

/* Mobile nav drawer */
.sz-mobile-nav {
  display: none;
  position: fixed;
  top: var(--nav-h);
  left: 0; right: 0;
  background: var(--white);
  border-bottom: 1px solid var(--gray-2);
  box-shadow: 0 8px 32px rgba(0,0,0,0.1);
  z-index: 998;
}
.sz-mobile-nav.open { display: block; }
.sz-mobile-nav a {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem var(--pad);
  font-size: var(--text-sm);
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--black);
  text-decoration: none;
  border-bottom: 1px solid var(--gray-2);
  transition: background var(--dur) ease, padding-left var(--dur) ease;
  min-height: 52px;
}
.sz-mobile-nav a:not(:last-child):hover { background: var(--gray-1); padding-left: calc(var(--pad) + 6px); }
.sz-mobile-nav a:last-child {
  border-bottom: none;
  background: var(--black);
  color: var(--white);
  justify-content: center;
  letter-spacing: 0.2em;
  padding: 1.1rem var(--pad);
  min-height: 56px;
}
.sz-mobile-nav a:last-child:hover { background: var(--black-soft); }

/* ── HERO ───────────────────────────────────────────────────── */
.hero {
  position: relative;
  height: 100svh;
  min-height: 640px;
  display: flex;
  align-items: flex-end;
  overflow: hidden;
}
.hero-bg {
  position: absolute;
  inset: 0;
  background-image: url('../assets/images/packages/short-safari/9.jpeg');
  background-size: cover;
  background-position: center 35%;
  transform: scale(1.06);
  transition: transform 9s var(--ease-out);
  will-change: transform;
}
.hero-bg.loaded { transform: scale(1); }

.hero-overlay {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(180deg, rgba(0,0,0,0.52) 0%, rgba(0,0,0,0.05) 35%, transparent 60%),
    linear-gradient(0deg,   rgba(0,0,0,0.78) 0%, rgba(0,0,0,0.2)  45%, transparent 100%);
}
.hero-content {
  position: relative;
  z-index: 2;
  max-width: var(--max-w);
  margin: 0 auto;
  padding: 0 var(--pad) clamp(3.5rem, 7vw, 6rem);
  width: 100%;
}
.hero-tag {
  font-size: clamp(0.68rem, 1.4vw, 0.88rem);
  font-weight: 700;
  letter-spacing: 0.32em;
  text-transform: uppercase;
  color: rgba(255,255,255,255);
  display: block;
  margin-bottom: 1.25rem;
}
.hero-title {
  font-family: var(--font-display);
  font-size: clamp(2.8rem, 6.5vw, 5.8rem);
  font-weight: 400;
  color: var(--white);
  line-height: 1.03;
  margin-bottom: 1.1rem;
  max-width: 720px;
  letter-spacing: -0.02em;
  text-shadow: 0 2px 40px rgba(0,0,0,0.3);
}
.hero-title em { font-style: italic; }
.hero-sub {
  font-size: clamp(0.68rem, 1.4vw, 0.88rem);
  font-weight: 500;
  color: rgba(255,255,255,255);
  letter-spacing: 0.3em;
  text-transform: uppercase;
  margin-bottom: 2.5rem;
}
.hero-actions { display: flex; flex-wrap: wrap; gap: 0.85rem; align-items: center; }
.hero-scroll {
  position: absolute;
  bottom: clamp(1.5rem, 3vw, 2.5rem);
  right: var(--pad);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  color: rgba(255,255,255,0.4);
  font-size: 0.58rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  user-select: none;
}
.scroll-line {
  width: 1px; height: 40px;
  background: linear-gradient(to bottom, transparent, rgba(255,255,255,0.5));
  animation: scrollPulse 2s ease-in-out infinite;
}
@keyframes scrollPulse {
  0%,100% { opacity:0.4; transform:scaleY(0.7); }
  50%      { opacity:1;   transform:scaleY(1); }
}

/* ── BUTTONS ────────────────────────────────────────────────── */
.btn-sz-primary, .btn-sz-dark, .btn-sz-outline, .btn-sz-ghost {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  font-family: var(--font-body);
  font-size: var(--text-xs);
  font-weight: 700;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  text-decoration: none;
  cursor: pointer;
  white-space: nowrap;
  border-radius: 0;
  transition: background var(--dur) ease, color var(--dur) ease, border-color var(--dur) ease, transform 0.12s ease;
  -webkit-tap-highlight-color: transparent;
  min-height: 48px;
  padding: 0.8rem 2rem;
  border-width: 1.5px;
  border-style: solid;
}
.btn-sz-primary:active, .btn-sz-dark:active, .btn-sz-outline:active, .btn-sz-ghost:active { transform: scale(0.97); }

.btn-sz-primary { background: var(--white);       color: var(--black); border-color: var(--white); }
.btn-sz-primary:hover { background: transparent; color: var(--white); }

.btn-sz-dark    { background: var(--black);       color: var(--white); border-color: var(--black); }
.btn-sz-dark:hover { background: var(--black-soft); border-color: var(--black-soft); }

.btn-sz-outline { background: transparent;        color: var(--black); border-color: var(--black); }
.btn-sz-outline:hover { background: var(--black); color: var(--white); }

.btn-sz-ghost   { background: transparent; color: rgba(255,255,255,0.85); border-color: rgba(255,255,255,0.4); }
.btn-sz-ghost:hover { background: rgba(255,255,255,0.1); color: var(--white); border-color: rgba(255,255,255,0.8); }

.btn-full { width: 100%; }
.book-now-link { display: inline-flex; align-items: center; justify-content: center; }

/* ── STATS BAR ──────────────────────────────────────────────── */
.stats-bar {
  background: var(--black);
  color: var(--white);
  padding: clamp(2.5rem, 5vw, 4rem) var(--pad);
}
.stats-grid {
  max-width: var(--max-w);
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 2rem;
  text-align: center;
}
.stat-number {
  font-family: var(--font-display);
  font-size: clamp(2.2rem, 4vw, 3rem);
  font-weight: 400;
  font-style: italic;
  display: block;
  margin-bottom: 0.4rem;
  color: var(--white);
}
.stat-label {
  font-size: var(--text-xs);
  font-weight: 500;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: rgba(255,255,255,0.45);
  display: block;
}

/* ── PACKAGE CARDS ──────────────────────────────────────────── */
.packages-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  border: 1.5px solid var(--black);
}
.pkg-card {
  padding: clamp(1.75rem, 3vw, 2.5rem) clamp(1.5rem, 2.5vw, 2rem);
  border-right: 1.5px solid var(--black);
  border-bottom: 1.5px solid var(--black);
  background: var(--white);
  position: relative;
  overflow: hidden;
  transition: background var(--dur) ease;
  display: flex;
  flex-direction: column;
}
.pkg-card:nth-child(3n)        { border-right: none; }
.pkg-card:nth-last-child(-n+3) { border-bottom: none; }
.pkg-card::after {
  content: '';
  position: absolute;
  bottom: 0; left: 0;
  width: 0; height: 2.5px;
  background: var(--black);
  transition: width 0.45s var(--ease-out);
}
.pkg-card:hover::after { width: 100%; }
.pkg-card:hover        { background: var(--gray-1); }

.pkg-number   { font-family: var(--font-display); font-size: 2.6rem; font-weight: 400; font-style: italic; color: var(--black); line-height: 1; margin-bottom: 0.85rem; user-select: none; }
.pkg-title    { font-family: var(--font-display); font-size: clamp(1.2rem, 2vw, 1.42rem); font-weight: 500; color: var(--black); margin-bottom: 0.2rem; line-height: 1.2; }
.pkg-duration { font-size: var(--text-xs); font-weight: 600; letter-spacing: 0.18em; text-transform: uppercase; color: var(--black); margin-bottom: 0.85rem; }
.pkg-desc     { font-size: var(--text-base); font-weight: 400; color: var(--black); line-height: 1.65; margin-bottom: 1.35rem; flex: 1; }
.pkg-price    { font-family: var(--font-display); font-size: 1.55rem; font-weight: 400; color: var(--black); margin-bottom: 0.15rem; line-height: 1.2; }
.pkg-price-label { font-size: var(--text-xs); color: var(--black); letter-spacing: 0.1em; font-weight: 500; text-transform: uppercase; margin-bottom: 1.35rem; }

/* ── WHY SECTION ────────────────────────────────────────────── */
.why-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: clamp(2rem, 4vw, 3.5rem);
  border-top: 1.5px solid var(--gray-2);
  padding-top: clamp(2.5rem, 4vw, 3.5rem);
}
.why-item  { display: flex; flex-direction: column; }
.why-icon  { width: 34px; height: 34px; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center; border: 1.5px solid var(--black); flex-shrink: 0; }
.why-title { font-family: var(--font-display); font-size: 1.12rem; font-weight: 500; margin-bottom: 0.6rem; color: var(--black); }
.why-text  { font-size: var(--text-base); font-weight: 400; color: var(--black); line-height: 1.7; }

/* ── CUSTOM SAFARI ──────────────────────────────────────────── */
.custom-safari {
  background: var(--off-white);
  border-top: 1px solid var(--gray-2);
  border-bottom: 1px solid var(--gray-2);
}
.responsive-two-col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: clamp(3rem, 6vw, 6rem);
  align-items: start;
}

/* ── FORMS ──────────────────────────────────────────────────── */
.form-group { margin-bottom: 1.35rem; }
.form-label {
  display: block;
  font-size: var(--text-xs);
  font-weight: 600;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--black);
  margin-bottom: 0.5rem;
  line-height: 1;
}
.form-control-sz {
  width: 100%;
  background: var(--white);
  border: 1.5px solid var(--gray-3);
  border-radius: 0;
  padding: 0.9rem 1rem;
  font-family: var(--font-body);
  font-size: var(--text-base);
  font-weight: 400;
  color: var(--black);
  outline: none;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
  -webkit-appearance: none;
  appearance: none;
  min-height: 48px;
}
.form-control-sz:focus       { border-color: var(--black); font-weight: 500; box-shadow: 0 0 0 3px rgba(10,10,10,0.07); }
.form-control-sz::placeholder { color: var(--black); font-weight: 500; }
select.form-control-sz {
  color: var(--black); font-weight: 500; cursor: pointer;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%230a0a0a' d='M5 6L0 0h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 1rem center; padding-right: 2.75rem;
}
textarea.form-control-sz { resize: vertical; min-height: 130px; line-height: 1.6; }

.phone-field-row                      { display: flex; gap: 0.5rem; }
.phone-field-row .phone-code-select   { flex: 0 0 140px; min-width: 0; }
.phone-field-row .phone-number-input  { flex: 1; min-width: 0; }

.form-row   { display: grid; grid-template-columns: 1fr 1fr;     gap: 1.25rem; }
.form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.25rem; }
.form-message { margin-top: 1rem; }
.form-message:empty { display: none; }

.alert-sz { padding: 1rem 1.25rem; font-size: var(--text-base); border-left: 3px solid; margin-top: 1rem; line-height: 1.55; }
.alert-sz.success { background: #f0f7f0; border-color: #2a7a2a; color: #174f17; }
.alert-sz.error   { background: #fdf0f0; border-color: #c22020; color: #6b1111; }

/* ── TESTIMONIALS ───────────────────────────────────────────── */
.testimonial-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: clamp(1.5rem, 3vw, 2.5rem);
}
.testimonial {
  padding: clamp(1.75rem, 3vw, 2.5rem);
  border: 1.5px solid var(--gray-2);
  position: relative;
  display: flex;
  flex-direction: column;
  transition: border-color var(--dur) ease;
}
.testimonial:hover { border-color: var(--black); }
.testimonial-quote-mark { font-family: var(--font-display); font-size: 4.5rem; font-style: italic; color: var(--gray-2); line-height: 0.8; margin-bottom: 1rem; user-select: none; }
.testimonial-text   { font-size: var(--text-base); font-weight: 400; font-style: italic; color: var(--black); line-height: 1.75; margin-bottom: 1.5rem; flex: 1; }
.testimonial-author { font-size: var(--text-xs); font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--black); margin-bottom: 0.2rem; }
.testimonial-origin { font-size: var(--text-xs); font-weight: 400; color: var(--black); letter-spacing: 0.06em; }

/* ── CTA BANNER ─────────────────────────────────────────────── */
.cta-banner { background: var(--black); padding: clamp(4rem, 8vw, 7rem) var(--pad); text-align: center; }
.cta-banner .section-label { color: rgba(255,255,255,0.3); }
.cta-banner-title   { color: var(--white); margin: 1rem 0 2.5rem; }
.cta-banner-actions { display: flex; flex-wrap: wrap; gap: 1rem; justify-content: center; }

/* ── FOOTER ─────────────────────────────────────────────────── */
/* ══════════════════════════════════════════════════════════
   FOOTER — full responsive system
   ══════════════════════════════════════════════════════════ */

.sz-footer {
  background: #0a0a0a;
  color: #fff;
  padding: clamp(3.5rem, 8vw, 5.5rem) var(--pad) 0;
}
.footer-inner {
  max-width: var(--max-w);
  margin: 0 auto;
}

/* Grid: brand takes 1.8fr, 3 nav cols take 1fr each */
.footer-grid {
  display: grid;
  grid-template-columns: 1.8fr 1fr 1fr 1fr;
  gap: clamp(2rem, 4vw, 3.5rem);
  padding-bottom: clamp(3rem, 5vw, 4rem);
  border-bottom: 1px solid rgba(255,255,255,.1);
  margin-bottom: 0;
  align-items: start;
}

/* ── Brand column ─────────────────────────────── */
.footer-brand-col {}

.footer-brand-name {
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-weight: 500;
  font-style: italic;
  color: #fff;
  letter-spacing: .02em;
  margin-bottom: .85rem;
  line-height: 1.15;
}

.footer-brand-desc {
  font-size: .82rem;
  color: rgba(255,255,255,.9);
  font-weight: 400;
  line-height: 1.8;
  max-width: 300px;
  margin-bottom: 1.5rem;
}

.footer-brand-actions {
  display: flex;
  flex-direction: column;
  gap: .65rem;
  margin-bottom: 1.5rem;
  max-width: 220px;
}

/* WhatsApp button */
.footer-wa-btn {
  display: inline-flex;
  align-items: center;
  gap: .55rem;
  padding: .7rem 1.1rem;
  background: #16a34a;
  color: #fff !important;
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
  text-decoration: none;
  transition: background .18s;
  border-radius: 2px;
}
.footer-wa-btn:hover { background: #15803d; }
.footer-wa-btn svg {
  width: 16px; height: 16px;
  fill: #fff;
  flex-shrink: 0;
}

/* Book a Safari ghost button */
.footer-book-btn {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .65rem 1.1rem;
  border: 1.5px solid rgba(255,255,255,.6);
  color: #fff !important;
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
  text-decoration: none;
  transition: border-color .18s, color .18s;
  border-radius: 2px;
}
.footer-book-btn:hover { border-color: #fff; color: #fff !important; }

/* Licence line */
.footer-licence {
  display: flex;
  align-items: center;
  gap: .5rem;
  flex-wrap: wrap;
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .06em;
  color: rgba(255,255,255,.6);
}
.footer-licence-dot { opacity: .35; }

/* ── Nav columns ──────────────────────────────── */
.footer-nav-col {}

.footer-col-title {
  display: block;
  font-size: .58rem;
  font-weight: 700;
  letter-spacing: .22em;
  text-transform: uppercase;
  color: rgba(255,255,255,.7);
  margin-bottom: 1.1rem;
}

.footer-links {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: .6rem;
}
.footer-links a {
  font-size: .82rem;
  color: #fff;
  font-weight: 500;
  text-decoration: none;
  transition: color .18s;
  display: inline-block;
}
.footer-links a:hover { color: #fff; }

/* Contact list */
.footer-contact-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: .75rem;
  margin-bottom: 1.25rem;
}
.footer-contact-item {
  display: flex;
  align-items: flex-start;
  gap: .55rem;
  font-size: .82rem;
  color: #fff;
  font-weight: 400;
  line-height: 1.5;
}
.footer-contact-item a {
  color: #fff;
  text-decoration: none;
  transition: color .18s;
}
.footer-contact-item a:hover { color: #fff; }
.footer-contact-icon {
  flex-shrink: 0;
  font-size: .85rem;
  margin-top: .05rem;
  opacity: 1;
}
.footer-contact-item small {
  font-size: .7rem;
  opacity: .65;
}
.footer-response-note {
  font-size: .7rem;
  color: rgba(255,255,255,.65);
  line-height: 1.6;
  padding-top: .85rem;
  border-top: 1px solid rgba(255,255,255,.07);
}

/* ── Bottom bar ───────────────────────────────── */
.footer-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: .65rem;
  padding: 1.35rem 0;
  font-size: .7rem;
  color: rgba(255,255,255,.6);
  font-weight: 500;
  letter-spacing: .05em;
}
.footer-copy { color: rgba(255,255,255,.6); }
.footer-bottom-links {
  display: flex;
  align-items: center;
  gap: .75rem;
  flex-wrap: wrap;
}
.footer-bottom-links a {
  color: rgba(255,255,255,.85);
  text-decoration: none;
  transition: color .18s;
}
.footer-bottom-links a:hover { color: #fff; }
.footer-bottom-sep { opacity: .25; }

/* ═══════════════════════════════════════════════
   FOOTER RESPONSIVE
   ═══════════════════════════════════════════════ */

/* Tablet: 2-column */
@media (max-width: 900px) {
  /* footer responsive handled in footer section */
  .footer-brand-col {
    grid-column: 1 / -1; /* brand spans full width on tablet */
  }
  .footer-brand-desc  { max-width: 100%; }
  .footer-brand-actions { flex-direction: row; max-width: 100%; }
  
}

/* Mobile: single column — everything stacked, left-aligned */
@media (max-width: 540px) {
  .sz-footer {
    padding-top: 3rem;
  }
  
  .footer-brand-col {
    grid-column: 1;
  }
  .footer-brand-name  { font-size: 1.35rem; }
  .footer-brand-desc  { font-size: .8rem; }
  .footer-brand-actions {
    flex-direction: column;
    max-width: 100%;
  }
  .footer-wa-btn,
  .footer-book-btn {
    justify-content: center;
    text-align: center;
    width: 100%;
    padding: .85rem 1rem;
    font-size: .72rem;
  }
  .footer-col-title { margin-bottom: .85rem; }
  .footer-links     { gap: .7rem; }
  .footer-links a   { font-size: .85rem; padding: .1rem 0; }
  .footer-contact-item { font-size: .85rem; }
  
  .footer-bottom-links {
    flex-wrap: wrap;
    gap: .5rem .75rem;
  }
  .footer-licence {
    flex-direction: column;
    align-items: flex-start;
    gap: .15rem;
  }
  .footer-licence-dot { display: none; }
}

/* ── Keep all footer text white (override any stray rules) ── */
.sz-footer, .sz-footer p, .sz-footer span,
.sz-footer li, .sz-footer small { color: inherit; }

/* ── PAGE HERO (inner pages) ────────────────────────────────── */
.page-hero {
  padding: clamp(7rem, 12vw, 10rem) var(--pad) clamp(3rem, 5vw, 5rem);
  border-bottom: 1px solid var(--gray-2);
}
.page-hero-inner {
  max-width: var(--max-w);
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: clamp(2rem, 4vw, 4rem);
  align-items: end;
}
.page-hero-img {
  width: 100%;
  max-width: 340px;
  height: auto;
  object-fit: cover;
  border: 1px solid var(--white);
}

.page-hero-row   { display: grid; grid-template-columns: 1fr 1fr;      }


/* ── BREADCRUMB ─────────────────────────────────────────────── */
.breadcrumb-sz {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  font-size: var(--text-xs);
  font-weight: 500;
  letter-spacing: 0.1em;
  color: var(--black);
  margin-bottom: 0.75rem;
}
.breadcrumb-sz a { color: var(--black); font-weight: 500; text-decoration: none; transition: color 0.2s; }
.breadcrumb-sz a:hover { color: var(--black); }

/* ── CONFIRMATION BOX ───────────────────────────────────────── */
.confirmation-box { border: 1.5px solid var(--gray-2); padding: clamp(1.75rem, 4vw, 3rem); max-width: 620px; margin: 0 auto; }
.booking-ref-display {
  font-family: var(--font-display);
  font-size: clamp(1.5rem, 4vw, 2.3rem);
  font-style: italic;
  letter-spacing: 0.04em;
  background: var(--gray-1);
  border: 1.5px solid var(--gray-2);
  padding: 0.85rem 1.5rem;
  display: inline-block;
  margin: 0.75rem 0;
  word-break: break-all;
  line-height: 1.3;
}

/* ── STATUS BADGES ──────────────────────────────────────────── */
.status-badge     { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.38rem 0.9rem; font-size: var(--text-xs); font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; border: 1.5px solid currentColor; border-radius: 2px; }
.status-pending   { color: #7a5a10; background: #fdf8ec; }
.status-deposit   { color: #1a4d7a; background: #edf4fc; }
.status-paid      { color: #1a5c1a; background: #edfaed; }
.status-cancelled { color: #6b1616; background: #faeaea; }

/* ── PACKAGE DETAIL PAGE ────────────────────────────────────── */
.pkg-detail-grid { display: grid; grid-template-columns: 1fr 360px; gap: clamp(2.5rem, 5vw, 4.5rem); align-items: start; }

.itinerary-day { display: grid; grid-template-columns: 52px 1fr; gap: 1.25rem; padding: 1.75rem 0; border-bottom: 1px solid var(--gray-2); align-items: start; }
.itinerary-day:last-child { border-bottom: none; }
.itinerary-day-num   { font-family: var(--font-display); font-size: 1.8rem; font-style: italic; color: var(--black); font-weight: 500; line-height: 1; }
.itinerary-day-title { font-family: var(--font-display); font-size: 1.1rem; font-weight: 500; margin-bottom: 0.4rem; }

.includes-list, .excludes-list { list-style: none; }
.includes-list li, .excludes-list li { display: flex; align-items: flex-start; gap: 0.65rem; padding: 0.6rem 0; border-bottom: 1px solid var(--gray-2); font-size: var(--text-base); color: var(--black); font-weight: 500; line-height: 1.5; }
.includes-list li:last-child, .excludes-list li:last-child { border-bottom: none; }
.includes-list li::before { content: '✓'; font-weight: 700; color: var(--black); flex-shrink: 0; margin-top: 1px; }
.excludes-list li::before { content: '×'; font-weight: 700; color: var(--black); flex-shrink: 0; margin-top: 1px; }

.pricing-table { border: 1.5px solid var(--gray-2); }
.pricing-row { display: grid; grid-template-columns: 1fr 1fr; padding: 0.9rem 1.25rem; border-bottom: 1px solid var(--gray-2); align-items: center; font-size: var(--text-base); }
.pricing-row:last-child { border-bottom: none; }
.pricing-row.header { background: var(--black); color: var(--white); font-size: var(--text-xs); font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; }
.price-cell { font-family: var(--font-display); font-size: 1.1rem; font-weight: 400; }

.pkg-section       { padding: 4.5rem 0; border-bottom: 1px solid var(--gray-2); }
.pkg-section:last-child { border-bottom: none; }
.pkg-section-num   { font-family: var(--font-display); font-size: 5rem; font-style: italic; color: var(--black); font-weight: 500; line-height: 1; display: block; margin-bottom: .5rem; }
.pkg-section-header { margin-bottom: 2.5rem; }
.include-exclude   { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem; }

.pkg-gallery {
  display: grid;
  grid-template-columns: 2fr 1fr;
  grid-template-rows: 1fr 1fr;
  gap: 6px;
  margin-bottom: 2.5rem;
  height: 360px;
}


.pkg-nav {
  display: flex;
  gap: 0;
  overflow-x: auto;
  border: 1px solid var(--gray-2);
  margin-bottom: 4rem;
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.pkg-nav::-webkit-scrollbar { display: none; }
.pkg-nav a {
  padding: .85rem 1.35rem;
  font-size: var(--text-xs);
  font-weight: 600;
  letter-spacing: .12em;
  text-transform: uppercase;
  white-space: nowrap;
  border-right: 1px solid var(--gray-2);
  color: var(--black);
  text-decoration: none;
  transition: background .2s ease, color .2s ease;
  flex-shrink: 0;
}
.pkg-nav a:last-child        { border-right: none; }
.pkg-nav a:hover,
.pkg-nav a.active             { background: var(--black); color: var(--white); }
.active-link::after           { width: 100% !important; }

/* ── BOOKING & SIDEBAR ──────────────────────────────────────── */
.booking-sidebar-col {
  position: sticky;
  top: calc(var(--nav-h) + 1.5rem);
  max-height: calc(100vh - var(--nav-h) - 3rem);
  overflow-y: auto;
  align-self: start;
  scrollbar-width: thin;
  scrollbar-color: var(--gray-2) transparent;
}
.booking-sidebar-col::-webkit-scrollbar       { width: 4px; }
.booking-sidebar-col::-webkit-scrollbar-thumb { background: var(--gray-2); border-radius: 2px; }
.booking-widget      { position: relative; border: 1.5px solid var(--gray-2); padding: clamp(1.5rem, 3vw, 2rem); }
.widget-price        { font-family: var(--font-display); font-size: 2.2rem; font-weight: 400; font-style: italic; color: var(--black); line-height: 1.15; margin-bottom: 0.2rem; }
.widget-price-note   { font-size: var(--text-xs); color: var(--black); letter-spacing: 0.1em; text-transform: uppercase; font-weight: 500; padding-bottom: 1.25rem; margin-bottom: 1.25rem; border-bottom: 1px solid var(--gray-2); }
.booking-layout      { display: grid; grid-template-columns: 1fr 380px; gap: clamp(3rem, 5vw, 5rem); align-items: start; }

/* Accommodation selector */
.acc-selector { display: flex; border: 1.5px solid var(--gray-2); margin-bottom: 1.25rem; overflow: hidden; }
.acc-selector input { display: none; }
.acc-selector label {
  flex: 1;
  text-align: center;
  padding: 0.7rem 0.3rem;
  font-size: var(--text-xs);
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  cursor: pointer;
  border-right: 1.5px solid var(--gray-2);
  transition: background 0.2s ease, color 0.2s ease;
  user-select: none;
  min-height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.acc-selector label:last-child { border-right: none; }
.acc-selector input:checked + label,
.acc-selector label.active     { background: var(--black); color: var(--white); }

.acc-selector-pkg              { display: flex; border: 1px solid var(--gray-2); margin-bottom: 1.5rem; }
.acc-selector-pkg label        { flex: 1; text-align: center; padding: .85rem .5rem; font-size: var(--text-xs); font-weight: 600; letter-spacing: .08em; text-transform: uppercase; cursor: pointer; border-right: 1px solid var(--gray-2); transition: background .2s, color .2s; user-select: none; }
.acc-selector-pkg label:last-child { border-right: none; }
.acc-selector-pkg input        { display: none; }
.acc-selector-pkg input:checked + label { background: var(--black); color: var(--white); }

/* Payment tabs */
.payment-method-tabs        { display: flex; gap: 0.5rem; margin-bottom: 1.1rem; }
.payment-method-tabs button {
  flex: 1; padding: 0.65rem;
  font-family: var(--font-body); font-size: var(--text-xs); font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase;
  border: 1.5px solid var(--gray-2); background: var(--white); color: var(--black);
  cursor: pointer; transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
  min-height: 44px; border-radius: 0;
}
.payment-method-tabs button.active { background: var(--black); color: var(--white); border-color: var(--black); }
.pm-tab        { flex: 1; padding: 0.65rem; font-family: var(--font-body); font-size: var(--text-xs); font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; border: 1.5px solid var(--gray-2); background: var(--white); color: var(--black); cursor: pointer; transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease; min-height: 44px; border-radius: 0; }
.pm-tab.active { background: var(--black); color: var(--white); border-color: var(--black); }
.payment-panel        { display: none; }
.payment-panel.active { display: block; }
#paypal-button-container, #track-paypal-container { margin-top: 0.75rem; min-height: 50px; }

/* ── TEAM (about page) ──────────────────────────────────────── */
.team-grid  { display: grid; grid-template-columns: repeat(4, 1fr); gap: clamp(1.5rem, 3vw, 2.5rem); }
.team-card  { border-top: 1.5px solid var(--gray-2); padding-top: 1.5rem; }
.team-avatar { width: 72px; height: 72px; border-radius: 50%; background: var(--gray-2); margin-bottom: 1rem; object-fit: cover; }
.team-name  { font-family: var(--font-display); font-size: 1.1rem; font-weight: 500; margin-bottom: 0.15rem; }
.team-role  { font-size: var(--text-xs); font-weight: 600; letter-spacing: 0.15em; text-transform: uppercase; color: var(--black); margin-bottom: 0.65rem; }
.team-bio   { font-size: var(--text-base); color: var(--black); font-weight: 500; line-height: 1.65; }

.team-social { display: flex; gap: 0.75rem; margin-top: 1rem; align-items: center; }
.team-social a {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px; height: 32px;
  border: 1.5px solid var(--gray-2);
  color: var(--black);
  text-decoration: none;
  transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
  flex-shrink: 0;
}
.team-social a:hover { background: var(--black); border-color: var(--black); color: var(--white); }
.team-social a svg   { width: 14px; height: 14px; fill: currentColor; display: block; }

/* ── SPINNER ────────────────────────────────────────────────── */
.spinner {
  display: inline-block;
  width: 15px; height: 15px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: var(--white);
  border-radius: 50%;
  animation: spin 0.55s linear infinite;
  vertical-align: middle;
  margin-right: 0.4rem;
  flex-shrink: 0;
}
.btn-sz-outline .spinner, .btn-sz-ghost .spinner { border-color: rgba(0,0,0,0.2); border-top-color: var(--black); }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── FADE-IN ────────────────────────────────────────────────── */
.fade-in { opacity: 0; transform: translateY(20px); transition: opacity 0.65s var(--ease-out), transform 0.65s var(--ease-out); }
.fade-in.visible  { opacity: 1; transform: translateY(0); }
.fade-in-delay-1  { transition-delay: 0.08s; }
.fade-in-delay-2  { transition-delay: 0.16s; }
.fade-in-delay-3  { transition-delay: 0.24s; }
.fade-in-delay-4  { transition-delay: 0.32s; }

/* If JS is slow/broken, force content visible after 1.5s */
@keyframes fadeInFallback {
  0%   { opacity: 0; transform: translateY(20px); }
  100% { opacity: 1; transform: translateY(0); }
}
.fade-in:not(.visible) { animation: fadeInFallback 0.65s var(--ease-out) 1.5s both; }

/* ── UTILITIES ──────────────────────────────────────────────── */
.text-italic    { font-style: italic; }
.text-center    { text-align: center; }
.text-white     { color: var(--white) !important; }
.text-muted     { color: var(--black); font-weight: 500; }
.bg-off-white   { background: var(--off-white); }
.border-bottom-thin { border-bottom: 1px solid var(--gray-2); }
.w-100          { width: 100%; }
.d-flex         { display: flex; }
.flex-wrap      { flex-wrap: wrap; }
.align-items-center      { align-items: center; }
.align-items-start       { align-items: flex-start; }
.justify-content-center  { justify-content: center; }
.justify-content-between { justify-content: space-between; }
.gap-3          { gap: 1rem; }
.gap-4          { gap: 1.5rem; }
.mt-2 { margin-top: .5rem; }   .mt-3 { margin-top: 1rem; }
.mt-4 { margin-top: 1.5rem; }  .mt-5 { margin-top: 3rem; }
.mb-2 { margin-bottom: .5rem; } .mb-3 { margin-bottom: 1rem; }
.mb-4 { margin-bottom: 1.5rem; }.mb-5 { margin-bottom: 3rem; }
.p-3  { padding: 1rem; }        .p-4  { padding: 1.5rem; }
.pt-3 { padding-top: 1rem; }    .pt-4 { padding-top: 1.5rem; }
.pb-3 { padding-bottom: 1rem; }

.font-display  { font-family: var(--font-display); color: var(--black); font-weight: 500; }
.font-display1 { font-family: var(--font-display); color: var(--white); }

/* ════════════════════════════════════════════════════════════
   RESPONSIVE BREAKPOINTS
   ════════════════════════════════════════════════════════════ */
@media (max-width: 1200px) {
  .packages-grid { grid-template-columns: repeat(2, 1fr); }
  .pkg-card:nth-child(3n)        { border-right: 1.5px solid var(--black); }
  .pkg-card:nth-last-child(-n+3) { border-bottom: 1.5px solid var(--black); }
  .pkg-card:nth-child(2n)        { border-right: none; }
  .pkg-card:nth-last-child(-n+2) { border-bottom: none; }
  .why-grid         { grid-template-columns: repeat(2, 1fr); }
  /* footer responsive handled in footer section */
  .team-grid        { grid-template-columns: repeat(2, 1fr); }
  .stats-grid       { grid-template-columns: repeat(2, 1fr); }
  .testimonial-grid { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 900px) {
  .testimonial-grid    { grid-template-columns: 1fr; }
  .pkg-detail-grid     { grid-template-columns: 1fr; }
  .booking-layout      { grid-template-columns: 1fr; }
  .booking-sidebar-col { position: static; max-height: none; overflow-y: visible; }
  .booking-widget      { position: static; }
  .page-hero-inner     { grid-template-columns: 1fr; gap: 2rem; }
}

@media (max-width: 768px) {
  :root { --nav-h: 64px; }
  .sz-nav-links { display: none; }
  .nav-toggle   { display: flex; }
  .sz-logo      { font-size: 1.55rem; }

  .hero           { min-height: 580px; }
  .hero-title     { font-size: clamp(2.2rem, 8vw, 3rem); }
  .hero-sub       { font-size: 0.66rem; letter-spacing: 0.2em; }
  .hero-actions   { flex-direction: column; align-items: flex-start; }
  .hero-actions .btn-sz-primary,
  .hero-actions .btn-sz-ghost { width: 100%; max-width: 320px; }
  .hero-scroll    { display: none; }

  .packages-grid  { grid-template-columns: 1fr; }
  .pkg-card       { border-right: none !important; border-bottom: 1.5px solid var(--black) !important; }
  .pkg-card:last-child { border-bottom: none !important; }

  .stats-grid     { grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
  .stat-number    { font-size: 2rem; }
  .why-grid       { grid-template-columns: 1fr; gap: 2rem; }

  .responsive-two-col { grid-template-columns: 1fr !important; gap: 2.5rem !important; }
  .about-grid, .values-grid, .contact-grid { grid-template-columns: 1fr !important; gap: 2.5rem !important; }
  .page-hero-img  { max-width: 100%; }

  .form-row       { grid-template-columns: 1fr; gap: 0; }
  .form-row-3     { grid-template-columns: 1fr; gap: 0; }
  .phone-field-row              { flex-direction: column; gap: 0; }
  .phone-field-row .phone-code-select { flex: none; width: 100%; }

  .testimonial-grid { grid-template-columns: 1fr; }
  
  
  .footer-brand-desc { max-width: 100%; }

  .team-grid      { grid-template-columns: 1fr 1fr; }
  .acc-selector label { font-size: 0.6rem; padding: 0.65rem 0.2rem; }
  .confirmation-box { padding: 1.5rem 1.25rem; }
  .booking-ref-display { font-size: 1.35rem; padding: 0.7rem 1rem; }

  .pkg-gallery    { height: 260px; border-radius: 8px; }
  .include-exclude { grid-template-columns: 1fr; }
}

@media (max-width: 480px) {
  .hero-title     { font-size: clamp(1.85rem, 7.5vw, 2.4rem); }
  .stats-grid     { grid-template-columns: 1fr 1fr; gap: 1rem; }
  .stat-number    { font-size: 1.75rem; }
  .stat-label     { font-size: 0.58rem; }
  .team-grid      { grid-template-columns: 1fr; }
  .cta-banner-actions { flex-direction: column; align-items: center; }
  .cta-banner-actions .btn-sz-primary,
  .cta-banner-actions .btn-sz-ghost { width: 100%; max-width: 300px; }
  .testimonial    { padding: 1.25rem; }
  .testimonial-quote-mark { font-size: 3.5rem; }
  .section        { padding: 3rem 1.1rem; }
  .section-lg     { padding: 4rem 1.1rem; }
  .section-sm     { padding: 2.5rem 1.1rem; }
  .pricing-row    { padding: 0.75rem 1rem; }
}

/* ── REDUCED MOTION ─────────────────────────────────────────── */
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
  .hero-bg { transform: none !important; }
  .fade-in { opacity: 1 !important; transform: none !important; }
}

/* ── PRINT ──────────────────────────────────────────────────── */
@media print {
  .sz-nav, .sz-mobile-nav, .hero-scroll, #paypal-button-container { display: none !important; }
  .confirmation-box { border: 1px solid #ccc; }
  body { font-size: 12pt; }
}

/* Currency switcher */
.currency-switcher {
  display: flex;
  align-items: center;
  gap: 0;
  border: 1px solid var(--gray-2);
  overflow: hidden;
  flex-shrink: 0;
}
.currency-switcher button {
  background: none;
  border: none;
  padding: 0.3rem 0.6rem;
  font-family: var(--font-body);
  font-size: 0.6rem;
  font-weight: 600;
  letter-spacing: 0.1em;
  cursor: pointer;
  color: var(--black);
  border-right: 1px solid var(--gray-2);
  transition: background 0.2s, color 0.2s;
}
.currency-switcher button:last-child { border-right: none; }
.currency-switcher button.active,
.currency-switcher button:hover { background: var(--black); color: var(--white); }

/* Trust strip */
.trust-strip {
  background: var(--gray-1);
  border-top: 1px solid var(--gray-2);
  border-bottom: 1px solid var(--gray-2);
  padding: 1rem var(--pad);
}
.trust-strip-inner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 2rem;
  flex-wrap: wrap;
  max-width: var(--max-w);
  margin: 0 auto;
}
.trust-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: var(--text-xs);
  font-weight: 500;
  color: var(--black);
  white-space: nowrap;
}
.trust-stars { color: #f59e0b; font-size: 0.8rem; letter-spacing: 0.05em; }
.trust-divider { width: 1px; height: 24px; background: var(--gray-2); flex-shrink: 0; }
@media (max-width: 640px) { .trust-divider { display: none; } .trust-strip-inner { gap: 1rem; justify-content: flex-start; } }




/* ── PACKAGE GALLERY / SLIDER (definitive) ─────────────────── */
/* pkg-gallery: see slider definition below */
.pkg-gallery img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  opacity: 0;
  transition: opacity .65s ease-in-out;
  display: block;
}
.pkg-gallery img.active,
.pkg-gallery img:first-child { opacity: 1; }

.pkg-slider-btn {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 48px; height: 48px;
  border: none;
  border-radius: 50%;
  background: rgba(0,0,0,.5);
  color: #fff;
  font-size: 22px;
  cursor: pointer;
  z-index: 10;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background .2s ease;
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
}
.pkg-slider-btn:hover { background: rgba(0,0,0,.8); }
.pkg-slider-prev, .pkg-prev { left: 14px; }
.pkg-slider-next, .pkg-next { right: 14px; }

/* Home package cards slider */
.home-slider {
  position: relative;
  width: 100%;
  height: 220px;
  overflow: hidden;
  border-radius: 8px;
  background: #111;
  margin-bottom: 1.1rem;
}
.home-slider img {
  position: absolute;
  inset: 0;
  width: 100%; height: 100%;
  object-fit: cover;
  opacity: 0;
  transition: opacity .6s ease-in-out;
  display: block;
}
.home-slider img:first-child { opacity: 1; }
.home-prev, .home-next {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 36px; height: 36px;
  border: none;
  border-radius: 50%;
  background: rgba(0,0,0,.5);
  color: #fff;
  cursor: pointer;
  z-index: 10;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  transition: background .2s;
}
.home-prev:hover, .home-next:hover { background: rgba(0,0,0,.8); }
.home-prev { left: 8px; }
.home-next { right: 8px; }

@media (max-width: 768px) {
  .pkg-gallery  { height: 260px; border-radius: 8px; }
  .home-slider  { height: 180px; }
}

/* ── BIG SCREEN OPTIMIZATIONS (≥1400px) ───────────────────── */
@media (min-width: 1400px) {
  .packages-grid    { grid-template-columns: repeat(3, 1fr); }
  .pkg-detail-grid  { grid-template-columns: 1fr 380px; gap: 5rem; }
  .booking-layout   { grid-template-columns: 1fr 400px; gap: 5rem; }
  .home-slider      { height: 260px; }
  .pkg-gallery      { height: 480px; }
  .section          { padding: 7rem 2.5rem; }
  .section-lg       { padding: 9rem 2.5rem; }
}

/* ── FORM SECTION STYLES ───────────────────────────────────── */
.sz-form-section {
  margin-bottom: 2rem;
  padding-bottom: 2rem;
  border-bottom: 1px solid var(--gray-2);
}
.sz-form-section:last-of-type { border-bottom: none; }

.sz-form-section-title {
  font-family: var(--font-body);
  font-size: var(--text-xs);
  font-weight: 700;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--black);
  margin-bottom: 1.25rem;
  display: flex;
  align-items: center;
  gap: .65rem;
}
.sz-form-section-title::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--gray-2);
}

.sz-required { color: var(--black); font-weight: 700; margin-left: .2rem; }
.sz-hint {
  font-size: var(--text-xs);
  font-weight: 400;
  color: var(--black);
  letter-spacing: 0;
  text-transform: none;
  margin-left: .35rem;
  opacity: 1;
}

/* Loading button state */
.btn-sz-dark.is-loading,
.btn-sz-outline.is-loading {
  position: relative;
  pointer-events: none;
  opacity: .75;
}
.btn-sz-dark.is-loading::after,
.btn-sz-outline.is-loading::after {
  content: '';
  display: inline-block;
  width: 14px; height: 14px;
  border: 2px solid rgba(255,255,255,.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin .55s linear infinite;
  margin-left: .5rem;
  vertical-align: middle;
}
.btn-sz-outline.is-loading::after { border-color: rgba(0,0,0,.2); border-top-color: var(--black); }

/* Price preview box */
.sz-price-preview {
  border: 1.5px solid var(--black);
  padding: 1.25rem;
  background: var(--gray-1);
}
.sz-price-preview-total {
  font-family: var(--font-display);
  font-size: 2rem;
  font-weight: 500;
  color: var(--black);
  margin-bottom: .5rem;
}
.sz-price-breakdown {
  font-size: .78rem;
  font-weight: 500;
  color: var(--black);
  line-height: 1.9;
}

/* Alert form messages */
.form-message.success {
  background: #f0f7f0;
  border-left: 3px solid #2a7a2a;
  color: #174f17;
  padding: .85rem 1.1rem;
  font-size: .85rem;
  font-weight: 500;
}
.form-message.error {
  background: #fdf0f0;
  border-left: 3px solid #c22020;
  color: #6b1111;
  padding: .85rem 1.1rem;
  font-size: .85rem;
  font-weight: 500;
}

/* Trust badge strip active */
.trust-strip { display: block; }

/* Track page: narrower content for readability */
.track-container { max-width: 760px; margin: 0 auto; }

/* Package page: sticky nav bar */
.pkg-nav {
  position: sticky;
  top: var(--nav-h);
  z-index: 90;
  background: var(--white);
  margin-bottom: 3rem;
}

/* About page hero image fix */
.page-hero-img {
  max-width: 420px;
  border-radius: 4px;
  object-fit: cover;
}

/* Confirmation page */
.confirmation-two-col {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 3rem;
  align-items: start;
}
@media (max-width: 768px) {
  .confirmation-two-col { grid-template-columns: 1fr; gap: 1.5rem; }
}

/* Contact page map placeholder better height */
.map-placeholder {
  border: 1px solid var(--gray-2);
  height: 240px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: .75rem;
  background: var(--gray-1);
  margin-top: 2rem;
}

/* Booking widget polish */
.booking-widget {
  border: 1.5px solid var(--gray-2);
  background: var(--white);
}
.booking-widget:hover {
  border-color: var(--gray-3);
  box-shadow: 0 4px 20px rgba(0,0,0,.06);
  transition: box-shadow .25s ease, border-color .25s ease;
}

/* Package pricing table */
.pricing-table {
  border: 1.5px solid var(--gray-2);
  overflow: hidden;
}

/* Why-grid icons */
.why-icon {
  background: var(--off-white);
  border: 1.5px solid var(--gray-2);
  transition: background .2s ease, border-color .2s ease;
}
.why-item:hover .why-icon {
  background: var(--black);
  border-color: var(--black);
  color: var(--white);
}
.why-item:hover .why-icon svg { stroke: var(--white); }

/* CTA Banner button spacing */
.cta-banner-actions { gap: 1.25rem; }
.cta-banner-actions a { min-width: 200px; }

/* Nav: active page indicator */
.sz-nav-links a.active-link::after { width: 100%; }

/* ── PUBLIC PAGE FIXES ──────────────────────────────────────── */

/* Navbar: ensure no overflow on mobile */
.sz-nav-links {
  flex-wrap: wrap;
}

/* Hero section: consistent min-height on big screens */
@media (min-width: 1200px) {
  .page-hero {
    min-height: 42vh;
  }
  .page-hero-inner {
    grid-template-columns: 1fr 1fr;
    align-items: end;
  }
}

/* About page: team grid responsive */
.team-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1.5rem;
  margin-top: 2.5rem;
}
.team-card {
  background: var(--white);
  border: 1px solid var(--gray-2);
  padding: 1.75rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: .5rem;
}
.team-avatar {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  margin-bottom: .5rem;
  flex-shrink: 0;
}
.team-name { font-family: var(--font-display); font-size: 1.05rem; font-weight: 500; }
.team-role { font-size: .65rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #9ca3af; }
.team-bio  { font-size: .85rem; line-height: 1.75; color: var(--black); font-weight: 400; flex: 1; }
.team-social { display: flex; gap: .75rem; margin-top: .5rem; }
.team-social a { display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border: 1px solid var(--gray-2); }
.team-social a svg { width: 13px; height: 13px; fill: var(--black); }
.team-social a:hover { background: var(--black); }
.team-social a:hover svg { fill: var(--white); }

@media (max-width: 768px) {
  .team-grid { grid-template-columns: 1fr 1fr; gap: 1rem; }
}
@media (max-width: 480px) {
  .team-grid { grid-template-columns: 1fr; }
}

/* Values grid responsive */
.values-grid {
  margin-top: 2.5rem;
}
@media (max-width: 768px) {
  .values-grid { grid-template-columns: 1fr !important; gap: 1rem; }
}
@media (max-width: 480px) {
  .about-grid { grid-template-columns: 1fr !important; gap: 2rem; }
}

/* pkg-gallery: ensure single definitive rule wins */
.pkg-gallery { position: relative; width: 100%; height: 420px; overflow: hidden; background: #111; }
.pkg-gallery img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; transition: opacity .6s ease-in-out; display: block; }
.pkg-gallery img.active, .pkg-gallery img:first-child { opacity: 1; }
.pkg-slider-btn { position: absolute; top: 50%; transform: translateY(-50%); width: 44px; height: 44px; border: none; border-radius: 50%; background: rgba(0,0,0,.5); color: #fff; cursor: pointer; z-index: 10; display: flex; align-items: center; justify-content: center; font-size: 20px; transition: background .2s; }
.pkg-slider-btn:hover { background: rgba(0,0,0,.8); }
.pkg-slider-prev, .pkg-prev { left: 12px; }
.pkg-slider-next, .pkg-next { right: 12px; }

/* home-slider */
.home-slider { position: relative; width: 100%; height: 220px; overflow: hidden; background: #111; }
.home-slider img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; transition: opacity .55s ease-in-out; display: block; }
.home-slider img.active { opacity: 1; }
.home-prev, .home-next { position: absolute; top: 50%; transform: translateY(-50%); width: 32px; height: 32px; border: none; border-radius: 50%; background: rgba(0,0,0,.5); color: #fff; cursor: pointer; z-index: 10; display: flex; align-items: center; justify-content: center; font-size: 16px; }
.home-prev { left: 8px; }
.home-next { right: 8px; }

@media (max-width: 768px) {
  .pkg-gallery  { height: 260px; }
  .home-slider  { height: 180px; }
}
@media (min-width: 1400px) {
  .pkg-gallery  { height: 480px; }
  .home-slider  { height: 260px; }
}

/* track.php — narrower container */
.track-container { max-width: 780px; margin: 0 auto; }

/* book.php — price preview */
.sz-price-preview { border: 1.5px solid var(--black); padding: 1.25rem 1.5rem; background: var(--off-white); }
.sz-price-preview-total { font-family: var(--font-display); font-size: 2rem; font-weight: 500; margin-bottom: .35rem; }
.sz-price-breakdown { font-size: .78rem; font-weight: 500; line-height: 1.9; }

/* contact.php map placeholder */
.map-placeholder { border: 1px solid var(--gray-2); height: 240px; display: flex; align-items: center; justify-content: center; flex-direction: column; gap: .75rem; background: var(--gray-1); margin-top: 2rem; }

/* terms.php / privacy-policy.php section spacing */
.legal-section { margin-bottom: 2.5rem; padding-bottom: 2.5rem; border-bottom: 1px solid var(--gray-2); }
.legal-section:last-of-type { border-bottom: none; }
.legal-section h2 { font-family: var(--font-display); font-size: 1.1rem; font-weight: 500; margin-bottom: .85rem; }
.legal-section p  { font-size: .9rem; line-height: 1.85; font-weight: 400; }

/* footer: licence numbers */
.footer-legal { font-size: .7rem; opacity: .45; margin-top: .35rem; }
.footer-legal a { color: inherit; text-decoration: none; margin-right: 1rem; }
.footer-legal a:hover { opacity: .8; }

/* ── FOOTER VISIBILITY OVERRIDE — all text pure white ────── */
.sz-footer,
.sz-footer p,
.sz-footer span:not(.footer-contact-icon),
.sz-footer li,
.sz-footer a,
.sz-footer small,
.sz-footer .footer-brand-name,
.sz-footer .footer-brand-desc,
.sz-footer .footer-col-title,
.sz-footer .footer-links a,
.sz-footer .footer-contact-item,
.sz-footer .footer-contact-item a,
.sz-footer .footer-copy,
.sz-footer .footer-bottom-links a,
.sz-footer .footer-licence,
.sz-footer .footer-response-note {
  color: #fff !important;
}
/* Subtle hover dimming */
.sz-footer .footer-links a:hover,
.sz-footer .footer-contact-item a:hover,
.sz-footer .footer-bottom-links a:hover { opacity: .7; }
/* Column titles — slightly muted but still visible */
.sz-footer .footer-col-title { color: rgba(255,255,255,.75) !important; }
/* Bottom bar and licence — slightly muted */
.sz-footer .footer-bottom,
.sz-footer .footer-copy,
.sz-footer .footer-bottom-links a,
.sz-footer .footer-licence { color: rgba(255,255,255,.75) !important; }
/* Response note — intentionally quieter */
.sz-footer .footer-response-note { color: rgba(255,255,255,.6) !important; }
/* Bottom separator dots */
.sz-footer .footer-bottom-sep,
.sz-footer .footer-licence-dot { color: rgba(255,255,255,.3) !important; }

FILEOF

echo '  Writing: admin/css/admin.css'
mkdir -p $(dirname $SERVER/admin/css/admin.css)
cat > $SERVER/admin/css/admin.css << 'FILEOF'
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,700;1,400&family=Jost:wght@300;400;500;600;700&display=swap');

/* ── RESET ──────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ── TOKENS ─────────────────────────────────────────────────── */
:root {
  --black:       #0a0a0a;
  --white:       #ffffff;
  --gray-50:     #f9f9f8;
  --gray-100:    #f3f3f1;
  --gray-200:    #e6e6e2;
  --gray-300:    #c4c4be;
  --gray-500:    #7a7a72;
  --gray-700:    #3a3a34;
  --green:       #16a34a;
  --red:         #dc2626;
  --blue:        #1d4ed8;
  --amber:       #d97706;
  --sidebar-w:   240px;
  --topbar-h:    60px;
  --font-body:   'Jost', system-ui, sans-serif;
  --font-disp:   'Playfair Display', Georgia, serif;
  --radius:      2px;
  --shadow-sm:   0 1px 3px rgba(0,0,0,.07);
  --shadow-md:   0 4px 16px rgba(0,0,0,.10);
  --transition:  .18s ease;
}

html, body { height: 100%; }

/* ── LAYOUT SHELL ───────────────────────────────────────────── */
.admin-body {
  display: flex;
  font-family: var(--font-body);
  font-size: 14px;
  background: var(--gray-50);
  color: var(--black);
  min-height: 100vh;
}

/* ── SIDEBAR ────────────────────────────────────────────────── */
.admin-sidebar {
  width: var(--sidebar-w);
  min-height: 100vh;
  background: var(--black);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0; left: 0; bottom: 0;
  z-index: 200;
  transform: translateX(0);
  transition: transform var(--transition);
  overflow-y: auto;
  overflow-x: hidden;
}

.admin-logo {
  padding: 1.4rem 1.25rem;
  border-bottom: 1px solid rgba(255,255,255,.08);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .5rem;
  flex-shrink: 0;
}
.admin-logo-left { display: flex; flex-direction: column; gap: .15rem; }
.admin-logo-mark { font-family: var(--font-disp); font-size: 1.4rem; font-weight: 500; color: var(--white); letter-spacing: .04em; }
.admin-logo-sub  { font-size: .55rem; font-weight: 600; letter-spacing: .18em; text-transform: uppercase; color: rgba(255,255,255,.35); }

/* Close button — visible on mobile only */
.sidebar-close {
  display: none;
  background: none;
  border: none;
  color: rgba(255,255,255,.5);
  font-size: 1.4rem;
  cursor: pointer;
  line-height: 1;
  padding: .25rem;
  flex-shrink: 0;
}

.admin-nav { flex: 1; padding: .75rem 0; display: flex; flex-direction: column; gap: 1px; overflow-y: auto; }

/* Nav group labels */
.admin-nav-group {
  font-size: .5rem;
  font-weight: 700;
  letter-spacing: .2em;
  text-transform: uppercase;
  color: rgba(255,255,255,.2);
  padding: .75rem 1.25rem .2rem;
  margin-top: .25rem;
}

.admin-nav-link {
  display: flex;
  align-items: center;
  gap: .65rem;
  padding: .6rem 1.25rem;
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: rgba(255,255,255,.5);
  text-decoration: none;
  border-left: 2px solid transparent;
  transition: color var(--transition), background var(--transition);
  white-space: nowrap;
}
.admin-nav-link:hover       { color: var(--white); background: rgba(255,255,255,.05); }
.admin-nav-link.active      { color: var(--white); border-left-color: var(--white); background: rgba(255,255,255,.08); }
.admin-nav-link--danger     { color: rgba(255,80,80,.6); }
.admin-nav-link--danger:hover { color: #ff5050; }
.admin-nav-icon             { font-size: .8rem; width: 16px; text-align: center; flex-shrink: 0; }

.admin-sidebar-footer {
  padding: .75rem 0;
  border-top: 1px solid rgba(255,255,255,.08);
  flex-shrink: 0;
}

/* Unread badge on Messages link */
.nav-badge {
  margin-left: auto;
  background: var(--red);
  color: #fff;
  font-size: .55rem;
  font-weight: 700;
  padding: .1rem .35rem;
  border-radius: 99px;
  line-height: 1.4;
}

/* ── SIDEBAR OVERLAY (mobile) ────────────────────────────────── */
.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.5);
  z-index: 199;
  backdrop-filter: blur(2px);
}
.sidebar-overlay.open { display: block; }

/* ── MAIN CONTENT ───────────────────────────────────────────── */
.admin-main {
  flex: 1;
  margin-left: var(--sidebar-w);
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  min-width: 0;
}

/* ── TOPBAR ─────────────────────────────────────────────────── */
.admin-topbar {
  background: var(--white);
  border-bottom: 1px solid var(--gray-200);
  padding: 0 1.5rem;
  height: var(--topbar-h);
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky;
  top: 0;
  z-index: 100;
  gap: 1rem;
}
.admin-topbar-left   { display: flex; align-items: center; gap: .85rem; min-width: 0; }
.admin-page-title    { font-family: var(--font-disp); font-size: 1.05rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.admin-topbar-right  { display: flex; align-items: center; gap: .75rem; flex-shrink: 0; }
.admin-env-badge     { font-size: .5rem; font-weight: 700; letter-spacing: .15em; padding: .2rem .5rem; background: var(--black); color: var(--white); text-transform: uppercase; }
.admin-user          { font-size: .72rem; font-weight: 600; color: var(--gray-500); }

/* Hamburger button */
.admin-hamburger {
  display: none;
  flex-direction: column;
  gap: 5px;
  background: none;
  border: none;
  cursor: pointer;
  padding: .4rem;
  flex-shrink: 0;
}
.admin-hamburger span {
  display: block;
  width: 20px;
  height: 2px;
  background: var(--black);
  border-radius: 1px;
  transition: var(--transition);
}

/* ── CONTENT AREA ───────────────────────────────────────────── */
.admin-content { padding: 1.5rem 2rem; flex: 1; min-width: 0; }

/* ── METRICS GRID ───────────────────────────────────────────── */
.metrics-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-bottom: 1.75rem;
}
.metric-card {
  background: var(--white);
  border: 1px solid var(--gray-200);
  padding: 1.25rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: .25rem;
}
.metric-card--revenue { border-top: 3px solid var(--black); }
.metric-label  { font-size: .6rem; font-weight: 700; letter-spacing: .15em; text-transform: uppercase; color: var(--gray-500); }
.metric-value  { font-family: var(--font-disp); font-size: 1.85rem; font-weight: 500; color: var(--black); line-height: 1.1; }
.metric-sub    { font-size: .68rem; color: var(--gray-500); }

/* ── CARDS ──────────────────────────────────────────────────── */
.admin-card          { background: var(--white); border: 1px solid var(--gray-200); }
.admin-card-header   { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid var(--gray-100); }
.admin-card-header h2,.admin-card-header h3 { font-family: var(--font-disp); font-size: .95rem; font-weight: 500; }
.admin-card-body     { padding: 1.25rem; }
.admin-grid-2        { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
.admin-link          { font-size: .68rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--black); text-decoration: none; }
.admin-link:hover    { text-decoration: underline; }

/* ── TABLES ─────────────────────────────────────────────────── */
.admin-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; border: 1px solid var(--gray-200); background: var(--white); }
.admin-table     { width: 100%; border-collapse: collapse; font-size: .8rem; min-width: 500px; }
.admin-table th  { font-size: .58rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--gray-500); padding: .55rem .75rem; border-bottom: 1px solid var(--gray-200); text-align: left; background: var(--gray-50); white-space: nowrap; }
.admin-table td  { padding: .7rem .75rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
.admin-table tr:last-child td { border-bottom: none; }
.admin-table tr:hover td      { background: var(--gray-50); }
.admin-table code { font-size: .72rem; background: var(--gray-100); padding: .12rem .35rem; }
.admin-empty     { text-align: center; padding: 2.5rem; color: var(--gray-500); font-style: italic; }

/* ── FORMS ──────────────────────────────────────────────────── */
.admin-label {
  display: block;
  font-size: .62rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--gray-700);
  margin-bottom: .3rem;
}
.admin-input {
  width: 100%;
  padding: .55rem .75rem;
  border: 1.5px solid var(--gray-200);
  font-family: var(--font-body);
  font-size: .85rem;
  outline: none;
  background: var(--white);
  color: var(--black);
  border-radius: var(--radius);
  appearance: none;
}
.admin-input:focus { border-color: var(--black); }
textarea.admin-input { resize: vertical; min-height: 80px; }

/* ── BUTTONS ────────────────────────────────────────────────── */
.admin-btn {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  padding: .4rem .85rem;
  font-family: var(--font-body);
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  cursor: pointer;
  border: 1.5px solid transparent;
  text-decoration: none;
  white-space: nowrap;
  border-radius: var(--radius);
  transition: background var(--transition), color var(--transition), border-color var(--transition);
}
.admin-btn--primary  { background: var(--black); color: var(--white); border-color: var(--black); }
.admin-btn--primary:hover  { background: #1e1e1e; }
.admin-btn--outline  { background: var(--white); color: var(--black); border-color: var(--gray-300); }
.admin-btn--outline:hover  { border-color: var(--black); }
.admin-btn--success  { background: var(--green); color: var(--white); border-color: var(--green); }
.admin-btn--danger   { background: var(--red); color: var(--white); border-color: var(--red); }
.admin-btn--muted    { background: var(--gray-100); color: var(--gray-500); border-color: var(--gray-200); }
.admin-btn--warning  { background: var(--amber); color: var(--white); border-color: var(--amber); }

/* ── ALERTS ─────────────────────────────────────────────────── */
.admin-alert         { padding: .85rem 1.1rem; margin-bottom: 1.25rem; font-size: .85rem; font-weight: 500; border-radius: var(--radius); }
.admin-alert--success{ background: #f0fdf4; border-left: 3px solid var(--green); color: #166534; }
.admin-alert--error  { background: #fef2f2; border-left: 3px solid var(--red); color: #991b1b; }
.admin-alert--info   { background: #eff6ff; border-left: 3px solid var(--blue); color: #1e40af; }

/* ── BADGES ─────────────────────────────────────────────────── */
.badge               { display: inline-flex; align-items: center; padding: .18rem .5rem; font-size: .6rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; border-radius: var(--radius); }
.badge-pending       { background: #fef3e2; color: #92400e; border: 1px solid #fcd34d; }
.badge-deposit_paid  { background: #e0f2fe; color: #075985; border: 1px solid #7dd3fc; }
.badge-fully_paid    { background: var(--black); color: var(--white); }
.badge-cancelled     { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
.badge-new           { background: var(--black); color: var(--white); }
.badge-status        { display: inline-block; padding: .2rem .5rem; font-size: .65rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.badge-pending.badge-status   { background: #fef3e2; color: #92400e; }
.badge-deposit_paid.badge-status { background: #e0f2fe; color: #075985; }
.badge-fully_paid.badge-status { background: var(--black); color: var(--white); }
.badge-cancelled.badge-status  { background: #fef2f2; color: #991b1b; }

/* ── MODAL ──────────────────────────────────────────────────── */
.admin-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.65); z-index: 999; align-items: center; justify-content: center; padding: 1rem; }
.admin-modal-overlay.open { display: flex; }
.admin-modal         { background: var(--white); border: 1px solid var(--gray-200); padding: 1.75rem; width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; position: relative; border-radius: var(--radius); }
.admin-modal-close   { position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--gray-500); }

/* ── LEAD LIST ──────────────────────────────────────────────── */
.lead-list           { display: flex; flex-direction: column; gap: 0; }
.lead-item           { display: grid; grid-template-columns: 1fr auto auto auto; align-items: center; gap: .75rem; padding: .65rem 0; border-bottom: 1px solid var(--gray-100); }
.lead-item:last-child{ border-bottom: none; }
.lead-name           { font-weight: 600; font-size: .82rem; }
.lead-meta           { font-size: .72rem; color: var(--gray-500); }
.lead-date           { font-size: .72rem; color: var(--gray-500); white-space: nowrap; }

/* ── MISC ───────────────────────────────────────────────────── */
.admin-form-group    { display: flex; flex-direction: column; gap: .3rem; margin-bottom: 1rem; }
.toast-container     { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999; display: flex; flex-direction: column; gap: .5rem; }
.toast               { background: var(--black); color: var(--white); padding: .75rem 1.25rem; font-size: .8rem; min-width: 200px; box-shadow: var(--shadow-md); }
.highlighted-row td  { background: #f0f0f0 !important; }

/* ── MODAL / DATA CARD extras ───────────────────────────────── */
.modal-overlay       { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 999; align-items: center; justify-content: center; padding: 1rem; }
.modal-overlay.open  { display: flex; }
.modal-box           { background: var(--white); width: 100%; max-width: 620px; max-height: 90vh; overflow-y: auto; border-radius: var(--radius); }
.modal-header        { display: flex; align-items: center; justify-content: space-between; padding: 1.1rem 1.5rem; border-bottom: 1px solid var(--gray-200); }
.modal-title         { font-size: .95rem; font-weight: 600; }
.modal-close         { background: none; border: none; font-size: 1.1rem; cursor: pointer; }
.detail-row          { display: grid; grid-template-columns: 130px 1fr; gap: .5rem; padding: .6rem 1.5rem; border-bottom: 1px solid var(--gray-100); font-size: .85rem; }
.detail-label        { font-size: .62rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--gray-500); padding-top: .1rem; }
.data-card           { background: var(--white); border: 1px solid var(--gray-200); margin-bottom: 2rem; }
.data-card-header    { padding: 1rem 1.5rem; border-bottom: 1px solid var(--gray-200); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .5rem; }
.data-card-title     { font-size: .9rem; font-weight: 600; }
.data-table          { width: 100%; border-collapse: collapse; font-size: .8rem; min-width: 600px; }
.data-table th       { padding: .6rem 1rem; border-bottom: 1px solid var(--gray-200); text-align: left; font-size: .62rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--gray-500); background: var(--gray-50); white-space: nowrap; }
.data-table td       { padding: .7rem 1rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td      { background: var(--gray-50); }
.ref-code            { font-family: monospace; font-size: .75rem; background: var(--gray-100); padding: .12rem .4rem; }
.search-bar          { display: flex; gap: .25rem; flex-wrap: wrap; }
.search-bar input    { border: 1.5px solid var(--gray-200); padding: .45rem .75rem; font-size: .8rem; outline: none; flex: 1; min-width: 160px; }
.search-bar input:focus { border-color: var(--black); }
.search-bar button   { background: var(--black); color: var(--white); border: none; padding: .45rem 1rem; font-size: .75rem; font-weight: 600; cursor: pointer; }
.status-select       { border: 1.5px solid var(--gray-200); padding: .35rem .5rem; font-size: .75rem; cursor: pointer; background: var(--white); }
.action-btn          { background: none; border: 1.5px solid var(--gray-200); padding: .3rem .6rem; font-size: .7rem; font-weight: 600; cursor: pointer; margin-right: .25rem; }
.action-btn:hover    { border-color: var(--black); }
.pagination          { padding: 1rem 1.5rem; display: flex; align-items: center; justify-content: space-between; border-top: 1px solid var(--gray-200); flex-wrap: wrap; gap: .5rem; }
.page-btns           { display: flex; gap: .25rem; flex-wrap: wrap; }
.page-btn            { border: 1.5px solid var(--gray-200); background: var(--white); padding: .3rem .6rem; font-size: .75rem; cursor: pointer; }
.page-btn.active     { background: var(--black); color: var(--white); border-color: var(--black); }
.btn-admin           { background: var(--black); color: var(--white); border: none; padding: .55rem 1.1rem; font-size: .75rem; font-weight: 600; cursor: pointer; }
.btn-outline-admin   { background: var(--white); color: var(--black); border: 1.5px solid var(--black); }

/* ═══════════════════════════════════════════════════════════════
   RESPONSIVE BREAKPOINTS
   ═══════════════════════════════════════════════════════════════ */

/* ── LARGE DESKTOP (>1280px) ────────────────────────────────── */
@media (min-width: 1280px) {
  .metrics-grid { grid-template-columns: repeat(4, 1fr); }
  .admin-content { padding: 2rem 2.5rem; }
}

/* ── TABLET LANDSCAPE (1024px) ──────────────────────────────── */
@media (max-width: 1024px) {
  :root { --sidebar-w: 200px; }
  .metrics-grid   { grid-template-columns: repeat(2, 1fr); }
  .admin-grid-2   { grid-template-columns: 1fr; }
  .admin-content  { padding: 1.25rem 1.5rem; }
  .admin-nav-link { font-size: .68rem; padding: .55rem 1rem; }
  .admin-logo-mark{ font-size: 1.2rem; }
}

/* ── TABLET PORTRAIT (768px) ────────────────────────────────── */
@media (max-width: 768px) {
  /* Sidebar slides in/out */
  .admin-sidebar {
    transform: translateX(-100%);
    box-shadow: none;
  }
  .admin-sidebar.open {
    transform: translateX(0);
    box-shadow: var(--shadow-md);
  }
  .sidebar-close { display: flex; align-items: center; justify-content: center; }
  .admin-hamburger { display: flex; }

  /* Main content takes full width */
  .admin-main { margin-left: 0; }

  /* Content padding */
  .admin-content { padding: 1rem; }

  /* Metrics: 2 cols */
  .metrics-grid { grid-template-columns: repeat(2, 1fr); gap: .75rem; }
  .metric-value { font-size: 1.5rem; }

  /* Cards */
  .admin-card-header { flex-direction: column; align-items: flex-start; gap: .5rem; }

  /* Tables scroll horizontally */
  .admin-table-wrap { border-radius: 0; }
  .admin-table th, .admin-table td { padding: .55rem .6rem; }

  /* Topbar */
  .admin-topbar { padding: 0 1rem; }
  .admin-page-title { font-size: .9rem; }
  .admin-env-badge { display: none; }

  /* Modals */
  .admin-modal, .modal-box { padding: 1.25rem; margin: 0; max-height: 95vh; border-radius: 0; }
  .detail-row { grid-template-columns: 100px 1fr; }
}

/* ── MOBILE (480px) ─────────────────────────────────────────── */
@media (max-width: 480px) {
  /* Metrics: 1 col */
  .metrics-grid { grid-template-columns: 1fr; }
  .metric-card  { padding: 1rem; flex-direction: row; align-items: center; justify-content: space-between; }
  .metric-value { font-size: 1.6rem; }

  /* Full-width buttons */
  .admin-btn { font-size: .65rem; padding: .4rem .7rem; }

  /* Search bar stacks */
  .search-bar { flex-direction: column; }
  .search-bar input { min-width: 0; width: 100%; }
  .search-bar button { width: 100%; justify-content: center; }

  /* Table actions wrap */
  .admin-table td { white-space: normal; word-break: break-word; }

  /* Form grids collapse */
  div[style*="grid-template-columns:1fr 1fr"],
  div[style*="grid-template-columns: 1fr 1fr"] {
    display: block !important;
  }
  div[style*="grid-template-columns:1fr 1fr"] > div,
  div[style*="grid-template-columns: 1fr 1fr"] > div {
    margin-bottom: .75rem;
  }
  div[style*="grid-template-columns:1fr 1fr 1fr"],
  div[style*="grid-template-columns: 1fr 1fr 1fr"] {
    display: block !important;
  }
  div[style*="grid-template-columns:1fr 1fr 1fr"] > div,
  div[style*="grid-template-columns: 1fr 1fr 1fr"] > div {
    margin-bottom: .75rem;
  }

  /* Pagination */
  .pagination { flex-direction: column; text-align: center; }

  .admin-content { padding: .75rem; }
  .admin-topbar  { padding: 0 .75rem; }
}

.admin-stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:1rem; margin-bottom:1.5rem; }
.admin-log-terminal { background:#0a0a0a; color:#a3e635; font-family:monospace; font-size:.73rem; padding:1rem 1.25rem; max-height:300px; overflow-y:auto; line-height:1.75; }
.cron-pill { display:inline-flex; align-items:center; gap:.3rem; padding:.3rem .75rem; font-size:.62rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; border:1.5px solid; }
.cron-pill--active { border-color:#86efac; background:#f0fdf4; color:#166534; }
.cron-pill--inactive { border-color:#fca5a5; background:#fef2f2; color:#991b1b; }
.admin-two-col { display:grid; grid-template-columns:1fr 320px; gap:1.5rem; align-items:start; }
/* ── Filter pills ─────────────────────────────────────────── */
.admin-filter-pills { display:flex; gap:.4rem; flex-wrap:wrap; margin-bottom:1.1rem; }
.admin-filter-pill { display:inline-block; padding:.3rem .85rem; font-size:.62rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; border:1.5px solid var(--gray-200); background:var(--white); color:var(--black); text-decoration:none; cursor:pointer; transition:all .15s; }
.admin-filter-pill:hover { border-color:var(--black); }
.admin-filter-pill.active { background:var(--black); color:var(--white); border-color:var(--black); }
.admin-filter-pill--amber { border-color:var(--amber); color:var(--amber); }
.admin-filter-pill--green { border-color:var(--green); color:var(--green); }
.admin-filter-pill--red   { border-color:var(--red);   color:var(--red);   }

/* ── Status dot ───────────────────────────────────────────── */
.status-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:.35rem; flex-shrink:0; vertical-align:middle; }
.status-dot--green  { background:var(--green); }
.status-dot--red    { background:var(--red); }
.status-dot--amber  { background:var(--amber); }
.status-dot--gray   { background:var(--gray-300); }

FILEOF

echo '  Writing: js/main.js'
mkdir -p $(dirname $SERVER/js/main.js)
cat > $SERVER/js/main.js << 'FILEOF'
/* ============================================================
   SAFARI ZANGU VENTURES — Main JavaScript
   ============================================================ */

'use strict';

// ── CUSTOM SAFARI FORM ──────────────────────────────────
const customForm = document.querySelector('#custom-safari-form');
if (customForm) {
  customForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn   = customForm.querySelector('[type="submit"]');
    const msgEl = customForm.querySelector('.form-message');
    const orig  = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Sending…';

    try {
      const fd = new FormData(customForm);

      // Attach the selected country's full name alongside the dial code
      const countrySelect = customForm.querySelector('#phone-country-select');
      if (countrySelect) {
        const selectedOpt = countrySelect.options[countrySelect.selectedIndex];
        fd.append('phone_country_name', selectedOpt?.dataset.countryName || '');
      }

      const res  = await fetch('php/custom_safari.php', { method: 'POST', body: fd });
      const data = await res.json();
      showMsg(msgEl, data.message, data.success ? 'success' : 'error');
      if (data.success) customForm.reset();
    } catch {
      showMsg(msgEl, 'Network error. Please try again.', 'error');
    }
    btn.disabled = false;
    btn.innerHTML = orig;
  });
}

// ── NAV SCROLL BEHAVIOUR ─────────────────────────────────
const nav = document.querySelector('.sz-nav');
if (nav) {
  // Only apply over-hero if a .hero element exists on this page
  const hasHero = document.querySelector('.hero');

  const onScroll = () => {
    const scrolled = window.scrollY > 60;
    nav.classList.toggle('scrolled', scrolled);
    // over-hero: only on pages with a hero AND not yet scrolled
    if (hasHero) {
      nav.classList.toggle('over-hero', !scrolled);
    }
    // No hero = always black nav, never add over-hero
  };

  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll(); // run immediately on page load
}

// ── MOBILE NAV ───────────────────────────────────────────
const navToggle = document.querySelector('.nav-toggle');
const mobileNav = document.querySelector('.sz-mobile-nav');
if (navToggle && mobileNav) {
  const openNav = () => {
    const isOpen = mobileNav.classList.contains('open');
    mobileNav.classList.toggle('open');
    navToggle.classList.toggle('is-open');
    navToggle.setAttribute('aria-expanded', String(!isOpen));
  };
  navToggle.addEventListener('click', openNav);

  const closeNav = () => {
    mobileNav.classList.remove('open');
    navToggle.classList.remove('is-open');
    navToggle.setAttribute('aria-expanded', 'false');
  };

  mobileNav.querySelectorAll('a').forEach(a => a.addEventListener('click', closeNav));

  // Close on outside click
  document.addEventListener('click', (e) => {
    if (!navToggle.contains(e.target) && !mobileNav.contains(e.target)) closeNav();
  });

  // Close on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && mobileNav.classList.contains('open')) closeNav();
  });
}

// ── HERO IMAGE PARALLAX / LOAD ───────────────────────────
const heroBg = document.querySelector('.hero-bg');
if (heroBg) {
  heroBg.addEventListener('animationend', () => heroBg.classList.add('loaded'));
  setTimeout(() => heroBg.classList.add('loaded'), 100);
}


// ── COUNTRY DIAL CODE DROPDOWN (Custom Safari form) ─────
const phoneCountrySelect = document.getElementById('phone-country-select');
if (phoneCountrySelect && typeof COUNTRY_DIAL_CODES !== 'undefined') {
  COUNTRY_DIAL_CODES.forEach(c => {
    const opt = document.createElement('option');
    opt.value = c.code;
    opt.textContent = `${c.name} (${c.code})`;
    opt.dataset.countryName = c.name;
    if (c.iso === 'TZ') opt.selected = true;
    phoneCountrySelect.appendChild(opt);
  });
}

// ── TRAVELERS COUNT DROPDOWN (Custom Safari form) ────────
const travelersCountSelect = document.getElementById('travelers-count-select');
if (travelersCountSelect) {
  for (let i = 1; i <= 14; i++) {
    const opt = document.createElement('option');
    opt.value = i;
    opt.textContent = i === 1 ? '1 person' : `${i} people`;
    travelersCountSelect.appendChild(opt);
  }
  const opt15 = document.createElement('option');
  opt15.value = '15+';
  opt15.textContent = '15+ people';
  travelersCountSelect.appendChild(opt15);
}


// ── SET MIN DATES on date pickers to tomorrow ────────────
const tomorrow = new Date();
tomorrow.setDate(tomorrow.getDate() + 1);
const minDateStr = tomorrow.toISOString().split('T')[0];
document.querySelectorAll('input[type="date"]').forEach(input => {
  if (!input.min) input.min = minDateStr;
});

// ── INTERSECTION OBSERVER — FADE IN ─────────────────────
const fadeEls = document.querySelectorAll('.fade-in');
if (fadeEls.length && 'IntersectionObserver' in window) {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('visible');
        observer.unobserve(e.target);
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

  fadeEls.forEach(el => observer.observe(el));

  // Immediately trigger elements already in viewport on page load
  // (no scroll needed for above-fold content)
  setTimeout(() => {
    fadeEls.forEach(el => {
      const rect = el.getBoundingClientRect();
      if (rect.top < window.innerHeight && rect.bottom > 0) {
        el.classList.add('visible');
      }
    });
  }, 50);
}

// ── BOOKING FORM ─────────────────────────────────────────
const bookingForm = document.querySelector('#booking-form');
if (bookingForm) {
  bookingForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn    = bookingForm.querySelector('[type="submit"]');
    const msgEl  = bookingForm.querySelector('.form-message');
    const orig   = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Processing…';

    try {
      const res  = await fetch(bookingForm.dataset.action || 'php/book.php', {
        method: 'POST',
        body: new FormData(bookingForm),
      });
      const data = await res.json();

      if (data.success && data.redirect) {
        window.location.href = data.redirect;
      } else {
        showMsg(msgEl, data.message, data.success ? 'success' : 'error');
        btn.disabled = false;
        btn.innerHTML = orig;
      }
    } catch {
      showMsg(msgEl, 'Network error. Please try again.', 'error');
      btn.disabled = false;
      btn.innerHTML = orig;
    }
  });
}


// ── CONTACT FORM ─────────────────────────────────────────
const contactForm = document.querySelector('#contact-form');
if (contactForm) {
  contactForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn   = contactForm.querySelector('[type="submit"]');
    const msgEl = contactForm.querySelector('.form-message');
    const orig  = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Sending…';

    try {
      const res  = await fetch('php/contact.php', { method: 'POST', body: new FormData(contactForm) });
      const raw  = await res.text();
      let data;
      try {
        data = JSON.parse(raw);
      } catch (pe) {
        console.error('[SZV Contact] Bad JSON:', raw.substring(0, 300));
        showMsg(msgEl, res.ok
          ? 'Message sent! We\'ll be in touch within 24 hours.'
          : 'Server error. Please email us at info@safarizanguven.com.', res.ok ? 'success' : 'error');
        if (res.ok) contactForm.reset();
        btn.disabled = false; btn.innerHTML = orig;
        return;
      }
      showMsg(msgEl, data.message, data.success ? 'success' : 'error');
      if (data.success) contactForm.reset();
    } catch (netErr) {
      console.error('[SZV Contact] Network error:', netErr);
      showMsg(msgEl, 'Network error. Please check your connection and try again.', 'error');
    }
    btn.disabled = false;
    btn.innerHTML = orig;
  });
}

// ── BOOKING CHECK ─────────────────────────────────────────
const checkForm = document.querySelector('#check-booking-form');
const checkResult = document.querySelector('#booking-result');
if (checkForm) {
  checkForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn  = checkForm.querySelector('[type="submit"]');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Searching…';
    if (checkResult) checkResult.innerHTML = '';

    try {
      const refInput  = checkForm.querySelector('[name="ref"]');
      const dateInput = checkForm.querySelector('[name="date"]');
      const params = new URLSearchParams({
        ref: refInput ? refInput.value : '',
        date: dateInput ? dateInput.value : '',
      });
      const res = await fetch('php/check_booking.php?' + params);
      const raw = await res.text();
      let data;
      try {
        data = JSON.parse(raw);
      } catch (pe) {
        console.error('[SZV Track] Bad JSON:', raw.substring(0, 400));
        if (checkResult) checkResult.innerHTML =
          '<div class="alert-sz error">Server error. Please try again or ' +
          '<a href="' + (window.WHATSAPP_BASE_URL || 'https://wa.me/255663430934') +
          '?text=Hi%2C%20I%20need%20help%20tracking%20my%20booking" ' +
          'style="color:inherit;font-weight:700">WhatsApp us</a>.</div>';
        btn.disabled = false; btn.innerHTML = orig; return;
      }

      if (data.success) {
        renderBookingResult(data);
      } else {
        if (checkResult) {
          checkResult.innerHTML = `<div class="alert-sz error">${data.message}</div>`;
        }
      }
    } catch (netErr) {
      console.error('[SZV Track] Network error:', netErr);
      if (checkResult) checkResult.innerHTML = '<div class="alert-sz error">Network error. Please check your connection and try again.</div>';
    }
    btn.disabled = false;
    btn.innerHTML = orig;
  });
}

// ── RENDER BOOKING RESULT ────────────────────────────────
function renderBookingResult(data) {
  if (!checkResult) return;
  const b = data.booking;
  const statusMap = {
    pending:       ['status-pending', 'Pending Payment'],
    deposit_paid:  ['status-deposit', 'Deposit Paid'],
    fully_paid:    ['status-paid',    'Fully Paid ✓'],
    cancelled:     ['status-cancelled','Cancelled'],
  };
  const [cls, label] = statusMap[b.status] || ['status-pending', b.status];

  const rawTotal = parseFloat((b.total || '0').replace(/[^0-9.]/g, '')) || 0;
  const rawDeposit = parseFloat((b.deposit || '0').replace(/[^0-9.]/g, '')) || 0;
  const amountDue = (b.status === 'pending') ? rawDeposit : (rawTotal - rawDeposit);

  let paymentHtml = '';
  if (amountDue > 0 && b.status !== 'cancelled' && b.status !== 'fully_paid') {
    paymentHtml = `
      <div class="mt-3">
        <p style="margin:0 0 .5rem">Amount due: <strong>${b.balance_due || ('From ' + (window.CURRENCY_SYMBOL || '$') + amountDue.toFixed(2))}</strong></p>
        <div id="track-paypal-container"></div>
        <div id="track-paypal-status" style="margin-top:.5rem;color:#333;font-size:.9rem"></div>
      </div>`;
    // Load PayPal buttons if configured
    if (window.PAYPAL_CLIENT_ID) {
      // render after insertion into DOM
      setTimeout(() => loadTrackPayPal(amountDue, b.ref, b.travel_date), 50);
    }
  }


  // Status colors (inline — no CSS class dependency)
  const sColor = {pending:'#92400e',deposit_paid:'#075985',fully_paid:'#166534',cancelled:'#991b1b'};
  const sBg    = {pending:'#fef3e2',deposit_paid:'#e0f2fe',fully_paid:'#f0fdf4',cancelled:'#fef2f2'};
  const sc = sColor[b.status] || '#333';
  const sb = sBg[b.status]    || '#f5f5f5';

  const breakdownRows = b.base_amount ? `
    <tr style="border-bottom:1px solid #eee">
      <td style="padding:.4rem 0;font-weight:500;color:#111;width:55%">Safari Package Cost</td>
      <td style="padding:.4rem 0;text-align:right">${b.base_amount}</td>
    </tr>
    <tr style="border-bottom:1px solid #eee">
      <td style="padding:.4rem 0;font-weight:500;color:#111">Service Charge (5%)</td>
      <td style="padding:.4rem 0;text-align:right">${b.service_charge}</td>
    </tr>
    <tr style="border-bottom:2px solid #111">
      <td style="padding:.5rem 0;font-weight:600">Subtotal</td>
      <td style="padding:.5rem 0;text-align:right;font-weight:600">${b.subtotal}</td>
    </tr>
    <tr style="border-bottom:1px solid #eee">
      <td style="padding:.4rem 0;font-weight:500;color:#111">VAT (${b.vat_rate||'18%'} Tanzania)</td>
      <td style="padding:.4rem 0;text-align:right">${b.vat_amount}</td>
    </tr>
    <tr style="background:#f7f7f7;border-bottom:2px solid #111">
      <td style="padding:.6rem .4rem;font-weight:700;font-size:1rem">Grand Total</td>
      <td style="padding:.6rem .4rem;text-align:right;font-weight:700;font-size:1rem">${b.total}</td>
    </tr>
    <tr><td colspan="2" style="padding:.25rem 0"></td></tr>
    <tr style="border-bottom:1px solid #eee">
      <td style="padding:.4rem 0;font-weight:500">Deposit Required (50%)</td>
      <td style="padding:.4rem 0;text-align:right;font-weight:700">${b.deposit}</td>
    </tr>
    <tr>
      <td style="padding:.4rem 0;font-weight:500">Balance Due</td>
      <td style="padding:.4rem 0;text-align:right">${b.balance_due}</td>
    </tr>` : `
    <tr style="border-bottom:1px solid #eee">
      <td style="padding:.4rem 0;font-weight:500;width:55%">Total Amount</td>
      <td style="padding:.4rem 0;text-align:right;font-weight:600">${b.total||data.total_amount||'—'}</td>
    </tr>
    <tr>
      <td style="padding:.4rem 0;font-weight:500">Deposit (50%)</td>
      <td style="padding:.4rem 0;text-align:right;font-weight:600">${b.deposit||data.deposit_amount||'—'}</td>
    </tr>`;

  checkResult.innerHTML = `
    <div style="border:1.5px solid #0a0a0a;padding:2rem">
      <p style="font-size:.6rem;font-weight:700;letter-spacing:.16em;text-transform:uppercase;margin-bottom:.65rem">Booking Found</p>
      <div style="font-family:Georgia,serif;font-size:1.9rem;font-weight:500;letter-spacing:.04em;color:#0a0a0a;margin-bottom:.5rem">${b.ref}</div>
      <div style="display:inline-block;padding:.2rem .65rem;font-size:.65rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:${sb};color:${sc};border:1px solid ${sc};margin-bottom:1.25rem">${label}</div>

      <p style="font-size:.6rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin:.75rem 0 .6rem">Summary</p>
      <table style="width:100%;font-size:.85rem;border-collapse:collapse;margin-bottom:1.25rem">
        <tr style="border-bottom:1px solid #eee"><td style="padding:.4rem 0;font-weight:500;color:#111;width:40%">Guest</td><td style="font-weight:600">${b.name}</td></tr>
        <tr style="border-bottom:1px solid #eee"><td style="padding:.4rem 0;font-weight:500;color:#111">Package</td><td>${b.package}</td></tr>
        <tr style="border-bottom:1px solid #eee"><td style="padding:.4rem 0;font-weight:500;color:#111">Accommodation</td><td>${b.accommodation_level||'—'}</td></tr>
        <tr style="border-bottom:1px solid #eee"><td style="padding:.4rem 0;font-weight:500;color:#111">Travelers</td><td>${b.travelers}</td></tr>
        <tr style="border-bottom:1px solid #eee"><td style="padding:.4rem 0;font-weight:500;color:#111">Travel Date</td><td style="font-weight:600">${b.travel_date}</td></tr>
        <tr><td style="padding:.4rem 0;font-weight:500;color:#111">Booked On</td><td>${b.created_at}</td></tr>
      </table>

      <p style="font-size:.6rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin:.75rem 0 .6rem">Cost Breakdown</p>
      <table style="width:100%;font-size:.85rem;border-collapse:collapse;margin-bottom:1.25rem">
        ${breakdownRows}
      </table>

      ${paymentHtml}

      <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1.5rem">
        <a href="${data.whatsapp}" target="_blank" style="display:inline-block;padding:.65rem 1.4rem;background:#0a0a0a;color:#fff;font-size:.7rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;text-decoration:none">WhatsApp Us</a>
        <button onclick="window.print()" style="padding:.65rem 1.4rem;background:#fff;color:#0a0a0a;border:1.5px solid #0a0a0a;font-size:.7rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;cursor:pointer">Print / Save</button>
      </div>
    </div>`;


}

// ── HELPER ───────────────────────────────────────────────
function showMsg(el, text, type) {
  if (!el) return;
  el.innerHTML = `<div class="alert-sz ${type}">${text}</div>`;
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ── SMOOTH ANCHOR LINKS ──────────────────────────────────
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', (e) => {
    const href = a.getAttribute('href');
    if (!href || href === '#') return;
    try {
      const target = document.querySelector(href);
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    } catch(err) { /* invalid selector, skip */ }
  });
});

function loadTrackPayPal(amount, bookingRef, bookingDate) {
  if (!window.PAYPAL_CLIENT_ID) return;
  const script = document.createElement('script');
  script.src = `https://www.paypal.com/sdk/js?client-id=${window.PAYPAL_CLIENT_ID}&currency=${window.PAYPAL_CURRENCY}&intent=capture`;
  script.onload = () => {
    paypal.Buttons({
      style: { layout: 'vertical', color: 'black', shape: 'rect', label: 'paypal' },
      createOrder: (data, actions) => actions.order.create({
        purchase_units: [{
          amount: { value: amount.toFixed(2), currency_code: window.PAYPAL_CURRENCY },
          description: 'Safari Zangu Ventures — Booking ' + bookingRef
        }]
      }),
      onApprove: (data, actions) => actions.order.capture().then(details => {
        const statusEl = document.getElementById('track-paypal-status');
        statusEl.textContent = 'Payment received — updating your booking…';
        statusEl.style.color = '#1a6b1a';

        const fd = new FormData();
        fd.append('ref', bookingRef);
        fd.append('travel_date', bookingDate);
        fd.append('paypal_order_id', details.id);
        fd.append('amount_paid', amount.toFixed(2));

        fetch('php/record_payment.php', { method: 'POST', body: fd })
          .then(r => r.json())
          .then(res => {
            statusEl.textContent = res.success
              ? 'Payment confirmed! Refresh to see your updated status.'
              : 'Payment captured, but updating your booking failed — please contact us with your reference.';
          });
      }),
      onError: (err) => {
        document.getElementById('track-paypal-status').textContent = 'PayPal payment failed. Please try again.';
        document.getElementById('track-paypal-status').style.color = '#cc2222';
        console.error('PayPal error:', err);
      }
    }).render('#track-paypal-container');
  };
  document.body.appendChild(script);
}


// ── CURRENCY SWITCHER ──────────────────────────────────────
(function() {
  const RATES = { USD: 1, EUR: 0.92, GBP: 0.79 };
  const SYMBOLS = { USD: '$', EUR: '€', GBP: '£' };
  let current = localStorage.getItem('szv_currency') || 'USD';

  function convertPrices(currency) {
    const rate = RATES[currency] || 1;
    const sym  = SYMBOLS[currency];
    document.querySelectorAll('[data-price-usd]').forEach(el => {
      const usd = parseFloat(el.dataset.priceUsd);
      el.textContent = 'From ' + sym + Math.round(usd * rate).toLocaleString();
    });
    document.querySelectorAll('.currency-switcher button').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.currency === currency);
    });
    localStorage.setItem('szv_currency', currency);
    current = currency;
  }

  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.currency-switcher button').forEach(btn => {
      btn.addEventListener('click', function() { convertPrices(this.dataset.currency); });
    });
    convertPrices(current);
  });
})();


/* ── PACKAGE GALLERY SLIDER (desktop grid / mobile slider) ── */
document.addEventListener('DOMContentLoaded', function () {

  /* ── pkg-gallery slider ─────────────────────────────────── */
  document.querySelectorAll('.pkg-gallery').forEach(function (gallery) {
    var imgs    = Array.from(gallery.querySelectorAll('img'));
    var prevBtn = gallery.querySelector('.pkg-prev, .pkg-slider-prev');
    var nextBtn = gallery.querySelector('.pkg-next, .pkg-slider-next');
    if (!imgs.length) return;

    var current = 0;

    function show(i) {
      imgs.forEach(function (img) {
        img.classList.remove('active');
        img.style.opacity = '0';
      });
      imgs[i].classList.add('active');
      imgs[i].style.opacity = '1';
      current = i;
    }

    show(0);
    if (imgs.length < 2) return;

    if (prevBtn) prevBtn.addEventListener('click', function (e) {
      e.preventDefault();
      show((current - 1 + imgs.length) % imgs.length);
    });
    if (nextBtn) nextBtn.addEventListener('click', function (e) {
      e.preventDefault();
      show((current + 1) % imgs.length);
    });

    // Touch swipe
    var startX = 0;
    gallery.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
    gallery.addEventListener('touchend', function (e) {
      var dx = e.changedTouches[0].clientX - startX;
      if (Math.abs(dx) > 50) show(dx < 0 ? (current+1) % imgs.length : (current-1+imgs.length) % imgs.length);
    });
  });

  /* ── home-slider: auto-advance + swipe ─────────────────── */
  document.querySelectorAll('.home-slider').forEach(function (slider) {
    var imgs    = Array.from(slider.querySelectorAll('img'));
    var prevBtn = slider.querySelector('.home-prev');
    var nextBtn = slider.querySelector('.home-next');
    if (!imgs.length) return;

    var current = 0;

    function show(i) {
      imgs.forEach(function (img) {
        img.classList.remove('active');
        img.style.opacity = '0';
      });
      imgs[i].classList.add('active');
      imgs[i].style.opacity = '1';
      current = i;
    }

    show(0);
    if (imgs.length < 2) return;

    var timer = setInterval(function () { show((current+1) % imgs.length); }, 4000);
    slider.addEventListener('mouseenter', function () { clearInterval(timer); });
    slider.addEventListener('mouseleave', function () {
      timer = setInterval(function () { show((current+1) % imgs.length); }, 4000);
    });

    if (prevBtn) prevBtn.addEventListener('click', function () { show((current-1+imgs.length)%imgs.length); });
    if (nextBtn) nextBtn.addEventListener('click', function () { show((current+1)%imgs.length); });

    var startX = 0;
    slider.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, {passive:true});
    slider.addEventListener('touchend', function (e) {
      var dx = e.changedTouches[0].clientX - startX;
      if (Math.abs(dx) > 50) show(dx < 0 ? (current+1)%imgs.length : (current-1+imgs.length)%imgs.length);
    });
  });

});
FILEOF

echo '  Writing: layouts/admin.php'
mkdir -p $(dirname $SERVER/layouts/admin.php)
cat > $SERVER/layouts/admin.php << 'FILEOF'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $pageTitle ?? 'Admin — Safari Zangu Ventures' ?></title>
  <link rel="icon" type="image/jpeg" href="<?= BASE_PATH ?>/assets/images/logo.jpeg">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,700;1,400&family=Jost:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/admin/css/admin.css">
</head>
<body class="admin-body">

<!-- Overlay for mobile sidebar -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="admin-sidebar" id="adminSidebar">
  <div class="admin-logo">
    <div class="admin-logo-left">
      <span class="admin-logo-mark">SZV</span>
      <span class="admin-logo-sub">Admin Panel</span>
    </div>
    <button class="sidebar-close" id="sidebarClose" aria-label="Close menu">&#10005;</button>
  </div>

  <nav class="admin-nav">
    <?php
    $cur = basename($_SERVER['PHP_SELF']);

    // Count unread messages for badge
    $unreadCount  = 0;
    $queuePending = 0;
    $remindersDue = 0;
    $newRequests  = 0;
    try {
      $db_nav       = \App\Models\Database::getInstance();
      $unreadCount  = (int)$db_nav->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn();
      $queuePending = (int)$db_nav->query("SELECT COUNT(*) FROM email_queue WHERE status='pending'")->fetchColumn();
      $remindersDue = (int)$db_nav->query("SELECT COUNT(*) FROM booking_reminders WHERE status='pending' AND send_at<=DATE_ADD(NOW(),INTERVAL 24 HOUR)")->fetchColumn();
      $newRequests  = (int)$db_nav->query("SELECT COUNT(*) FROM custom_safari_requests WHERE status='new'")->fetchColumn();
    } catch(Exception $e) {}

    $groups = [
      'Operations' => [
        ['index.php',            'Dashboard',        '◈', ''],
        ['analytics.php',        'Analytics',        '◉', ''],
        ['bookings.php',         'Bookings',         '◉', ''],
        ['custom_requests.php',  'Requests',         '◎', $newRequests > 0 ? $newRequests : ''],
        ['contact_messages.php', 'Messages',         '✉', $unreadCount > 0 ? $unreadCount : ''],
        ['leads.php',            'Leads',            '◇', ''],
        ['quotations.php',       'Quotations',       '❝', ''],
      ],
      'Comms' => [
        ['email_queue.php',      'Email Queue',      '✉', $queuePending > 0 ? $queuePending : ''],
        ['reminders.php',        'Reminders',        '⏰', $remindersDue > 0 ? $remindersDue : ''],
      ],
      'Content' => [
        ['gallery.php',          'Image Gallery',    '🖼', ''],
        ['itineraries.php',      'Itineraries',      '🗺', ''],
        ['packages.php',         'Packages',         '▣', ''],
        ['package_edit.php',      'New Package',      '+', ''],
        ['team_members.php',     'Team',             '◑', ''],
        ['accommodations.php',   'Accommodations',   '⌂', ''],
        ['activities.php',       'Activities',       '◆', ''],
        ['vehicles.php',         'Fleet',            '⊕', ''],
      ],
      'Settings' => [
        ['park_fees.php',        'Park Fees',        '♦', ''],
        ['seasonal_rates.php',   'Seasons',          '◐', ''],
        ['users.php',             'Users',            '👤', ''],
      ],
    ];

    foreach ($groups as $groupLabel => $links):
    ?>
    <span class="admin-nav-group"><?= $groupLabel ?></span>
    <?php foreach ($links as [$href, $label, $icon, $badge]):
      $active = ($cur === $href) ? ' active' : '';
    ?>
    <a href="<?= $href ?>" class="admin-nav-link<?= $active ?>">
      <span class="admin-nav-icon"><?= $icon ?></span>
      <?= $label ?>
      <?php if ($badge): ?>
        <span class="nav-badge"><?= $badge ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; endforeach; ?>
  </nav>

  <div class="admin-sidebar-footer">
    <a href="<?= BASE_PATH ?>/" target="_blank" class="admin-nav-link">← View Site</a>
    <a href="change_password.php" class="admin-nav-link" style="font-size:.65rem">🔑 Change Password</a>
    <a href="logout.php" class="admin-nav-link admin-nav-link--danger">Sign Out</a>
  </div>
</aside>

<main class="admin-main">
  <header class="admin-topbar">
    <div class="admin-topbar-left">
      <button class="admin-hamburger" id="adminHamburger" aria-label="Open menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
      <h1 class="admin-page-title"><?= $pageTitle ?? '' ?></h1>
    </div>
    <div class="admin-topbar-right">
      <span class="admin-env-badge"><?= defined('APP_ENV') && APP_ENV === 'local' ? 'LOCAL' : 'LIVE' ?></span>
      <span class="admin-user">
        <?= htmlspecialchars($_SESSION['admin_full_name'] ?? $_SESSION['admin_username'] ?? 'Admin') ?>
      </span>
      <a href="logout.php" class="admin-btn admin-btn--muted" style="font-size:.62rem;padding:.28rem .65rem">Sign Out</a>
    </div>
  </header>

  <div class="admin-content">
    <?= $content ?? '' ?>
  </div>
</main>

<script src="<?= BASE_PATH ?>/admin/js/admin-validator.js"></script>
<script src="<?= BASE_PATH ?>/admin/js/admin.js"></script>
<script>
// ── Sidebar toggle (mobile) ──────────────────────────────────
(function () {
  var sidebar  = document.getElementById('adminSidebar');
  var overlay  = document.getElementById('sidebarOverlay');
  var hamburger= document.getElementById('adminHamburger');
  var closeBtn = document.getElementById('sidebarClose');

  function openSidebar() {
    sidebar.classList.add('open');
    overlay.classList.add('open');
    hamburger.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
    hamburger.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  }

  if (hamburger) hamburger.addEventListener('click', openSidebar);
  if (closeBtn)  closeBtn.addEventListener('click', closeSidebar);
  if (overlay)   overlay.addEventListener('click', closeSidebar);

  // Close on Escape
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeSidebar();
  });

  // Close sidebar on nav link click (mobile)
  if (sidebar) {
    sidebar.querySelectorAll('.admin-nav-link').forEach(function(link) {
      link.addEventListener('click', function() {
        if (window.innerWidth <= 768) closeSidebar();
      });
    });
  }
})();
</script>
</body>
</html>

FILEOF

echo '  Writing: layouts/app.php'
mkdir -p $(dirname $SERVER/layouts/app.php)
cat > $SERVER/layouts/app.php << 'FILEOF'
<?php
// cspell:ignore Zangu zangu
$pageTitle = $pageTitle ?? 'Safari Zangu Ventures';
?>

<!DOCTYPE html>
<html lang="en">

<head>

<?php include __DIR__ . '/head.php'; ?>

</head>

<body>

<?php include __DIR__ . '/navbar.php'; ?>

<main>
<?= $content ?? '' ?>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<script src="<?= BASE_PATH ?>/js/country-codes.js"></script>
<script src="<?= BASE_PATH ?>/js/form-validator.js"></script>
<script src="<?= BASE_PATH ?>/js/main.js"></script>
<script src="<?= BASE_PATH ?>/js/analytics.js"></script>

</body>
</html>

FILEOF

echo '  Writing: layouts/footer.php'
mkdir -p $(dirname $SERVER/layouts/footer.php)
cat > $SERVER/layouts/footer.php << 'FILEOF'
<footer class="sz-footer" role="contentinfo">
  <div class="footer-inner">

    <!-- TOP: brand + nav columns -->
    <div class="footer-grid">

      <!-- Brand column -->
      <div class="footer-brand-col">
        <p class="footer-brand-name"><em>Safari Zangu</em> Ventures</p>
        <p class="footer-brand-desc">
          Arusha-based safari operator crafting private, thoughtful Tanzania
          journeys since 2024. Every booking handled personally.
        </p>
        <div class="footer-brand-actions">
          <a href="https://wa.me/255663430934" target="_blank" rel="noopener" class="footer-wa-btn">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M11.999 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2.22 21.5l4.432-1.162A9.945 9.945 0 0011.999 22c5.523 0 10-4.477 10-10S17.522 2 11.999 2z" fill-rule="evenodd" clip-rule="evenodd"/></svg>
            Chat on WhatsApp
          </a>
          <a href="<?= BASE_PATH ?>/book.php" class="footer-book-btn">Book a Safari →</a>
        </div>
        <!-- Licence numbers -->
        <div class="footer-licence">
          <span>TALA Licence No. 037129</span>
          <span class="footer-licence-dot">·</span>
          <span>Reg. No. 179228971</span>
        </div>
      </div>

      <!-- Explore column -->
      <div class="footer-nav-col">
        <span class="footer-col-title">Explore</span>
        <ul class="footer-links">
          <li><a href="<?= BASE_PATH ?>/packages.php">All Packages</a></li>
          <li><a href="<?= BASE_PATH ?>/packages.php#classic-safari">Classic Safari</a></li>
          <li><a href="<?= BASE_PATH ?>/packages.php#luxury-safari">Luxury Safari</a></li>
          <li><a href="<?= BASE_PATH ?>/packages.php#safari-zanzibar">Safari + Zanzibar</a></li>
          <li><a href="<?= BASE_PATH ?>/packages.php#cultural-safari">Cultural Safari</a></li>
          <li><a href="<?= BASE_PATH ?>/#customize">Design My Safari</a></li>
        </ul>
      </div>

      <!-- Company column -->
      <div class="footer-nav-col">
        <span class="footer-col-title">Company</span>
        <ul class="footer-links">
          <li><a href="<?= BASE_PATH ?>/about.php">About Us</a></li>
          <li><a href="<?= BASE_PATH ?>/about.php#guides">Our Guides</a></li>
          <li><a href="<?= BASE_PATH ?>/contact.php">Contact</a></li>
          <li><a href="<?= BASE_PATH ?>/track.php">Track Booking</a></li>
          <li><a href="<?= BASE_PATH ?>/terms.php">Terms &amp; Conditions</a></li>
          <li><a href="<?= BASE_PATH ?>/privacy-policy.php">Privacy Policy</a></li>
        </ul>
      </div>

      <!-- Contact column -->
      <div class="footer-nav-col">
        <span class="footer-col-title">Get in Touch</span>
        <ul class="footer-contact-list">
          <li class="footer-contact-item">
            <span class="footer-contact-icon" aria-hidden="true">📍</span>
            <span>Njiro Road, Arusha, Tanzania</span>
          </li>
          <li class="footer-contact-item">
            <span class="footer-contact-icon" aria-hidden="true">✉</span>
            <a href="mailto:info@safarizanguven.com">info@safarizanguven.com</a>
          </li>
          <li class="footer-contact-item">
            <span class="footer-contact-icon" aria-hidden="true">📞</span>
            <a href="tel:+255663430934">+255 663 430 934</a>
          </li>
          <li class="footer-contact-item">
            <span class="footer-contact-icon" aria-hidden="true">⏰</span>
            <span>7am–9pm EAT<br><small>(4am–6pm UK time)</small></span>
          </li>
        </ul>
        <div class="footer-response-note">
          Typical WhatsApp response: under 1 hour<br>
          Email enquiries: within 24 hours
        </div>
      </div>

    </div><!-- /footer-grid -->

    <!-- BOTTOM bar -->
    <div class="footer-bottom">
      <p class="footer-copy">
        © <?= date('Y') ?> Safari Zangu Ventures Ltd. All rights reserved.
      </p>
      <div class="footer-bottom-links">
        <a href="<?= BASE_PATH ?>/terms.php">Terms &amp; Conditions</a>
        <span class="footer-bottom-sep" aria-hidden="true">·</span>
        <a href="<?= BASE_PATH ?>/privacy-policy.php">Privacy Policy</a>
        <span class="footer-bottom-sep" aria-hidden="true">·</span>
        <a href="<?= BASE_PATH ?>/contact.php">Contact</a>
      </div>
    </div>

  </div>
</footer>

FILEOF

echo '  Writing: layouts/navbar.php'
mkdir -p $(dirname $SERVER/layouts/navbar.php)
cat > $SERVER/layouts/navbar.php << 'FILEOF'
<nav class="sz-nav" role="navigation" aria-label="Main navigation">
  <div class="nav-inner">
    <a href="<?= BASE_PATH ?>/" class="sz-logo" aria-label="Safari Zangu Ventures — Home">SZV</a>
    <ul class="sz-nav-links" role="list">
      <li><a href="<?= BASE_PATH ?>/packages.php">Packages</a></li>
      <li><a href="<?= BASE_PATH ?>/#customize">Customize</a></li>
      <li><a href="<?= BASE_PATH ?>/about.php">About</a></li>
      <li><a href="<?= BASE_PATH ?>/contact.php">Contact</a></li>
      <li><a href="<?= BASE_PATH ?>/track.php">Track</a></li>
      <!-- <li><a href="<?= BASE_PATH ?>/blog/">Travel Guides</a></li> -->
      
      
      <!-- <li><a href="https://wa.me/255663430934?text=Hello!%20I%27d%20like%20to%20enquire%20about%20a%20Tanzania%20safari." target="_blank" rel="noopener" class="sz-nav-wa">💬 WhatsApp</a></li> -->
      <li><a href="<?= BASE_PATH ?>/book.php" class="sz-nav-cta">Book Now</a></li>
    </ul>
      
    <button class="nav-toggle" aria-label="Toggle menu" aria-expanded="false" aria-controls="mobile-nav">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<div class="sz-mobile-nav" id="mobile-nav" role="navigation" aria-label="Mobile navigation">
  <a href="<?= BASE_PATH ?>/packages.php">Packages</a>
  <a href="<?= BASE_PATH ?>/#customize">Customize Safari</a>
  <a href="<?= BASE_PATH ?>/about.php">About Us</a>
  <a href="<?= BASE_PATH ?>/contact.php">Contact</a>
  <a href="<?= BASE_PATH ?>/track.php">Track</a>
  <a href="<?= BASE_PATH ?>/book.php">Book Now →</a>
</div>
FILEOF

echo ""
echo "  Applying additional fixes..."

# Fix 1: Add wa-float to main.css if missing
if ! grep -q 'wa-float' $SERVER/css/main.css; then
cat >> $SERVER/css/main.css << 'CSSEOF'

/* ── WHATSAPP FLOAT BUTTON ─────────────────────────────────── */
.wa-float {
  position:fixed;bottom:1.75rem;right:1.75rem;z-index:500;
  display:flex;align-items:center;gap:.6rem;
  background:#16a34a;color:#fff!important;
  padding:.8rem 1.25rem .8rem 1rem;border-radius:50px;
  text-decoration:none;box-shadow:0 4px 20px rgba(22,163,74,.35);
  transform:translateY(120px);opacity:0;
  transition:transform .4s cubic-bezier(.34,1.56,.64,1),opacity .25s ease;
}
.wa-float svg{width:22px;height:22px;fill:#fff;flex-shrink:0}
.wa-float-label{font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;white-space:nowrap;color:#fff!important}
.wa-float--visible{transform:translateY(0);opacity:1}
.wa-float:hover{background:#15803d;box-shadow:0 6px 24px rgba(22,163,74,.5)}
@media(max-width:540px){
  .wa-float{bottom:1.1rem;right:1.1rem;padding:.9rem;border-radius:50%;width:52px;height:52px;justify-content:center}
  .wa-float-label{display:none}
}
CSSEOF
  echo "  Added: wa-float CSS"
fi

# Fix 2: Footer visibility override
if ! grep -q 'FOOTER VISIBILITY OVERRIDE' $SERVER/css/main.css; then
cat >> $SERVER/css/main.css << 'CSSEOF2'

/* ── FOOTER VISIBILITY OVERRIDE ─────────────────────────────── */
.sz-footer,.sz-footer p,.sz-footer span,.sz-footer li,
.sz-footer a,.sz-footer small,.sz-footer strong {color:#fff!important}
.sz-footer .footer-col-title{color:rgba(255,255,255,.8)!important}
.sz-footer .footer-bottom,.sz-footer .footer-copy,
.sz-footer .footer-bottom-links a,.sz-footer .footer-licence{color:rgba(255,255,255,.7)!important}
.sz-footer .footer-response-note{color:rgba(255,255,255,.6)!important}
.sz-footer .footer-bottom-sep,.sz-footer .footer-licence-dot{color:rgba(255,255,255,.3)!important}
.sz-footer a:hover{opacity:.75}
CSSEOF2
  echo "  Added: footer visibility override"
fi

# Fix 3: Add double-submit + WA float to main.js
if ! grep -q 'data-submitting' $SERVER/js/main.js; then
cat >> $SERVER/js/main.js << 'JSEOF'

/* ── Form double-submit prevention ─────────────────────────── */
(function(){
  document.addEventListener('submit',function(e){
    var form=e.target;
    if(form.id==='booking-form'||form.id==='contact-form'||
       form.id==='custom-safari-form'||form.id==='check-booking-form')return;
    if(form.dataset.submitting){e.preventDefault();return;}
    var btn=form.querySelector('[type="submit"]');
    if(btn&&!btn.dataset.noLock){
      form.dataset.submitting='1';
      var orig=btn.innerHTML;
      btn.disabled=true;
      btn.innerHTML='<span style="opacity:.7">Sending\u2026</span>';
      setTimeout(function(){
        delete form.dataset.submitting;
        btn.disabled=false;btn.innerHTML=orig;
      },15000);
    }
  });
}());

/* ── WhatsApp float button ──────────────────────────────────── */
(function(){
  var btn=document.getElementById('wa-float');
  if(!btn)return;
  setTimeout(function(){btn.classList.add('wa-float--visible');},3000);
  window.addEventListener('scroll',function(){
    if(window.scrollY>300)btn.classList.add('wa-float--visible');
    else btn.classList.remove('wa-float--visible');
  },{passive:true});
}());
JSEOF
  echo "  Added: double-submit + WA float JS"
fi

# Fix 4: Add WA button to layouts/app.php if missing
if ! grep -q 'wa-float' $SERVER/layouts/app.php; then
python3 - << 'PYEOF'
BASE = '/var/www/html/safari-zangu'
with open(BASE+'/layouts/app.php', encoding='utf-8') as f:
    ap = f.read()
ap = ap.replace(
    "<?php include __DIR__ . '/footer.php'; ?>",
    """<?php include __DIR__ . '/footer.php'; ?>
<a href="https://wa.me/255663430934?text=<?= urlencode('Hi Safari Zangu, I\\'d like to enquire about a Tanzania safari.') ?>"
   id="wa-float" class="wa-float" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">
  <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="width:22px;height:22px;fill:#fff;flex-shrink:0">
    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
    <path d="M11.999 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2.22 21.5l4.432-1.162A9.945 9.945 0 0011.999 22c5.523 0 10-4.477 10-10S17.522 2 11.999 2z"/>
  </svg>
  <span class="wa-float-label">Chat with us</span>
</a>"""
)
with open(BASE+'/layouts/app.php', 'w', encoding='utf-8') as f:
    f.write(ap)
print('  Added: WA float button to app.php')
PYEOF
fi

# Fix 5: Remove color:inherit from footer
python3 -c "
f=open('/var/www/html/safari-zangu/layouts/footer.php','r+',encoding='utf-8')
c=f.read().replace(' style=\"color:inherit;text-decoration:none\"','').replace(' style=\"color:inherit;\"','')
f.seek(0);f.write(c);f.truncate();f.close()
print('  Fixed: footer color:inherit removed')
"

echo ""
echo "=================================================="
echo "  VERIFY"
echo "=================================================="
python3 - << 'PYEOF2'
import re, subprocess
BASE = '/var/www/html/safari-zangu'
def R(f): return open(BASE+'/'+f,encoding='utf-8').read()
def php(f):
    r=subprocess.run(['/usr/bin/php8.3','-l',BASE+'/'+f],capture_output=True,text=True)
    return 'No syntax errors' in r.stdout

mc = R('css/main.css')
ac = R('admin/css/admin.css')
mj = R('js/main.js')

checks = [
    ('main.css: no DUPLICATE markers',      'DUPLICATE REMOVED' not in mc),
    ('main.css: pkg-gallery single def',    mc.count('.pkg-gallery {') <= 2),
    ('main.css: home-slider single def',    mc.count('.home-slider {') <= 2),
    ('main.css: wa-float present',          'wa-float' in mc),
    ('main.css: footer override',           'FOOTER VISIBILITY OVERRIDE' in mc),
    ('main.css: btn-sz-primary',            'btn-sz-primary' in mc),
    ('main.css: container-sz',              'container-sz' in mc),
    ('admin.css: no DUPLICATE markers',     'DUPLICATE REMOVED' not in ac),
    ('admin.css: single :root',             len(re.findall(r':root\s*\{',ac))==1),
    ('admin.css: admin-sidebar',            'admin-sidebar' in ac),
    ('admin.css: admin-table',              'admin-table' in ac),
    ('admin.css: admin-btn--success',       'admin-btn--success' in ac),
    ('main.js: data-submitting',            'data-submitting' in mj),
    ('main.js: wa-float handler',           'wa-float' in mj),
    ('main.js: pkg-gallery slider',         'pkg-gallery' in mj),
    ('layouts/app.php: WA button',          'wa-float' in R('layouts/app.php')),
    ('layouts/admin.php: PHP OK',           php('layouts/admin.php')),
    ('views/home.php: PHP OK',              php('views/home.php')),
    ('views/book.php: PHP OK',              php('views/book.php')),
    ('views/contact.php: PHP OK',           php('views/contact.php')),
    ('views/packages.php: PHP OK',          php('views/packages.php')),
    ('admin/index.php: PHP OK',             php('admin/index.php')),
    ('admin/bookings.php: PHP OK',          php('admin/bookings.php')),
    ('admin/quotations.php: PHP OK',        php('admin/quotations.php')),
    ('admin/packages.php: PHP OK',          php('admin/packages.php')),
    ('admin/email_queue.php: PHP OK',       php('admin/email_queue.php')),
    ('admin/reminders.php: PHP OK',         php('admin/reminders.php')),
    ('admin/itineraries.php: PHP OK',       php('admin/itineraries.php')),
    ('admin/users.php: PHP OK',             php('admin/users.php')),
    ('admin/gallery.php: PHP OK',           php('admin/gallery.php')),
    ('admin/login.php: PHP OK',             php('admin/login.php')),
]
all_ok=True
for label,ok in checks:
    print('  '+('✓' if ok else '✗')+'  '+label)
    if not ok: all_ok=False
print()
print('ALL PASS ✓' if all_ok else 'Check ✗ items')
PYEOF2

echo ""
echo "Run: sudo systemctl reload apache2"