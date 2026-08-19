# AIESEC in Deutschland — B2B Website · Build Status

> Working notes for future sessions. The full spec is in
> `AIESEC_CLAUDE_CODE_HANDOFF_v2.md`. This file tracks what's actually built and
> what's next. Design reference: `Copy of [BD] B2B Webseite.pdf` (read it before
> any new page). Guiding principle from the owner: **stay faithful to the
> reference, just lift the craft. Don't over-design.**

## Stack & deploy
- Pure HTML + CSS + vanilla JS. No frameworks, no build step. Open files directly.
- One shared stylesheet `assets/css/style.css`, one shared script `assets/js/main.js`.
  Sub-pages link them with `../` (e.g. `../assets/css/style.css`).
- Host: GitHub Pages → `unternehmen.aiesec.de`.
- **Forms: Web3Forms.** Access key `f0ba8c58-9857-47f0-b545-d6d283ebc418`.
  Every contact form posts to `https://api.web3forms.com/submit` with that key,
  a per-page `subject`, and (on sub-pages) a hidden `interest`/`profil` field so
  leads are tagged by page. `main.js` intercepts submit → fetch → green success state.

## Design system (already built — reuse, don't reinvent)
Defined in `assets/css/style.css`:
- **Tokens** at `:root` — brand blue `#037EF3`, `--blue-deep #0A1628`, `--teal #00C49A`,
  neutrals, layered `--shadow*`, `--radius*`, `--trans`. DM Sans everywhere; Dancing
  Script only for "Developing young leaders since 1948".
- **Icons:** inline SVG line-icons with class `.icon` (NO emoji anywhere). The `.icon`
  class sets stroke/fill — just drop in `<svg class="icon" viewBox="0 0 24 24">…</svg>`.
- **Reusable blocks:** `.btn`(+variants), `.section-overline`, `.section-title`,
  `.section-intro`, `.hero`(+`.hero--sub` shorter), hero word-reveal (`.hero-title .word`
  with staggered `animation-delay`), `.stats-grid`/`.stat-card` (animated counters via
  `data-target`), `.feature-grid`/`.feature-card`, `.process-layout`+`.steps`/`.step`,
  `.quote-card` (dark text testimonial), `.stories-grid`/`.story-card`, `.partners-logos`,
  `.section-dark` contact, `.footer`, `.cta-banner`.
- **Animation:** add `animate-up|left|right` + optional inline `animation-delay`;
  `main.js` IntersectionObserver adds `.in-view`. `prefers-reduced-motion` fully handled.
- **Scroll progress bar:** `<div class="scroll-progress" id="scrollProgress">` at top of `<body>`.

## DONE
- **`index.html` (homepage)** — fully rebuilt & polished. Navbar, cinematic hero
  (word-reveal), "Was ist AIESEC" (blue, 1948 script), 8-stat band, Standorte pills,
  4 event cards, CTA banner, 4 partnership value cards + DHL testimonial image,
  partner logo wall, dark contact form, footer. Verified desktop + narrow, zero
  horizontal overflow.
- **`global-talent/index.html`** (§5.1 Internationale Praktikant:innen) — hero,
  "Warum Global Talent?" (3 feature cards), "Recruiting in 4 Schritten" (numbered
  steps + side quote card), CTA banner, "Unser Netzwerk" (4 stats), "Erfolgsgeschichten"
  (2 story cards), partner wall, contact form. Verified.
- **`hochschulmarketing/index.html`** (§5.2) — hero, 3-Mio.-Studierende lead,
  "AIESEC Konferenzen" with 3 pricing tiers (Local/Regional[featured]/National) +
  **embedded YouTube video** (`.video-embed`, responsive 16:9 iframe, `youtu.be/h96R65tD08k?start=20`)
  where the fake placeholder was, region-grouped Standorte (Nord/West/Mitte&Ost/Süd),
  blue stat band (55.000+/3 Mio.+/75+/NPS 72), 2 testimonials (#2 now uses
  `partner-db-y2b.jpg`), Y2B partner wall, dark
  "Nationale Partnerschaft" CTA **now a text+photo split** (`partner-huawei-national.jpg`),
  contact form. Verified.
