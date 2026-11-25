# Digital LEAP — redesign brief

25 November 2026 · working document for the next build · supersedes the
"tracking is too complex" discussion

This brief answers one question first — *who actually checks that a student
reached the site?* — and then sets out the research, brand, landing, loading,
offline, device, Codespaces and demo-data work that follows from the answer.
The copy-ready instruction for the next session is in
`docs/MASTER-PROMPT-NEXT-BUILD.md`.

---

## 1. Who verifies presence

### 1.1 What the system does today

Today there are three separate mechanisms, each correct on its own, but they
never appear together:

| Mechanism | Who records it | Where it shows | Strength |
| --- | --- | --- | --- |
| Attendance check-in | Student, at the placement pin | Student · Attendance | Attested fallback when GPS is unavailable |
| Staff site visit | Mentor or coordinator, GPS at the site | Visits · Staff site visits | Strict: refused beyond 500 m |
| Completion gate | Mentor, on the visit row | Visits | A physical visit cannot be completed without a check-in |

The feature works (TC-238–242), but it reads as *three* systems. A coordinator
opening the app sees "Attendance", "Visits", "Staff site visits" and cannot
answer in one glance: *did anyone independent confirm this student was there?*
That is the complexity the demo exposes.

### 1.2 The model to build

One principle: **one event, one verifier; nobody verifies themselves.**

```
   ARRIVAL            PRESENCE              MONITORING            REVIEW
   student            host supervisor       academic mentor       coordinator
   ────────           ───────────────       ───────────────       ─────────
   "I am here"        "they were here"      "I visited and saw"   "I checked the
   GPS or attest      weekly confirmation   strict GPS visit      exceptions"
        │                   │                     │                    │
        └───────────────────┴─────────────────────┴────────────────────┘
                     one presence record per placement
```

| Evidence | Given by | Method | Never given by | Reads as |
| --- | --- | --- | --- | --- |
| **Arrival** | Student | One reading at the button press, 500 m of the placement pin; attested note when GPS is unavailable | Anyone else | "Arrived 08:42 · 42 m · GPS" |
| **Presence** | Host supervisor | Weekly confirmation of days attended, from the workplace | Student, mentor | "Confirmed by supervisor · week 12" |
| **Monitoring visit** | Academic mentor or coordinator | Scheduled visit, strict GPS check-in, completion blocked without it | Student | "Site verified · 16 m · 10:14" |
| **Review** | Coordinator | Exceptions queue, spot checks, pin and radius ownership | The parties above | "Reviewed 25 Nov · 2 exceptions closed" |

Why this is defensible:

- It matches how field-work systems actually work. Geofenced attendance is
  normally a *claim* by the worker, an *attestation* by the site supervisor,
  and an *exception review* by the office. No system with a straight face lets
  the student be the only witness.
- It keeps the strict rule where it belongs. A mentor visit is a scheduled
  professional act, so strict GPS with a 500 m refusal makes sense. A student
  on a weak network needs a fallback, or the app punishes the wrong person.
- It puts the coordinator in the only role that scales: not watching every
  student, but clearing a queue of things that disagree.

### 1.3 What the coordinator actually reviews

The coordinator's screen should open on a short, finite list, not a map:

1. Arrival recorded outside the radius, or attested because GPS was refused.
2. Week ended with no supervisor confirmation.
3. Visit scheduled for a past date with no check-in.
4. Mentor completed a virtual/phone visit where the schedule said physical.
5. Two sources disagree (student says present, supervisor did not confirm).

Each row resolves in one of three ways: **accept** (with a reason), **query**
(sends a message to the student or mentor), **escalate** (to management). Every
resolution appends to `audit_log`; nothing is edited or deleted.

### 1.4 What to simplify in the interface

- One **Presence** panel per placement: a single vertical timeline of arrivals,
  confirmations, visits and reviews, newest first, each with its verifier.
- Merge "Staff site visits" and "Coordinator site check-ins" into one visit
  list with a *Visitor* column (mentor · coordinator), keeping the existing
  `sitevisit.checkin` records as the source.
- Keep the 500 m rule for staff; show distance and time on every verified row.
- Wording: *Arrived*, *Confirmed*, *Visited*, *Reviewed* — four verbs, one per
  role. Retire "check in" from student-facing copy where it means "I am here".
