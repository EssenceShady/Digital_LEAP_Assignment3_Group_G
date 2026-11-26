# Master prompt — Digital LEAP, next build

Copy the whole of this file into the new session. It is written to stand alone;
where it refers to `docs/REDESIGN-BRIEF.md`, read that file first for the
reasoning behind a decision.

---

## 0. Mission

You are continuing **Digital LEAP**, the internship management system for
Limkokwing University, Sierra Leone (COMP217 Assignment 3 / final project,
Group G). The current build works and is documented, but four things are
wrong from the owner's point of view:

1. The presence/tracking model is too complex to explain. Nobody can say who
   checks that a student reached the site.
2. The landing page does not capture attention, and there is no branded
   loading screen.
3. Offline mode fails on a phone, and some members' devices render the layout
   broken or not full-screen.
4. The demo data has drifted from testing, and DIVA still describes the older
   system.

Your job is to rebuild the presentation, simplify presence into a model a
first-year can explain, make the app dependable on phones from 320 px to
tablets, fix offline honestly, reseed a clean demo, update DIVA, and make a new
Codespace open the application on first click — then raise the finish with the
polish work in §13.

Work in phases. Finish and verify one phase before starting the next. Do not
ask for confirmation between phases; run to completion, except for the four
stop conditions in §12.

### 0.1 Already shipped — do not undo

These visual changes were made at the owner's request (25 November, extended on
26 November 2026) and are part of the baseline your build starts from. Treat
them as requirements, not proposals:

1. **The floating corner button is DIVA, not feedback.** `app/Views/layout.php`
   renders `a.diva-fab` → `url('diva')`, gated on `Auth::can('diva.use')` and
   hidden when `$route === 'diva'`. Feedback still opens from the account menu
   (**Send feedback**) and the sidebar (**Share feedback**); `app.js` binds every
   `[data-feedback-open]`, so never reintroduce a second floating feedback
   button.
2. **The sign-in backdrop is the owner's photograph.** `body.auth-shell` uses
   `img/campus-graduation.jpg` (1904 × 680, the supplied graduation photograph
   enlarged 2× and sharpened) at `center 36% / cover` with
   `background-attachment: scroll` — never `fixed`, which forced a repaint on
   iOS. Do not replace it with a generated scene; enhance it in place.
3. **The landing is the owner's campus photograph, with the globe as its
   seal.** `app/Views/auth/landing.php` centres the copy above a full-width
   `figure.home-stage` carrying `img/campus-flags.jpg` (1320 × 741, the supplied
   flag-waving crowd) with `campus-flags-960.jpg` below 768 px, and the supplied
   globe as `img/globe-flag-sphere.png/.webp` in a `<picture>` seal. The caption
   reads **Students at Limkokwing University, Sierra Leone**. No paragraph sits
   over a face; the proof strip sits below the photograph. Do not replace either
   image with a generated scene.
4. **The boot splash turns the owner's globe.** `div.leap-boot[data-leap-boot]`
   lives in `layout.php`; `public/assets/js/boot.js` loads synchronously in the
   head, writes `sessionStorage['leap.booted']` and adds `html.leap-booting`
   only on a cold tab. The coin is two back-to-back `<picture>` faces with
   `backface-visibility: hidden`; CSS spins it, the glint counter-rotates, two
   rings orbit and a determinate meter fills. Once per tab, 1.0–2.6 s
   (450–1400 ms calm), never on a back navigation, skipped without JS or
   storage. See §6.3.

Evidence: TC-246 to TC-249 and TC-271 to TC-278 in `docs/test-log.md`.

---

## 1. Environment