- **`anmeldung.html` (Partner-Anmeldung / registration page)** — replicates the old
  `/unternehmen/anmeldung` endpoint in the new template. Hero (MeCo community photo) +
  two-column section: credibility aside (MeCo photo, trust checklist, DAAD / Auswärtiges
  Amt / Transparente-Zivilgesellschaft logos) and the full form. Fields match the old
  site: Unternehmen, Website, Vorname, Nachname, E-Mail, Telefon, **Produktinteresse**
  (Internationale Praktikant:innen / Hochschulmarketing & Recruiting / Talentbindung /
  CSR / National Fördernder Beirat), **Stadt** (24 Standorte), **Quelle** (Wie gehört),
  2 consent checkboxes (contact opt-in pre-checked, Datenschutz required). Posts to
  Web3Forms (`subject="Partner-Anmeldung …"`), `id="contactForm"` so `main.js` intercepts.
  **Decision: kept as a dedicated page** (not a homepage section) — heavy 10-field B2B
  form, linkable/trackable conversion goal; light contact forms on each page stay for
  quick inquiries. All `nav-cta` "Jetzt Partner werden" buttons (home + 3 sub-pages)
  now point to `anmeldung.html`. New consent-checkbox CSS in `style.css` (`.form-consent`,
  `.trust-*`, `.anmeldung-*`, `.event-photo`, `.dark-cta-split`).
  **Website field (2026-08-19):** was `type="url"`, so the browser rejected anything
  without `https://`. Now `type="text"` + `inputmode="url"` with a lenient `pattern`
  that only requires a dot + a 2+ letter TLD (`aiesec.de` passes, `aiesec` doesn't).
  Placeholder `aiesec.de`, a `.form-hint` line under it, and a German error message via
  `setCustomValidity` in `main.js`. `main.js::normaliseWebsite()` trims and prepends
  `https://` on submit (only for values that pass the regex), so the lead still arrives
  as a clickable URL. The HTML `pattern` and the JS `WEBSITE_RE` must stay in sync — both
  tolerate surrounding whitespace so a pasted value validates.
  **Note:** `--gray-300`/`--gray-400` are NOT defined in `:root` (only 50/100/200/500/700/900)
  — using them yields an invisible border. Use a literal hex or a defined token.
- **`talentbindung/index.html`** (§5.3 GVP) — hero, 3 pain→Lösung cards, "Wie
  funktioniert das GVP?" (5-step flow + Broschüre CTA), 2 program cards (GVP +
  Global Volunteer, external link to aiesec.de/volunteer), CTA banner, 4 network
  stats, 2 stories, "Partnerländer & Projekte" (6 cards: map-pin tile + SDG badge —
  NOT flag emoji, which break on Windows), contact form. Verified.
- **All 4 contact forms wired to Web3Forms** (key `f0ba8c58-…`) with per-page
  `subject` + hidden `interest`/`profil`/`paket`/`teamgroesse` so leads are tagged.
- **Images optimized** — all photos resized/compressed (~47 MB → ~2.6 MB shipped).
  Originals backed up in `assets/images/_originals/` (**do NOT deploy this folder**).
  Latest batch wired in: `event-y2b-booth.jpg` (real Y2B career-fair booth → homepage
  Y2B event card, has a Tesla banner in shot), `partner-db-y2b.jpg` (DB rep at the
  Germany Y2B Forum banner → hochschulmarketing), `partner-huawei-national.jpg`
  ("Premium National Partner" / Huawei → hochschulmarketing dark CTA),
  `community-meco.jpg` (big MeCo group photo → anmeldung hero + aside). Source folders
  `assets/images/to be used as well in the  showcaing/` and `original anmeldung endpoint/`
  are raw drops — **do NOT deploy them**.