- Never imply continuous tracking. The browser reads location only when a
  button is pressed; say so on the page.

---

## 2. Research notes

### 2.1 Limkokwing, globally and in Sierra Leone

- The institutional identity is built on the **globe**: the supplied Sierra
  Leone mark is a sphere of national flags with the Limkokwing crest at its
  centre. The global site leans on the same idea — a world map or globe in the
  hero, large display type, and the "global university" positioning.
- The Sierra Leone microsite (`luct-sl.vercel.app`) is the most modern public
  expression of the brand: near-black canvas `#05070c`, Geist and Geist Mono,
  a large interactive 3-D globe, big numbered section markers, a role-portal
  grid, and short copy with generous space. It reads as a product site, not a
  prospectus.
- Brand-reference listings give Limkokwing blue `#4167B2` alongside white and
  black. The copy of the LUCT lockup already in the repo
  (`public/assets/img/limkokwing-logo.png`, 130 × 75) is a light lockup: a pale
  blue field `#E6EFF8` with near-black lettering. It belongs on a light footer,
  **not** on a charcoal header.

Adopt: the globe as the single recurring motif, near-black surfaces, numbered
sections, role portals, restrained motion, short sentences.
Reject: WebGL libraries, external fonts, autoplay media, and anything that
needs a network — the system must run offline on the exam laptop and in
Codespaces with no third-party requests (CSP `script-src 'self'`).

The app's own identity is already correct and should not be replaced by the
institutional palette: charcoal `#111315`, ivory `#f6f3ec`, gold `#b8912f` /
`#d8b75c`, line `#e7e1d5`; Segoe UI for interface text, Georgia for display.
The Limkokwing globe is the *seal* on that identity, not a repaint.

### 2.2 What the research says about offline and location

- Service workers, the Cache API and geolocation all require a **secure
  context**: HTTPS or `localhost`. A phone opening `http://192.168.x.x/…` gets
  no service worker at all.
- iOS evicts website storage after about seven days of non-use, and an
  installed home-screen app has storage separate from Safari. "Add to Home
  Screen" is required before offline is dependable there.
- Geofence products handle the hard cases by *recording and reviewing*, not by
  blocking the worker: outside-fence punches are accepted and flagged, or
  refused with a stated reason, and a supervisor clears them. Accuracy is
  treated as a number to display, not a truth to assume.
- Codespaces is a development container, not hosting: a stopped container
  serves nothing, and a forwarded port is private until it is made public.

---

## 3. Brand assets on disk

| File | Size | Purpose |
| --- | --- | --- |
| `docs/brand-source/leap-badge.jpeg` | 1362 × 1155 | Original supplied badge: black plate, white "Leap" and arc, caption, "Sierra Leone" band |
| `docs/brand-source/limkokwing-globe.png` | 234 × 295 | Original supplied flag-globe (low resolution, white background) |
| `docs/brand-preview/leap-mark.png` (and `-white`) | 1158 × 625 | Badge plate without the caption — social card, splash plate, about panel |
| `docs/brand-preview/leap-mark-ink.png` / `-ivory.png` / `-gold.png` | 1362 × 1155 | Whole badge recoloured; the plate is kept, so use on a matching surface |
| `docs/brand-preview/leap-badge.png` | 1330 × 1060 | Full badge, trimmed |
| `docs/brand-preview/leap-wordmark-ink.png` | 1013 × 566 | **Transparent** "Leap" + arc, charcoal; the header and auth mark |
| `docs/brand-preview/leap-wordmark-ivory.png` | 1013 × 566 | Transparent, ivory; for charcoal or photographic surfaces |
| `docs/brand-preview/leap-wordmark-gold.png` | 1013 × 566 | Transparent, gold; accent use only |
| `docs/brand-preview/leap-globe.png` | 456 × 456 | Circular crop of the flag globe, white removed |
| `docs/brand-preview/icon-192.png`, `icon-512.png` | 192 / 512 | App icon: charcoal rounded square, white Leap glyph, gold ring |

Installed copies live in `digital-leap/public/assets/img/`. The header, auth
tile and landing header now use `leap-wordmark-ink.png`; the footer keeps the
LUCT lockup. `sw.js` precaches the wordmarks under `VERSION = 'v6'`.

