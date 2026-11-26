# Screenshots for the user guide and report

The captures live in `pwa/`. They were taken from the running application at
`http://localhost/Digital_LEAP_Assignment3_Group_G/digital-leap/` on the seeded
demo accounts, with browser chrome cropped out.

## Naming

`<role>-<page>-<width>.png`, where `<role>` is the account used
(student, supervisor, mentor, coordinator, management, admin), `<page>` is the
route shown, and `<width>` is the viewport width in pixels: `1440` for desktop,
`360` for the narrow layout. Six further files cover offline and sync states and
use their own widths (`420` for the offline page, `460` for the sync strip).

## Current set

128 files: 61 pages captured at both widths (122 files) plus six state shots.

- `student-*` (15) — dashboard, apply, career fair, preferences, placement,
  logbook list, new logbook, self-evaluation, report, results, profile,
  messages, notifications, DIVA, DIVA report check
- `supervisor-*` (7) — dashboard, queue, evaluation, profile, messages,
  notifications, DIVA
- `mentor-*` (11) — dashboard, students, visits, visit form, evaluation,
  logbook, reports, profile, messages, notifications, DIVA
- `coordinator-*` (11) — dashboard, applications, placements, visits,
  organisations, grades, printable grades, profile, messages, notifications,
  DIVA
- `management-*` (6) — dashboard, KPIs, printable KPIs, profile, notifications,
  DIVA
- `admin-*` (11) — dashboard, users, user form, roles, audit, feedback, mail,
  settings, profile, notifications, DIVA
- `offline-fallback-420.png`, `offline-cached-page-420.png`,
  `offline-cached-real-420.png`, `offline-fallback-real-420.png` — the offline
  page served by the service worker while the network is down
- `sync-strip-queued-460.png`, `sync-strip-drained-460.png` — the sync strip with
  drafts waiting and after they have drained

`diva-welcome.png` sits one level up and is the welcome-state figure linked from
`docs/Digital_LEAP_User_Guide.md`.

## Used in the report

Figures 5 to 16 of `REPORT/Digital_LEAP_Assignment3_Report.docx` use these files:

| Figure | File |
| --- | --- |
| 5 | `pwa/student-dashboard-1440.png` |
| 6 | `pwa/student-student-apply-1440.png` |
| 7 | `pwa/student-student-logbook-new-1440.png` |
| 8 | `pwa/coordinator-coordinator-placements-1440.png` |
| 9 | `pwa/supervisor-supervisor-queue-1440.png` |
| 10 | `pwa/mentor-mentor-evaluation-1440.png` |
| 11 | `pwa/coordinator-coordinator-grades-1440.png` |
| 12 | `pwa/admin-admin-audit-1440.png` |
| 13 | `pwa/student-diva-1440.png` |
| 14 | `pwa/offline-fallback-420.png` |
| 15 | `pwa/sync-strip-queued-460.png` |
| 16 | `pwa/student-dashboard-360.png` |

## Known gaps

Two pages added late are not yet in the set: the student **My settings** page and
the **Guidebook** view/download page. Capture them at 1440 and 360 with a
signed-in student, save as `student-student-settings-<width>.png` and
`student-guidebook-<width>.png`, and the set is complete.

Capture from the running application only. Do not screenshot the code editor,
the XAMPP control panel or a database client, and do not include any password
in a screenshot.

## Phase 9 captures (25 November 2026)

Six extra files in `phase9/` cover the Phase 9 polish pass: the landing hero
at 1440 and at 390 (both showing the `srcset` delivery), the phone sign-in with
the illustrative strip, the DIVA launcher on **My placement**, the prefilled
DIVA composer, and the guided tour at its **Feedback has a home** step. Naming
is `phase9-<what>-<width>.png`; the files drop the prefix inside the folder
itself. `phase9/signin-390.png` predates the owner's request to restore the
supplied photograph, so use `phase10/signin-390.png` for anything current.

## Phase 10 captures (25 November 2026)

Eight files in `phase10/` record the owner-requested corrections:

| File | Shows |
| --- | --- |
| `signin-1440.png` | The sign-in backdrop as the owner's own graduation photograph, enlarged 2× and sharpened |
| `signin-390.png` | The same photograph as the 150 px strip above the card, captioned **Limkokwing graduation** |
| `diva-scroll-1440.png` | A six-turn DIVA conversation inside the bounded chat card, composer pinned below it |
| `diva-scroll-390.png` | The phone layout: chat first, history below, stream open at the newest answer |
| `chat-390.png` | A placement thread on a 390 × 844 phone with 28 / 48 px system insets: dark status strip, header and thread head clear of the icons, page scrolled with the composer pinned over the card's lower edge |
| `chat-scrolled-390.png` | The same thread after a drag that began on a message — the page moved, which it could not do before |
| `diva-390.png` | DIVA at 390 px as one page scroller, composer at the safe-area edge, no inner box |
| `chat-1440.png` | The desktop thread unchanged: bounded card, transcript's own scrollbar, **Back to inbox** and the DIVA launcher still present |

The two DIVA captures were taken on a temporary six-turn conversation that was
deleted from the database afterwards, so the shipped demo data stays clean.


## Phase 11 captures (26 November 2026)

Nine files in `phase11/` record the boot splash and the landing page rebuilt
around the owner's own globe and campus photographs:

| File | Shows |
| --- | --- |
| `splash-1440.png` | The boot splash mid-turn on a cold tab: the globe coin showing its flag face, two orbit rings, the ivory wordmark, the two-line subtitle and the gold meter part-filled |
| `splash-390.png` | The same splash on a 390 × 844 phone at DPR 3, with the subtitle stacked on two lines and the meter fill visible |
| `splash-390-reduced.png` | The calm variant: same composition with every animation stopped and the meter resting at 64 % |
| `landing-1440.png` | The restructured landing: centred copy, then the flag-waving crowd filling the column with the globe seal, caption and proof bar below |
| `landing-390.png` | The phone landing at 390 px: the 960 px candidate in the stage, the caption chip bottom-left on two lines and no horizontal scroll |
| `stage-390.png` | The stage alone on a phone, showing the drift crop, the seal at the upper left and the caption cleared of the Online dot at the lower left |
| `workspace-1440.png` | `section.home-workspace`: the three numbered notes beside the coordinator dashboard mock |
| `workspace-390.png` | The same section stacked to one column on a phone |
| `roles-1440.png` | The six role cards after their scroll-reveal stagger |

The splash captures were taken on a fresh tab with cleared session storage; a
second navigation shows no splash. All captures come from the running
application at `http://localhost/Digital_LEAP_Assignment3_Group_G/digital-leap/`.