- **Logos fixed** — MLP, Ventuseo, Zoho, DB and the **AIESEC logo** all shipped with
  black backgrounds; black was keyed to transparent at the image level. All render
  clean full-color on white cards and the dark footer.
- **Nav/footer:** CSR links point to `#kontakt` (no CSR section exists yet). On
  sub-pages, top-level nav points back to home with `../#…`; `Kontakt`/`Partnerschaften`
  are local anchors.
- **Internal page links use explicit `index.html`** (e.g. `global-talent/index.html`,
  not `global-talent/`). Bare folder links don't open under local `file://` preview —
  the owner reviews locally, so always append `index.html`. Fixed across all 5 pages
  (nav dropdown, homepage Standorte pills, footer).

## DONE — premium interaction layer
Cursor-follow spotlight + subtle 3D tilt on cards (`.fx-card`, auto-applied via
`main.js` to stat/feature/event/package/project/story/logo cards), magnetic buttons,
button shine sweep, refined blur-in reveals, hero pointer-parallax (bg keeps
scale(1.06) overscan so it never gaps). All `prefers-reduced-motion` + touch guarded.

**High-end polish sprinkle (2026-06-09)** — additive only, no asset/content changes:
- **Film grain** — `.grain-overlay` (fixed, `mix-blend:overlay`, opacity .20, SVG `feTurbulence`
  data-URI) appended to `<body>` by `main.js`. Filmic over imagery/dark, ~invisible on white.
  Hidden under `prefers-reduced-motion`. (data: URI allowed by CSP `img-src data:`.)
- **Back-to-top** — `.to-top` button injected by `main.js`, shows after 600px scroll (rAF-throttled).
- **Partner-logo wall** — JS adds `.animate-up` + staggered `animationDelay`, revealed by a dedicated
  IntersectionObserver (logos were static before).
- **Nav underline** → blue→teal gradient; **`.partner-showcase figure`** lift+glow on hover.
- Owner directive: *high-end, catchy, professional* — but still **don't over-design** (subtle > flashy).

## DONE — fixes & load polish
- **Dropdown hover bug fixed** — the nav "Kooperationsmöglichkeiten" menu had a gap
  that closed it before you could reach the items. Fixed with an invisible `::before`
  bridge over the gap, an 8px (was 12px) offset, a `.22s` close delay, and
  `:focus-within` (keyboard). All in `.dropdown-menu` CSS.
- **Page-load entrance** — navbar drops in (`@keyframes navDrop`), hero image focuses
  in from a soft blur (`@keyframes heroFocus`), hero content cascades. Reduced-motion safe.

## DONE — QA, security & honesty pass (2026-06-08)
- **Fabricated testimonials REMOVED.** A prior session invented customer quotes + names
  (Anna Klein, Markus Roth, Julia Krämer, Sandra Meyer, Thomas Berger, Claudia Wagner,
  David Lang). These were NOT from any source. Removed the 3 standalone testimonial
  sections (`#stimmen` on hochschulmarketing, `#erfolge` on global-talent & talentbindung)
  and converted global-talent's in-process quote card to an honest non-attributed CTA card
  (`.quote-card.is-cta`). See DECISIONS: never fabricate testimonials/quotes/stats.
  (Side effect: resolved the `about-1.jpg` double-use; freed `partner-db-y2b.jpg` &
  `event-y2b-networking.jpg` for reuse.)
- **Tesla added** to partner walls (home, global-talent, hochschulmarketing) —
  `assets/images/logo-tesla.png` (red, square; sits slightly smaller than wordmark logos).
- **YouTube embed** = click-to-load facade (`.video-facade` in `main.js`), loads
  `youtube-nocookie.com` only on click. Fixes "error 153" (player rejects `null` origin
  on local `file://` preview) and lazy-loads. Poster from `i.ytimg.com`.