Asset rules:

- The wordmark is never placed on a busy photograph; use the ivory or gold
  variant on dark surfaces.
- Do not stretch it. Its ratio is 1.79 : 1; height 28–40 px in headers.
- The globe image is a raster crop of 234 px. It may be shown up to about
  300 px wide. For larger sizes, build the CSS globe described in §5 rather
  than upscaling it.
- App icon: one glyph, one ring, no text. It must stay legible at 48 px.

---

## 4. Landing page

### 4.1 Why it does not capture

- The hero headline is 9 words but the paragraph under it is 45, so the first
  screen is mostly prose.
- There is no single, obvious action; "Explore the demo" and "Create account"
  compete.
- The page shows a mock dashboard before it shows what the system is *for*.
- Six role cards with equal weight read as a feature list, not a journey.
- On a 550 px window the status chip sat over the header actions (fixed in this
  session; see §7).

### 4.2 Structure to build

```
┌───────────────────────────────┬───────────────────────────┐
│ LIMKOKWING · SIERRA LEONE     │                           │
│ Every placement.              │        ( globe )          │
│ Every signature.  ← 2 lines   │   six role labels orbit   │
│ One record.                   │                           │
│                               │                           │
│ [ Sign in ]  [ See how it works ]                         │
│ ✓ Offline  ✓ No external API  ✓ Audit trail               │
└───────────────────────────────┴───────────────────────────┘
   ↓ scroll cue

How a placement moves   ① apply ② allocate ③ arrive ④ review ⑤ publish
Six roles, one record   student · supervisor · mentor · coordinator ·
                        management · administration  (portal tiles)
Proof, not paperwork    GPS arrival · supervisor confirmation · published mark
                        (three small evidence cards, each with a real sample row)
[ one dark CTA band: Open the demo → ]
footer: LUCT lockup, guidebook link, licence line
```

Copy budget: hero ≤ 18 words; each section ≤ 25 words besides its heading; no
paragraph longer than two lines at 1280 px.

The globe in the hero is the CSS globe from §5, held still except for a slow
meridian sweep, with role names on a ring. It is decorative: `aria-hidden`.

### 4.3 What the hero must do in five seconds

1. Say the domain: internships at Limkokwing Sierra Leone.
2. Say the promise: every placement, from application to published mark.
3. Offer one primary action.
4. Prove it runs without the internet.

---

## 5. Loading screen

The supplied image is a photograph of a sphere, so rotating the PNG looks like
a spinning coin, not a turning world. Build the globe in CSS and use the image
as the still fallback:

- A circle with radial shading, a `repeating-linear-gradient` of flag-coloured
  bands masked by the circle, and a slow `background-position` animation — the
  bands travel, the sphere stays — which reads as rotation.
- Two thin elliptical "meridian" rings, one gold, rotating at different speeds.
- The crest panel sits at the centre; it does not spin.
- 900 ms minimum, 2.5 s maximum; the overlay fades when `DOMContentLoaded` has
  fired *and* the minimum has elapsed. It never blocks a page that is ready.
- A rotating fact line under the globe, one per 1.6 s: "12 weeks · Forms A–I",
  "200 marks · seven components", "GPS proof · 500 m rule", "Works offline".
- Shown once per browser session; skipped when `prefers-reduced-motion` is set
  (static globe, no fact rotation, 300 ms fade).
- Critical: the splash lives in the HTML, not behind a fetch, so it appears even
  when the network is gone.

---

## 6. Offline: why the phone says "offline"

| # | Cause | Evidence in the code | Fix |
| --- | --- | --- | --- |
| 1 | No secure context over a LAN address | `app.js:1013` returns silently when `!window.isSecureContext`; no registration, no cache | Detect it and say so: "Offline needs HTTPS or localhost. This page was opened over a local IP." Offer the installed-app route |
| 2 | Nothing authenticated is precached | Shell list holds CSS/JS/icons only; the dashboard is network-first | Precache an offline fallback page and the last visited page per role; keep network-first for freshness |
| 3 | Shell install failures are swallowed | `sw.js:47-49` — `cache.add` inside `try { } catch { /* keep going */ }` | Count failures, log the URLs, expose the count in Settings → Device |
| 4 | iOS storage eviction and separate app storage | Platform behaviour | A short "Keep it working offline" card: Add to Home Screen, open monthly |
| 5 | The status pill probes with a HEAD request and treats any failure as offline | `initNetStatus` | Add a timeout and a "reachable but slow" state; do not call a slow server offline |
| 6 | A stopped Codespace answers nothing | Container not running | Say "demo server asleep" rather than "you are offline" when the URL is `*.app.github.dev` |

