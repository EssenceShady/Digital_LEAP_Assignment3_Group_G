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
itself.