- **CSR links fixed** — were local `#kontakt` on sub-pages (so CSR "went to GVP"); now all
  point to homepage contact (`../index.html#kontakt`).
- **Logo / "Für Unternehmen" home links fixed** — sub-pages used bare `../` (opens a dir
  listing under `file://`); now `../index.html`.
- **OWASP pass:** CSP + `referrer` `<meta>` on all 5 pages (script/style/img/font/frame/
  connect/form-action allowlisted; blocks injected scripts). No inline JS/handlers; all
  `target=_blank` carry `rel=noopener`. **Limit:** GitHub Pages can't send real HTTP
  headers — `X-Frame-Options`/HSTS need a proxy (Cloudflare) in front for full hardening.
- **Link validator** (regex sweep of every href/src/anchor across 5 pages) → all resolve.
- **Deploy artifacts created:** `.gitignore` (excludes originals/raw drops/reference docs/
  root screenshots), `.nojekyll` (plain static serving), `CNAME` (`unternehmen.aiesec.de`).
  Repo not yet `git init`-ed.

## DONE — DSGVO / no-external-calls pass (2026-07-29)
Owner requirement: **no Google Fonts, no external service calls** (German data-protection
exposure — the LG München I ruling on embedded Google Fonts, Az. 3 O 17493/20).

- **Google Fonts removed → self-hosted.** All 6 pages dropped the two `preconnect`s and the
  `fonts.googleapis.com/css2` stylesheet. Fonts now live in `assets/fonts/` as woff2
  (`dm-sans-latin`, `dm-sans-latin-ext`, `dancing-script-latin`, `dancing-script-latin-ext`
  — ~134 KB total), declared as `@font-face` at the top of `style.css`. **Both families are
  variable fonts**, so ONE file per unicode subset covers every weight (DM Sans declared
  `font-weight:300 800`). Licence: SIL OFL 1.1 — self-hosting is permitted.
  Each page now `preload`s `dm-sans-latin.woff2` instead of preconnecting to Google.
  **`@font-face` URLs are relative to style.css** (`../fonts/…`), so they resolve from
  root pages and sub-pages alike — do not "fix" them to page-relative.
- **YouTube poster de-Googled.** `hochschulmarketing` loaded its thumbnail from
  `i.ytimg.com` on page load — a Google request before any consent. Downloaded to
  `assets/images/video-y2b-poster.jpg` and referenced locally.
- **Video consent notice added.** `.video-consent` (CSS) + a line in the `.video-facade`
  on hochschulmarketing: clicking loads YouTube and transfers data to Google, linking the
  Datenschutzerklärung. `main.js::load()` now ignores clicks that land on an `<a>` so the
  link is clickable without starting the player.
- **CSP tightened** on all 6 pages — `style-src` and `font-src` are now `'self'`,
  `img-src 'self' data:`, `frame-src` only `youtube-nocookie.com`. Anything that tries to
  reach Google again will now be *blocked by the page itself*, not just absent.
- **Verified by netlog, not by eye.** Headless Chrome with background networking disabled,
  netlog diffed against an `about:blank` baseline: **zero page-attributable external
  requests** on all 5 real pages. (`csp.withgoogle.com` shows up in the raw log only inside
  a *response header* of Chrome's own safebrowsing call — no DNS, no socket. Ignore it if
  you re-run this.) Screenshots confirmed DM Sans 300/400/600/800 + Dancing Script + umlauts
  all render from the local files.

### Still open (owner's call, NOT code problems)
- **Web3Forms** (`api.web3forms.com`) is still the form backend. It only fires on submit
  (user-initiated), so it is not a page-load leak — but it is a third-party processor
  receiving name/e-mail/phone/company, so it needs an AVV and a mention in the
  Datenschutzerklärung. The owner plans to replace it with a self-hosted DB backend, which
  removes the issue entirely.