Offline writes are drafts and logbook entries only. Arrival check-ins and staff
visits are *not* queued today. For the new build, queue them too, with:
a UUID, the captured coordinates and accuracy, `captured_at`, the device clock
skew, and a badge "Waiting to send". On sync the server keeps the captured
time, records `synced_at`, and marks the event **Late sync** if the gap exceeds
15 minutes. Never rewrite the captured time to the sync time.

The app already has the right storage (`offline.js`, IndexedDB with a
localStorage fallback) and a diagnostics block behind Settings. Surface the
diagnostics: install state, cache count, pending count, last sync, secure
context yes/no.

---

## 7. Device support: why some phones break

| Symptom | Cause | Fix |
| --- | --- | --- |
| Unstyled or half-styled page, elements stacked | `@layer` is unsupported on Chrome < 99, Edge Legacy, Samsung Internet < 15, Windows Phone; the whole `app.css` is layered, so unsupported browsers drop nearly every declaration | Ship a flattened build: keep the layered source, generate one unlayered file for the app. Verify in Chrome 90 emulation |
| Content under the notch or status bar; page taller than the screen | Missing `viewport-fit=cover`, `100vh` on mobile | Added `viewport-fit=cover`, `env(safe-area-inset-*)` padding, and `100dvh` fallbacks (this session) |
| Buttons wrapping, controls overlapping | Fixed-position status chip over the header; no `white-space` on actions | Moved the chip to the bottom-right; actions no longer wrap (this session) |
| Horizontal scroll on 320–360 px | Tables and long tokens | `table--stack` cards, `overflow-wrap: anywhere` on identifiers, test at 320 px |
| Hover-only menus | No touch path | Tap toggle with `aria-expanded`; 44 px targets |
| Tiny text | Fixed `rem` sizes | Fluid `clamp()` steps, minimum 16 px body at 320 px |

Support matrix to test in every release:

| Width | Reference device |
| --- | --- |
| 320 × 568 | iPhone SE (1st gen), small Android |
| 360 × 640 | Common Android |
| 375 × 812 | iPhone X/11/12 mini |
| 390 × 844 | iPhone 12/13/14 |
| 412 × 915 | Pixel |
| 768 × 1024 | iPad portrait |
| 820 × 1180 | iPad Air |
| 1024 × 768 | iPad landscape, small laptop |
| 1280 / 1440 | Laptop and demo projector |

Windows Phone is end-of-life; the target is *readable and usable*, not
pixel-identical. That means the unlayered stylesheet above all.

---

## 8. Codespaces: one click to the app

### 8.1 What went wrong before

- The devcontainer build broke because Compose does not export
  `localWorkspaceFolder`; every `${localWorkspaceFolder}` path collapsed and
  the Dockerfile was looked up in `.devcontainer/deploy/`. It is fixed with
  paths relative to the compose file.
- The first image was built from `php:8.0`, which is past end of life. The
  current Dockerfile uses `php:8.3-apache-bookworm`.
- Port 8080 is **private by default on every new codespace**. An anonymous
  request is redirected to `github.dev/pf-signin`; that is a visibility
  problem, not an application error.
- An empty `404` with `X-Served-By: tunnels-prod…` means no container is
  attached: stopped, rebuilding, or failed.
- A running codespace does not pull after a push. The demo can answer `200`
  and still be the old build.

### 8.2 The recipe for the new codespace

1. Create it from `codespaces.new/EssenceShady/Digital_LEAP_Assignment3_Group_G`.
2. Wait for the creation log to show Apache started and the seed finished.
3. Open the Ports tab, right-click 8080, set **Public**. The `portsAttributes`
   block asks for public, but confirm it — a new codespace gets a new port row.