Local (the owner's PC):

- Root: `C:\xampp\htdocs\Digital_LEAP_Assignment3_Group_G`
- App: `digital-leap/` · entry `digital-leap/index.php`
- URL: `http://localhost/Digital_LEAP_Assignment3_Group_G/digital-leap/`
- PHP CLI: `C:/xampp/php/php.exe` (8.x). MariaDB:
  `C:/xampp/mysql/bin/mysql.exe -u root`
- No Docker, no `gh`, no Python, no Ruby, no Composer on this machine.
- PHP GD is **not** available; image work must use PowerShell
  `System.Drawing` or CSS.

Codespaces (the demo host):

- Repo `EssenceShady/Digital_LEAP_Assignment3_Group_G`, app served by Apache
  with `DocumentRoot` set to `digital-leap/`, port 8080.
- `base_url` is `''`, so the app must work at the host root and must not
  hard-code `/digital-leap`.
- Read `docs/REDESIGN-BRIEF.md` §8 before touching the devcontainer.

Current facts you can rely on, but re-verify before quoting them:

- 130 routes: 11 public, 9 auth-only, 110 permission-gated (`app/routes.php`).
- 33 tables in `database/digital_leap.sql`; seed has 12 users, 5 students,
  5 placements, 4 visits, 12 logbook entries, 9 organisations.
- Roles: 1 administrator, 2 coordinator, 3 academic mentor, 4 host supervisor,
  5 student, 6 management/AQA.
- Permissions of note: coordinator has `visit.view` (18), not `visit.manage`
  (19); mentor has both.
- `SiteCheckin::MAX_DISTANCE_M = 500`; organisation pin is the `setting` key
  `geo.org.<org_id>` (JSON `{lat,lng}`).
- `sw.js` is at the app root, `VERSION = 'v12'`; `SHELL` precaches the CSS, JS
  (including `js/boot.js`), the globe sphere, logo and icons but not the
  photographic backdrops.
- Header wordmark is `public/assets/img/leap-wordmark-ink.png`.
- Corner launcher is `a.diva-fab` (44 px tap target, charcoal with a gold
  hairline) — not a feedback button. See §0.1.

Demo accounts (keep working; passwords are in the seed comments and on the
sign-in demo buttons):

| Role | Email | Password |
| --- | --- | --- |
| Student | mohamedalie247@gmail.com | Student@1234 |
| Host supervisor | supervisor@brightfuture.sl | Super@1234 |
| Academic mentor | deborahjulietmoiforay@gmail.com | Mentor@1234 |
| Coordinator | bayohmusa001@gmail.com | Musa@2026 |
| Management / AQA | fatmatabfc@gmail.com | Fatmata@2026 |
| Administrator | sesayshadrachsaidu@gmail.com | Shadrach@2026 |

---

## 2. Non-negotiables

These are standing instructions from the owner. They override convenience.

- **No external APIs, keys or cloud services in the shipped system.** "Online"
  means our own server. Anything that reaches the internet is an administrator
  setting, off by default, and needs the owner's explicit approval first.
- **Identity:** charcoal `#111315`, ivory `#f6f3ec`, gold `#b8912f` /
  `#d8b75c`, line `#e7e1d5`; Segoe UI for interface text, Georgia for display.
  The Limkokwing globe is a seal on this identity, not a replacement for it.
- **Never edit or regenerate** `Internship_Guidebook_LEAP_Clean_Layout.pdf`.
  Quote it; do not change it.
- **Never read, print, commit or modify** `digital-leap/config/config.php`.
  Commit only `config/config.sample.php` and the Codespaces config.
- **No destructive database operations.** Schema changes are additive (new
  tables/columns as needed). Never delete user data; tombstones only. Never
  drop or truncate a live database.
- **Never modify or reuse anything in the Assignment 2 folder.**
- **No new frameworks:** no React, Vue, jQuery, Bootstrap, Tailwind, Composer
  packages, icon libraries or web fonts. Vanilla JS modules and plain CSS only.
  All assets local; CSP stays `script-src 'self'`, no inline scripts.
- **utf8mb4 everywhere; PHP 8.0-compatible code**, even though the codespace
  runs 8.3.
- **Keep the demo seed accounts working** through every migration.
- **Do not create git commits or push** unless the owner asks in the current
  message.
- Location is read **once, on a button press**. No continuous tracking, no
  background location, no maps, no third-party geo service.

---

## 3. Start here (Phase 0)

1. Read `docs/REDESIGN-BRIEF.md`, `README.md`, `docs/DEMO.md`,
   `docs/test-log.md` (last 60 lines), `docs/design-system.md`.
2. Open `digital-leap/app/routes.php`, `app/Services/SiteCheckin.php`,
   `app/Services/Attendance.php`, `app/Controllers/MentorController.php`
   (`visitCheckin`, `visitComplete`), `app/Controllers/CoordinatorController.php`
   (`siteCheckin`), `app/Views/mentor/visits.php`,
   `app/Views/coordinator/placement_view.php`.
3. Confirm the four route counts and the seed counts with the commands below,
   and write the result into the first line of `docs/test-log.md` under a new
   dated heading.
4. Take a baseline screenshot set at 1440, 768 and 390 of the landing, student
   dashboard, mentor visits, coordinator placement view and DIVA.

```bash
"/c/xampp/php/php.exe" -r '$r=require "digital-leap/app/routes.php";$p=$a=$g=0;foreach($r as $v){if($v[2]===null)$p++;elseif($v[2]==="auth")$a++;else$g++;}echo count($r)." total, $p public, $a auth, $g gated\n";'
curl -s -o /dev/null -w "%{http_code}\n" "http://localhost/Digital_LEAP_Assignment3_Group_G/digital-leap/"
```

Lint every PHP file you touch:

```bash
find digital-leap -name '*.php' -print0 | xargs -0 -n1 "/c/xampp/php/php.exe" -l
```

---

## 4. Phase 1 — Codespace that opens the app

Goal: one link, no GitHub account, the landing page on screen.

1. Keep `deploy/Dockerfile` on `php:8.3-apache-bookworm`; keep
   `APACHE_DOCUMENT_ROOT=/var/www/html/digital-leap`.
2. Keep the compose paths **relative to the compose file** (`context: ..`,
   `../digital-leap`, `../.devcontainer/config.codespaces.php`,
   `../database/digital_leap.sql`). Compose does not export
   `localWorkspaceFolder`; do not reintroduce it.
3. In `devcontainer.json`: forward 8080, label "Digital LEAP",
   `onAutoForward: "openBrowser"`, `portsAttributes.visibility: "public"`,
   `service_started` for the DB dependency, a `postCreateCommand` that runs
   setup and then curls the landing page through Apache and fails loudly.
4. Add `deploy/404.html` — a plain "The demo server is not running" page with
   the reason and the two likely fixes. Configure Apache `ErrorDocument 404`
   to it.
5. In the landing footer, when `HTTP_HOST` ends with `app.github.dev`, render a
   one-line note: demo server, stops when the codespace stops.
6. Verify: create a codespace; in its terminal,
   `curl -s -o /dev/null -w "%{http_code}" localhost:8080/` must print 200;
   then from outside, the `-8080.app.github.dev` URL must serve the landing
   page with no sign-in. If it redirects to `github.dev/pf-signin`, the port is
   private — set Ports → 8080 → Public and say so in the final report.
7. Record the recipe in `README.md` and `INSTALL.txt`: create · wait · make
   public · share the `-8080` URL · stop when done.

Acceptance: a private browser window with no GitHub session reaches the
landing page; a stopped codespace shows the plain 404 page, not a crash.

---

## 5. Phase 2 — Device-safe CSS

Goal: no member sees an unstyled or half-width page.

The stylesheet is built on `@layer`, which Chrome < 99, Edge Legacy, Samsung
Internet < 15 and Windows Phone discard entirely. Flatten it.

1. Back up `app.css` in git (do not duplicate it in the tree).
2. Remove every `@layer a, b, c;` statement and every `@layer name { … }`
   wrapper, preserving source order. The order the layers encoded is the order
   the blocks already appear in. Keep the section comments.
3. Check specificity regressions page by page; where a later low-specificity
   rule must beat an earlier high-specificity one, raise the later selector's
   specificity deliberately rather than adding `!important`.
4. Confirm the current device fixes survive:
   `viewport-fit=cover` on the viewport meta, `env(safe-area-inset-*)` on
   `.topbar`, `.site-foot` and `.sidebar`, `100dvh` after `100vh`, the status
   chip at bottom-right, header actions `white-space: nowrap`, 44 px targets.
5. Add a 320 px pass: `overflow-wrap: anywhere` on emails, URLs, registration
   numbers and file names; stacked tables via the existing `.table--stack`.
6. Old-browser behaviour: if `navigator.serviceWorker` is absent, the app must
   still work online and must not show install errors. Guard optional APIs.

Verify at 320, 360, 375, 390, 412, 768, 820, 1024, 1280, 1440. For each: no
horizontal scroll (`document.documentElement.scrollWidth <= clientWidth`), no
overlapping fixed elements, header controls on one line (or deliberately
stacked), readable 16 px body text.

Acceptance: Chrome 90 emulation renders the full design; 320 px has no
horizontal scroll on landing, dashboard, logbook, visits and DIVA.

---

## 6. Phase 3 — Brand, landing, loading

### 6.1 Assets (already prepared, keep them)

```
public/assets/img/leap-wordmark-ink.png     transparent charcoal wordmark (light surfaces)
public/assets/img/leap-wordmark-ivory.png   transparent ivory wordmark (dark surfaces)
public/assets/img/leap-wordmark-gold.png    transparent gold wordmark (accents only)
public/assets/img/leap-mark.png             full badge on black (social/splash plate)
public/assets/img/leap-globe.png            older 456 px globe crop, kept but unused on screen
public/assets/img/globe-flag-sphere.png     340 × 340 globe crop, splash coin + landing seal
public/assets/img/globe-flag-sphere.webp    the same crop in WebP, 22 KB
public/assets/img/campus-flags.jpg          1320 × 741, 261 KB, landing stage
public/assets/img/campus-flags-960.jpg      960 × 539, 109 KB, phone candidate
public/assets/img/campus-graduation.jpg     1904 × 680, 229 KB, sign-in backdrop
public/assets/img/campus-graduation-960.jpg 960 × 343, 92 KB, phone strip
public/assets/img/icon-192.png, icon-512.png  app icon
public/assets/img/limkokwing-logo.png       LUCT lockup — footer only
public/assets/img/diva-mark-sm.png          gold brain mark, transparent
docs/brand-source/, docs/brand-preview/     originals and working copies
```

Rules: never stretch the wordmark (ratio 1.79 : 1, height 28–40 px in
headers); no wordmark on busy photography; the globe sphere shown no larger
than ~210 CSS px; app icon legible at 48 px.

`campus-flags.jpg` and `campus-graduation.jpg` are the owner's own photographs.
Caption the landing stage *Students at Limkokwing University, Sierra Leone* and
the sign-in picture *Limkokwing graduation*; enhance them in place and never
swap either for a generated scene. The globe is likewise supplied artwork: crop
it, never redraw it. No generated scene should be reintroduced on these
surfaces.

### 6.2 Landing page

The landing was restructured on 26 November 2026 around the owner's photograph
and is settled: on `app/Views/auth/landing.php` a centred copy block (kicker →
`h1` capped at 22 ch → 64 ch lead → CTA pair) sits above a full-width
`figure.home-stage` — the flag-waving crowd, a sheen, the globe seal and a
caption chip — with the four-figure proof strip below it. The coordinator
dashboard preview now lives in `section.home-workspace#workspace` beside three
numbered notes, not in the hero. The hero is one column at every width; the
stage photo is `clamp(320px, 43vw, 540px)` tall on desktop and
`clamp(250px, 64vw, 380px)` on a phone. Keep text off the faces. At ≤ 768 px
the caption chip moves to the lower left, `max-width: calc(100% - 100px)`, so
the fixed Online status dot in the lower right never covers it. Improve it
only in the ways listed in §13.

The sections below the hero are the three rebuilt blocks: **How a placement
moves** (five numbered steps), **Six roles, one record** (six portal tiles) and
**Proof, not paperwork** (three evidence cards, then one dark CTA band and a
slim footer). Keep them.

Scroll reveal: `initReveal()` adds `js-reveal` and `.is-in` through
`IntersectionObserver` on 17 `[data-reveal]` nodes; the hidden state exists only
under `html.js-reveal`, so the page is fully visible without JavaScript, and
both reduced-motion paths skip the effect.

### 6.3 Boot splash

Shipped form, in `layout.php` and `public/assets/js/boot.js`:

- The markup is present on every page; CSS displays it only under
  `html.leap-booting`, which `boot.js` adds on a cold tab and removes on leave.
  A second navigation never shows it.
- `boot.js` is synchronous in the head so the overlay exists at first paint. It
  writes `sessionStorage['leap.booted']` immediately; with no storage or no JS
  the splash is skipped entirely and no page depends on it.
- Timing: minimum 1,000 ms and maximum 2,600 ms from navigation start; reduced
  motion 450 / 1,400 ms. On finish the overlay fades 420 ms and is hidden.
- The coin is two `<picture>` faces back to back (`backface-visibility: hidden`,
  the back mirrored `scaleX(-1)`), spun by `leapCoin` over 8 s; the glint
  counter-rotates in `screen` blend; two rings orbit at 17 s and 24 s; the orb
  bobs 8 px. A determinate meter fills 6 %→94 % over 2.6 s.
- The wordmark is the ivory variant, the subtitle is two spans (stacked ≤480 px)
  and the background is two radial gradients on `#0b0d0e`.
- Reduced motion stops every animation and leaves the meter at 64 %. `@media
  print` hides the overlay.

Acceptance: a cold tab shows the turning globe; a second load does not; a phone
shows the same composition with a visible meter fill; reduced-motion shows a
still frame; the landing fits without horizontal scroll at 360 px.

---

## 7. Phase 4 — Presence, simplified

This is the centre of the work. Read `docs/REDESIGN-BRIEF.md` §1 first.

### 7.1 The model

Four verbs, one verifier each:

| Verb | Who | How | Rule |
| --- | --- | --- | --- |
| **Arrived** | Student | One reading at the button press | ≤ 500 m of the placement pin; attested note when GPS is unavailable, always flagged |
| **Confirmed** | Host supervisor | Weekly confirmation of days attended | Cannot be given by the student or mentor |
| **Visited** | Mentor or coordinator | Scheduled visit + strict GPS check-in | Completion blocked without it; > 500 m refused |
| **Reviewed** | Coordinator | Exceptions queue | Accept · Query · Escalate; every action audited |

Nobody verifies themselves. Student self-report never counts as *verified*.

### 7.2 Data (additive only)

Do not rewrite `audit_log` usage for existing events; it stays the event
store. Add:

- `presence_event` — `event_id`, `placement_id`, `kind`
  (`arrival|confirmation|visit|review`), `actor_user_id`, `actor_role`,
  `method` (`gps|attested|attested_weekly|strict_gps|manual`),
  `captured_at`, `synced_at`, `lat`, `lng`, `accuracy_m`, `distance_m`,
  `status` (`recorded|flagged|accepted|queried|escalated`), `note`,
  `source_audit_id`, `created_at`. Index `(placement_id, captured_at)`.
- `presence_exception` — `exception_id`, `placement_id`, `presence_event_id`,
  `kind`, `opened_at`, `state` (`open|accepted|queried|escalated|closed`),
  `resolved_by`, `resolved_at`, `resolution_note`.
- `placement_site` — `placement_id`, `lat`, `lng`, `radius_m` (default 500),
  `set_by`, `set_at`. If the organisation pin is set, derive from it; the
  placement-level row overrides for multi-site placements.

Seed those tables from the existing `sitevisit.checkin` and attendance events
so history is preserved. Migration must be idempotent and must not delete.

### 7.3 Service

`app/Services/Presence.php`:

```
recordArrival(int $userId, array $placement, ?float $lat, ?float $lng, ?float $acc, string $attestedNote = ''): array
confirmWeek(int $supervisorId, array $placement, int $weekNo, array $days, string $note = ''): array
recordVisit(...)      // delegates to SiteCheckin; do not duplicate the 500 m logic
reviewException(int $userId, int $exceptionId, string $action, string $note): array
timeline(int $placementId, int $limit = 50): array
exceptions(int $limit = 50): array
```

Rules: reuse `Attendance::distance()` and the org/placement pin; capture the
client time but validate against server time (flag > 15 min skew); one arrival
per student per working day (duplicate → a clear refusal, not a second row);
one confirmation per placement per week; every write appends to `audit_log`.

### 7.4 Screens

- **Student · Presence** — today's status, this week's days, a button "I have
  arrived", the timeline, and a plain sentence: "Your location is read once,
  when you press the button."
- **Supervisor · Confirmation** — one card per week: the days the student
  recorded, checkboxes, "Confirm these days", optional note.
- **Mentor · Visits** — unchanged behaviour, now reading from `Presence`, with
  the four-verb labels.
- **Coordinator · Presence** — two tabs: *Needs review* (the queue) and
  *Placements* (timeline per placement). One list of visits with a *Visitor*
  column; no separate "staff visits" card.
- **Management** — read-only presence coverage: % weeks confirmed, open
  exceptions, visits completed.

Navigation: one entry, **Presence**, per role; remove the duplicate "Staff site
visits" card from the placement page and put its content in the timeline.

### 7.5 Exceptions

Auto-open on: arrival outside the radius (refused attempt), arrival attested
with no GPS, week ended unconfirmed, visit date passed without check-in,
verified visit but no findings, client/server time skew > 15 min.

Acceptance: AT-09 to AT-14 in the brief. In particular: a 1.8 km arrival is
refused with the distance; an attested arrival appears in the queue; a mentor
cannot complete a physical visit unchecked; the coordinator sees one visit list
with both roles.

---

## 8. Phase 5 — Offline that tells the truth

1. **Secure-context notice.** When `!window.isSecureContext`, show a persistent
   but dismissible strip on public and app pages: "Offline needs HTTPS or
   localhost. This page was opened over a local address (<host>). Install the
   app from the Codespace link, or use the hosted URL." Do not silently skip
   registration (`app.js:1013`).
2. **Precache an offline page** and the last visited authenticated page per
   role. Keep network-first for record pages.
3. **Report shell failures.** Collect failed `cache.add` URLs in install, store
   the list, and show it in Settings → Device ("2 files could not be cached:
   …"). No more `catch { /* keep going */ }` with no record.
4. **Queue presence writes.** Arrival and confirmation join drafts and
   logbooks in the outbox, with `captured_at`, coordinates, accuracy and a
   client UUID. On sync the server keeps `captured_at`, sets `synced_at`, and
   flags **Late sync** beyond 15 minutes.
5. **Status chip.** Three states: online, offline, unreachable-but-slow. A
   HEAD timeout is not "offline". On `*.app.github.dev`, "offline" reads
   "demo server may be asleep".
6. **Device card** (Settings): secure context yes/no, service worker active,
   install state, cache count, pending count, last sync, and the failed-file
   list. Include the iOS note: Add to Home Screen; open at least monthly.
7. **Logout purge** stays: caches, outbox and drafts.

Acceptance: AT-06 to AT-08, AT-14, AT-15. A phone on `http://192.168.x.x`
must see the notice, not a lie. An offline arrival syncs with its original
time.

---

## 9. Phase 6 — Fresh demo data

Rewrite `database/digital_leap.sql` so it tells the story in the brief §9.
Keep the same account emails and passwords. Remove every test artefact
("test", "asdf", "qwerty", "demo1", "1", "aaa").

Required counts:

- 9 organisations (keep), 12 users (keep), 5 students, 5 placements:
  2 active, 2 proposed, 1 completed.
- Attendance: 20 working days for placement 1 (17 on time, 2 late, 1 absent)
  and 18 for placement 2; 2 refused attempts at 1.8 km kept as audit rows.
- Supervisor confirmations: weeks 1–11 for placement 1, 1–8 for placement 2.
- Visits: placement 1 — 2 completed (16 m, 38 m) and 1 scheduled;
  placement 2 — 1 completed, 1 missed.
- Logbooks: 12 weeks each, with 10 approved / 1 submitted / 1 returned for
  placement 1 and 11 approved / 1 submitted for placement 2.
- 2 open exceptions, 1 conversation per active placement (6 messages each),
  8 unread student notifications, 3 feedback entries.
- Marks: placement 1 published (68, B+), placement 2 computed and unpublished;
  placement 4 published from last year.

Add `database/demo_check.sql` asserting those counts, and run it from setup so
a partial seed fails loudly. Do **not** delete or truncate the owner's local
database: import into a new database name and point config at it, or let a new
Codespace volume take the new seed.

Acceptance: `demo_check.sql` passes; no test strings remain; every seeded
screen has believable content; AT-17 passes.

---

## 10. Phase 7 — DIVA

1. Rewrite DIVA's self-knowledge: the four verbs, who may give each signature,
   500 m for staff, attested fallback for students, the queue and its actions,
   offline limits, install requirements, the demo-server caveat.
2. Add page context for *Presence*, *Needs review*, *Device* and the splash.
3. Add 8 golden questions with expected answers and citations; record the run
   in `docs/diva-eval-results.txt`. Keep the refusal rule: no source, no
   answer, point to the guidebook page.
4. DIVA must never claim to know a student's location or to track anyone
   continuously.

Acceptance: AT-18 passes; DIVA refuses a question about live location.

---

## 11. Phase 8 — Documentation and evidence

- `docs/test-log.md`: new dated section, tests numbered from TC-250 (TC-246 to
  TC-249 are the 25 November launcher, backdrop and hero work), one line of
  evidence per test (command, status code, screenshot name).
- `docs/DEMO.md`: update the run sheet to the four verbs; add the splash and
  the Presence panel; keep it to 8 minutes.
- `docs/Digital_LEAP_User_Guide.md`: keep prose under 2,000 words; update the
  tracking paragraph and the Visits paragraph to the new vocabulary.
- `README.md`, `INSTALL.txt`, `docs/VERIFY.md`, `docs/SUBMISSION-CHECKLIST.md`:
  route counts, test count, offline notes, codespace recipe, demo-server
  caveat.
- `docs/design-system.md`: the globe, the splash, the four verbs, the device
  matrix.
- Screenshots: landing, splash, Presence (student, coordinator), exceptions,
  Device card, tablet and 360 px views. Store in `docs/screenshots/`.
- Do not touch the report DOCX/PDF in this phase; their counts are stale and
  updating them is a separate instruction.

---

## 12. Stop conditions

Stop and ask only for:

1. a destructive database operation;
2. enabling anything that reaches the internet;
3. touching secrets or `config/config.php`;
4. an irreversible action.

Everything else: decide, record the decision in `docs/test-log.md`, continue.

Do not commit or push unless asked in the current message.

---

## 13. Phase 9 — Polish that builds on 25 November

Small, verifiable improvements to the work in §0.1. Each one must leave the
demo accounts, the route counts and the offline rules untouched.

### 13.1 Make the launcher an entrance, not a signpost

- `diva` accepts an optional `q` parameter that pre-fills the composer (escaped,
  never evaluated, length-capped at 500 characters). The launcher on a
  placement page passes one question in the student's words — for example
  "What is my next step on this placement?" — and the DIVA page lands with that
  text in the box, not sent.
- `Alt+Shift+D` opens DIVA from any signed-in page, declared with
  `aria-keyshortcuts="Alt+Shift+D"` on the launcher and listed in the guided
  tour. Do not capture the key while focus is in a text field.
- The launcher must sit below every modal (`.feedback-layer`, install sheet,
  tour) and disappear from print. Already true — prove it again with rectangles.

Acceptance: click from `student/placement`, `coordinator/placement` and
`mentor/visits`; each opens DIVA with the right prompt; the question is not
sent until the user presses send; the link is absent on `diva` itself.

### 13.2 Let the sign-in backdrop exist on a phone

At ≤ 560 px `.auth-brand` is hidden, so the photograph is reduced to a 14 px
frame around the card. Add a slim `figure.auth-strip` above the card (3 : 1,
150 px tall, `campus-graduation-960.jpg`, caption **Limkokwing graduation**).
Keep the card's contrast; do not move the form below the fold at 390 × 844.

Acceptance: at 390 px the strip is visible, the card top is still inside the
first screen, and `scrollWidth === clientWidth`.

### 13.3 Serve the images at the size they are shown

- Shipped: `campus-flags.jpg` (1320 × 741) with `campus-flags-960.jpg` below
  768 px through a media `<source>`, and `campus-graduation.jpg` with its 960 px
  candidate for the sign-in strip. The phone candidates are 109 KB and 92 KB,
  under the 120 KB target; the globe ships as 22 KB of WebP with a PNG
  fallback.
- Keep the full-size photographs out of a phone's install. Both stay in the
  runtime cache and neither is precached; the globe sphere is precached because
  the splash needs it at first paint. If `SHELL` changes, bump `VERSION`.
- Keep intrinsic `width`/`height` on every image so CLS stays 0.

Acceptance: DevTools network shows the small variant on a phone, the large one
on a 1440 desktop; the first paint keeps the headline where it was.

### 13.4 Hold the quality line

- White text over the scrim ≥ 4.5 : 1 at the darkest and lightest points, at
  200 % zoom; the caption chip ≥ 4.5 : 1 over both a light and a dark part of
  the photograph.
- No horizontal scroll at 320, 360, 390, 768, 1024 and 1440 px on the landing,
  sign-in, dashboard and DIVA.
- `prefers-reduced-motion`: the launcher has no transform transition and the
  hover lift is removed.

### 13.5 Say where the pictures came from

- Keep `docs/assets.md`: one line per image — file, pixel size, byte size, how
  it was produced, and the caption rule. State plainly that the landing crowd,
  the sign-in graduation and the globe are the owner's own files, enhanced
  locally and never swapped for generated stand-ins. Link it from `README.md`
  next to the brand assets, and keep the provenance line in `docs/DEMO.md` so a
  presenter can answer the question accurately on stage.

### 13.6 Keep feedback findable now that it has no button

- Add one step to the guided tour: "Send feedback" lives in the account menu
  (and **Share feedback** in the sidebar) and reaches the office.
- Check the empty and submitted states of the feedback form from the menu and
  from the sidebar after the launcher change; `app.js` binds every
  `[data-feedback-open]`, so a regression here means a selector changed.

Acceptance: a new account can find feedback without being told; the tour step
resolves to a visible control; both entry points open the same dialog.

### 13.7 One more assistant surface, only if it earns its place

There is room for a single contextual chip inside the placement and logbook
screens — "Ask DIVA about this week" — using the existing `diva-fab` styling at
inline size. Ship it only if the question it asks is specific to the record on
screen and the answer is verifiable from live data or a guidebook page. If it
would only open a generic chat, do not build it.

---

## 14. Definition of done

- The Codespace link opens the landing page in a private window.
- One sentence explains presence: *students prove arrival, supervisors confirm
  presence, mentors verify visits, coordinators review exceptions.*
- The coordinator's Presence screen resolves every exception in one place.
- Landing fits one screen at 1440 × 900; 320 px has no horizontal scroll;
  Chrome 90 renders the full design.
- Splash shows the globe once per session, respects reduced motion, never
  blocks a ready page.
- A phone over plain HTTP explains why offline is unavailable; over HTTPS,
  drafts and arrivals survive a reload and sync with their captured time.
- `demo_check.sql` passes; no test data; all six demo accounts work.
- DIVA answers the 8 golden questions and refuses live-location claims.
- The corner button opens DIVA on every signed-in page except DIVA, and feedback
  is reachable in two places from every signed-in page (§13.6).
- The sign-in backdrop is sharp at 1440 and the campus still shows on a 390 px
  phone (§13.2); every photograph is captioned or declared in `docs/assets.md`
  (§13.5).
- Lint clean; route counts stated correctly everywhere; docs and test log in
  step with the code.