- **GitHub Pages** is US-hosted (Microsoft/Fastly) and sees every visitor IP. Common and
  widely tolerated, but belongs in the Datenschutzerklärung. Moving to EU hosting would be
  the fully clean answer.
- **Datenschutzerklärung** currently links to `aiesec.de/datenschutz`, which won't mention
  this site's form processor or host. Needs updating by whoever owns that page.

## DECISIONS (settled — do not relitigate)
- **NEVER fabricate content.** No invented testimonials, customer names, quotes, logos, or
  statistics. Social proof must come from the owner (real, with permission) or not appear.
  This caused a credibility incident on 2026-06-08; do not repeat.
- **Follow the PDF reference exactly.** Owner chose to keep the homepage and overall
  flow faithful to `Copy of [BD] B2B Webseite.pdf`. The reference + flow is the
  ultimate factor — polish/effects on top are welcome, new sections are not.
- **No "Unser Ansatz"/"our way" showcase section** was added (owner picked option B).
  The "how we work" story lives in the sub-pages' existing process sections
  (`.steps`). Do not add it to the homepage unless the owner explicitly reverses this.

## NEXT — REBUILD Kooperationsmöglichkeiten from the REAL old site

**Repo moved (2026-07-29).** Work now happens on the AIESEC org repo —
**`AIESECGermany/bdaiesec-2026`** (public), which is a fork of the original
`theonlymosmos/bdaiesec`. Git remotes in this working copy:
- `origin` → `AIESECGermany/bdaiesec-2026` ← **push here**
- `personal` → `theonlymosmos/bdaiesec` (the old repo; kept as a backup mirror, now behind)

The owner has WRITE on the org repo and treats it as his own — direct pushes to `main` are
fine, no PR ceremony needed. No branch protection is set.

**GitHub Pages is NOT yet enabled on `AIESECGermany/bdaiesec-2026`** — pushing does not
deploy anything live. Someone with admin on the org repo has to switch Pages on
(Settings → Pages → Deploy from branch `main` / root). Until then the site is code-only.
The old repo's Pages (`https://theonlymosmos.github.io/bdaiesec/`) is stale.
CNAME removed for now (re-add `unternehmen.aiesec.de` once Pages is on the org repo).
Rollback tags on the old repo: `backup-2026-06-09-live|polish|wow`.

**The 3 cooperation pages + CSR are currently DEACTIVATED** (redirect to `coming-soon.html`)
because their copy was invented, NOT from any source. They must be rebuilt from the OLD
site captures in `oldwebsite endpoints/old website/` (image-only PDFs — render with PyMuPDF
`get_pixmap(matrix=fitz.Matrix(1.85,1.85))`, Letter-sized pages, read one at a time).
Design language = the homepage/`style.css` system + the B2B reference where it applies.
**RULE: transcribe copy verbatim from the PDFs at higher zoom during build — invent nothing.**

### Real old-site structure (verified by reading the PDFs 2026-06-09)
Headings/labels below were read directly; longer body paragraphs were partly legible and
MUST be re-read at higher zoom before transcribing (do not paraphrase from memory).