4. Share only `https://<name>-8080.app.github.dev/` — never the repo page or
   `codespaces.new`.
5. Check it anonymously before sending: a browser in private mode, no GitHub
   session. A `200` with the landing page is the only passing result.
6. Stop it manually after a demo. The 200-minute idle timeout applies to
   codespaces created after the setting was saved, and it is not hosting.

### 8.3 What "one click" can honestly mean

The link opens the app **while the codespace is running and the port is
public**. It cannot be a permanent URL: a stopped container serves nothing and
the hostname changes when the codespace is recreated. If the marker needs a
link that works unattended, the system needs a PHP host of its own; the
Dockerfile and the vhost are already written for that.

To reduce the failure modes:

- `postCreateCommand` runs setup and then a smoke check that curls the landing
  page through Apache, so a broken build fails visibly in the log.
- The landing page shows a small "demo server" note when the host ends in
  `app.github.dev`, explaining that a stopped container is not an outage of
  the app.
- The 404 page is replaced with a plain "The demo server is not running"
  document so a stopped container never looks like a crash.

---

## 9. Fresh demo data

The local database has drifted from testing: 17 users, 10 students, 7
placements, test names and half-finished records. The seed file still holds the
clean baseline (12 users, 5 students, 5 placements, 4 visits, 12 logbook
entries). The new build should seed a *story*, not just rows.

Narrative (all synthetic, marked as demo in the footer):

- Placement 1 — Mohamed Alie Kamara at Bright Future Mining, week 12 of 12.
  Attendance 20 days: 17 on time, 2 late, 1 absent. Two refused attempts at
  1.8 km, kept in the audit log. Supervisor confirmed weeks 1–11; week 12
  awaits confirmation. Three visits: two completed (16 m and 38 m), one
  scheduled next week. Twelve logbook weeks: 10 approved, 1 submitted, 1
  returned with a comment. Supervisor Form D complete; mentor evaluation
  complete. Mark computed, not yet published.
- Placement 2 — Aisha Koroma at Sierra Telecoms, week 9. Attendance 18 days.
  Supervisor confirmed through week 8. Two visits: one completed, one missed.
  Eleven logbooks approved, one submitted. Mark not computed.
- Placement 3 — Ibrahim Sesay, Rokel Commercial Bank: proposed, no start date.
- Placement 4 — Maria Kamara, Leone District Health Office: completed last
  year, published mark, so the archive views have content.
- Placement 5 — Mariama Turay, Freetown City Council: proposed.
- Two open exceptions for the coordinator queue: an attested arrival with a
  GPS refusal, and a missed visit.
- One conversation per active placement, six messages each; eight unread
  notifications for the student; three feedback entries.

How to refresh without destructive local work: import the new seed into a new
database name (for example `digital_leap_v3`) and point `config/config.php` at
it; the old database stays untouched for comparison. In Codespaces the volume
is recreated with the codespace, so the new seed is what a fresh volume loads.

Add a `database/demo_check.sql` that asserts the counts and fails loudly:
5 students, 2 active placements, 20 + 18 attendance days, 5 visits, 2 open
exceptions, 24 logbook weeks. Run it in setup so a partial seed cannot pass as
a good demo.

---

## 10. DIVA

DIVA's knowledge must describe the system as it now is, or it will confidently
explain the old one.