- **Global Talent** (`…global-talent…pdf`, 4pp): Hero "**Internationale Talente**" + Global
  Talent logo + "Jetzt anmelden" (sample role card: "Web Development Intern · 26 weeks ·
  Munich, Germany"). → "**Warum Global Talent?**" 3 cards: *Passende Talente gewinnen* /
  *Globale Perspektiven* / *Zukunftsfähige Unternehmenskultur*. → "**Recruiting in vier
  Schritten**": 1 *Veröffentlichung der Ausschreibung*, 2 *Auswahl der Praktikant:innen*,
  3 *Administration und Logistik*, 4 *Betreuung in Deutschland*. → "**Unser Netzwerk**"
  stats: 250+ Partner in Deutschland · 100+ (2nd label hard to read — VERIFY) · 5000+ Aktive
  Bewerber auf unserer Plattform · 70+ Jahre Erfahrung. + 3 colored "Mehr erfahren" cards
  (content cut across pages — VERIFY). → teal CTA "**Jetzt internationale Talente mit AIESEC
  finden!**" → partner logos → footer.
- **Hochschulmarketing** (`…branding-recruiting…pdf`, 3pp): Hero "**Hochschulmarketing &
  Recruiting**" + "Kontakt aufnehmen". → "**Warum mit AIESEC?**" stats: **70+** Jahre
  Erfahrung · **600** Ehrenamtliche Mitglieder in Deutschland · **28** Standorte
  (Lokalkomitees) in Deutschland · **42** Universitäten in unserem Netzwerk. → blue CTA
  band "Wir nehmen in Kürze mit Ihnen Kontakt auf." → "Unsere Partner" logos → footer.
  **NOTE:** the real page is SIMPLE — it has NO Local/Regional/National pricing tiers, NO
  region-grouped Standorte, NO testimonials. The current built page invented all of that.
- **Talentbindung** (`…talentbindung…pdf`, 3pp): Hero "**Talentbindung**" + "Kontakt
  aufnehmen". → "**Wie funktioniert es?**" 2 cards: *Angebot für den Talentpool* / *Junge
  Talente entwickeln* (body legible-but-blurry — VERIFY). pp2–3 not yet read. The current
  built page (GVP, 3 pain cards, 5-step flow, Partnerländer) is invented — replace.
- **CSR** (`…csr…pdf`, 4pp): Hero "**(Corporate) Social Responsibility**" + "Kontakt
  aufnehmen". → "**Unsere Ziele für Deutschland**" (body not yet read). pp2–4 not yet read.
  The site currently has NO CSR page (removed); the old site HAS one — rebuild + re-add to nav.
- Also present in the folder (not yet built): **Talentprofile** (2 PDFs) and **Youth2Business
  Forum** (the Y2B page also appears in the B2B reference PDF pp.6–7).

### Real partners on the old site (logos we may be MISSING)
DHL · DB · **pwc** · Huawei · **DAAD** · **Bayern LB** · **HHLA** · MLP · **NORD/LB?** (verify).
The current built wall uses Mondelēz/Zoho/ING/Ventuseo/Tesla — reconcile with the owner.

### Cross-check flag (verify before trusting current numbers)
Homepage stat band (650+ Mitglieder, 40+ Unis, 24 Standorte, 75+ Jahre) does NOT match the
old site's hochschulmarketing numbers (600 / 42 / 28 / 70+). Homepage follows the B2B
reference design — confirm the homepage numbers against the B2B PDF and the owner; don't
assume either set is correct.

### Suggested order
1. Global Talent (most content already mapped) → 2. Hochschulmarketing (simplify to the
real short structure) → 3. Talentbindung → 4. CSR (+ re-add to nav). Per page: re-render the
PDF, transcribe exact copy, build in the design system, wire real photos/logos we actually
have, verify desktop+mobile, commit, then flip its `coming-soon` redirect off.

## Verify a page (headless Chrome, no extra tooling)
Chrome on Windows clamps the min viewport to ~484px, so true mobile can't be shot
locally — but `scrollWidth ≤ innerWidth` at 484/752/1424 confirms no overflow.
Heroes use `min-height:vh`, so for a full-page screenshot, inject a preview style
capping `.hero{min-height:640px}` and forcing `.animate-*{opacity:1}`, shoot at a tall
window (e.g. `--window-size=1440,5800`), then slice the PNG to read it.

## Don'ts
- Don't deviate from the reference layout/feel without asking.
- Don't reintroduce emoji icons — use `.icon` SVGs.
- Don't hard-delete or overwrite originals in `_originals/`.
- Don't add new external dependencies. Pure HTML/CSS/JS only.