- Rewrite the self-knowledge block: the four verbs (arrived, confirmed,
  visited, reviewed), who can give each signature, the 500 m rule for staff,
  the attested fallback for students, offline limits ("drafts and logbooks
  queue; arrivals now queue too"), install requirements.
- Add 8 golden questions with expected answers and citations, for example:
  *Who confirms I was at work?* · *What happens if I am 600 m from the site?* ·
  *Can my mentor complete a visit without checking in?* · *Why does offline not
  work on my phone?* · *What does "Late sync" mean?* · *Who sees my location?* ·
  *How do I install the app?* · *Why does the demo link say the server is
  asleep?*
- Keep the refusal rule. If the answer is not in the guidebook or the records,
  say so and point to the page.
- Add page-level context for the new Presence panel and the coordinator
  exceptions queue.

---

## 11. Feature shortlist

Ordered by value to the demo and the marker:

1. **Presence panel** per placement — the timeline from §1.4.
2. **Exceptions queue** for the coordinator — the five cases in §1.3.
3. **Offline queue with visible state** — pending badge, last sync, late sync.
4. **Device card** — secure context, install state, cache count, pending count.
5. **Command palette** (Ctrl/⌘ K) — jump to any page the role may open; labels
   only, no new permissions.
6. **Print summary sheet** per student — placement, presence, marks, signatures.
7. **Landing + splash** from §4 and §5.
8. **Demo seed** from §9.

Not in scope: maps, routing, push notifications, background location, external
services, new frameworks.

---

## 12. Acceptance tests

| ID | Test | Pass condition |
| --- | --- | --- |
| AT-01 | Open the new codespace URL with no GitHub session | Landing page over HTTPS, port public |
| AT-02 | Stop the codespace, reload | Plain "demo server is not running" page, not a browser 404 |
| AT-03 | Desktop 1440, 1280; laptop 1024 | No horizontal scroll; hero fits one screen |
| AT-04 | 360, 375, 390, 412, 320 emulation | No overlap; 44 px targets; header actions on one line |
| AT-05 | Chrome 90 emulation (no `@layer`) | Full styling, not a stacked document |
| AT-06 | iOS Safari, Add to Home Screen, then offline | Last page opens; drafts save; pending badge shows |
| AT-07 | Android Chrome, airplane mode after one visit | Same |
| AT-08 | Open over `http://<LAN IP>` | Visible, specific secure-context notice; no silent failure |
| AT-09 | Student arrival at 16 m | Accepted, reads "42 m · GPS" with time |
| AT-10 | Student arrival at 1.8 km | Refused with distance; attested note offered and flagged |
| AT-11 | Mentor visit completion without check-in | Blocked, message names the reason |
| AT-12 | Coordinator visits screen | Mentor and coordinator visits in one list, visitor column |
| AT-13 | Exceptions queue | Shows the two seeded exceptions; accept/query/escalate all audit |
| AT-14 | Offline arrival, then sync | Captured time kept; `synced_at` set; late sync shown if > 15 min |
| AT-15 | Shell install with one bad path | Settings shows the failed URL, not silence |
| AT-16 | `prefers-reduced-motion` | Static globe, no fact rotation |
| AT-17 | Seed check | `demo_check.sql` passes; no test names |
| AT-18 | DIVA golden set | 8/8 new questions answered or refused correctly |
| AT-19 | Route count and permissions | Unchanged unless deliberately added; lint clean |
| AT-20 | Logout | Caches and outbox purged; back button does not reveal a page |

---

## 13. Risks

- **Scope.** The tracking redesign touches schema, services, views and copy.
  Ship the Presence panel and the exceptions queue before the decorative work.
- **Offline expectations.** No website can promise offline on iOS forever;
  say "works offline after one visit, keep it installed" rather than "works
  offline".
- **Codespace limits.** The demo link dies with the container. State it in the
  README and on the landing page footnote.
- **Brand drift.** The institutional palette must not overwrite the app's
  charcoal/ivory/gold identity; the globe is a seal.
- **The guidebook** is a fixed artefact. Never edit
  `Internship_Guidebook_LEAP_Clean_Layout.pdf`; quote it, do not change it.

---

## 14. Sources

- Limkokwing Sierra Leone microsite (layout, palette, globe, role portals) —
  <https://luct-sl.vercel.app/>
- Limkokwing University main site — <https://www.limkokwing.com/>
- MDN, secure contexts (why a LAN IP gets no service worker) —
  <https://developer.mozilla.org/en-US/docs/Web/Security/Secure_Contexts>
- MDN, service worker API —
  <https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API>
- web.dev, Learn PWA —
  <https://web.dev/learn/pwa/>
- GitHub Docs, forwarding ports in a codespace (public/private port) —
  <https://docs.github.com/en/codespaces/developing-in-a-codespace/forwarding-ports-in-your-codespace>
- GitHub Docs, codespace lifecycle (stop, idle timeout, deletion) —
  <https://docs.github.com/en/codespaces/about-codespaces/understanding-the-codespace-lifecycle>

Platform behaviour that is not linkable to one page — iOS storage eviction,
geofence review practice, Windows Phone browser support — is stated as
observed behaviour, not quoted policy.

