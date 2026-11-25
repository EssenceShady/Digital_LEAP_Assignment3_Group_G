# Digital LEAP Internship Management System

A secure, offline-first, multi-role PHP 8 and MariaDB application built for
COMP217 Advanced Database Systems, Assignment 3, Group G, BSEM 2102,
Limkokwing University, Sierra Leone.

The system digitises the Digital LEAP internship process modelled in
Assignment 2: ranked preferences, placement allocation, weekly logbooks,
supervisor and mentor evaluation, internship reports, weighted marking,
moderation, publication and management reporting.

**Running demo:** [open a GitHub Codespace](https://codespaces.new/EssenceShady/Digital_LEAP_Assignment3_Group_G)
— the container builds PHP 8 + Apache and MariaDB, imports the demo seed and
publishes the app on a forwarded HTTPS link. See `deploy/README.md` §4 for the
port-visibility step and the idle timeout.

---

## 1. Quick start

Requirements: PHP 8.0+, MariaDB 10.4+, Apache with `mod_rewrite` (XAMPP is
fine), or the PHP built-in server.

1. Copy `digital-leap/` into `C:\xampp\htdocs\digital-leap`.
2. Start Apache and MySQL.
3. Import `database/digital_leap.sql` into a disposable clean installation only;
   it drops existing application tables and must not be used to upgrade live data.
4. Copy `config/config.sample.php` to `config/config.php`.
5. Open `http://localhost/digital-leap/`.

Built-in server alternative:

```
C:\xampp\php\php.exe -S 127.0.0.1:8080 router.php
```

Demo accounts and troubleshooting are in `INSTALL.txt`. The marker's
walkthrough is in `docs/VERIFY.md`; executed evidence is in
`docs/test-log.md`. Deployment on Apache, in Docker, in GitHub Codespaces, or
on a plain GitHub-hosted checkout is described in section 9 and
`deploy/README.md`.

The sign-in screen offers one-click demo credentials for all six roles. The
interface provides a single password field with a show-password eye button
(no second checkbox), a notification bell and inbox preview, a profile page
that previews a chosen photo in the header before saving (and can remove
it), card and row shortcuts, an off-canvas navigation drawer, a five-step
guided tour, a top loading bar with button spinners, an internship progress
tracker and a floating feedback form. Every signed-in role has a **My settings**
page for its own account: which notification families are copied to email,
normal or large text, reduced motion, and what this browser is holding offline —
installed app, saved DIVA answers, queued log book changes, cached pages — with
buttons to clear the device-side copies. Text size and motion preview the moment
they are picked, and every choice is stored per member and follows the account
to any other device. Registration and account creation use
the official Limkokwing faculty and programme list. Every role that handles
logbooks can open or download the 16-page internship log book template.
Students also have a Career Fair 2026 page that ships the official two-day
participation report template and records the uploaded report with its
deadline, late status and mentor review. Students check in and out at their
placement from **My placement**: the browser's location is read only at that
moment, the record shows the distance from the site pin the coordinator sets on
the organisation, and declining the prompt records an attested check-in instead.
Staff visits are GPS-verified too: a mentor checks in at the site before a
physical visit can be completed, a coordinator records a monitoring check-in on
the placement, both are refused beyond 500 m from the pin, and every record is
attributed by name, role, time and distance. No third-party map or location
service is involved. A finished log book day can also be sent to the host
supervisor and academic mentor straight away from the day's row, while the full
week is still emailed when the student submits. Every signed-in role has a
**Guidebook** page that opens the published 25-page guidebook PDF in the
browser and offers the same file as a download, with a page list that opens
the PDF at the chosen page (DIVA's citations keep a plain-text page that links
back to the PDF). Every signed-in role can ask DIVA,
an offline assistant that answers from the account's own records, quotes the
internship guidebook passage first with its page number, and can comment on a
report draft without giving a mark.
Students, mentors and coordinators also have placement messaging with quoted
replies, reactions, unread counts and an edit window.
Queues and record lists carry GET-based filters — status, week, student,
organisation, date range, rating, page or review state — with an **Apply
filters** button and a **Reset** link; a filter never changes a record and a
filtered view can be bookmarked. A short user guide for all six roles is in
`docs/Digital_LEAP_User_Guide.md`.

The application installs as a PWA from the browser's install control. Choosing
**Not now** hides that banner and nothing else: **How to install this app** stays
in the account menu and on the **My settings** page, and the browser's own
install icon in the address bar is unaffected. Desktop Firefox offers no install
button of its own for this kind of app, so the copy there names Chrome or Edge
instead of showing a control that does nothing. A service worker
precaches the shell, pages already opened stay readable without a connection,
log book edits are written to IndexedDB and drained through `?r=api/sync` with
an operation UUID per write, and DIVA keeps answering guidebook questions from a
32 KB index stored on the device.
Answers that need live records are refused honestly offline and queued;
per-user stores keep one account's saved answers away from the next.
The route sweep covers 62 routes at 360, 768, 1024 and 1440 px with no
horizontal page scroll (`docs/test-log.md`, TC-144), and the screens changed
in the DIVA revision were re-measured in the same sweep.

---

## 2. Directory layout

```
.gitignore                  Ignores config.php, runtime logs and tool output
.dockerignore               Build context for deploy/Dockerfile
digital-leap/
  index.php                 Front controller; requires app/bootstrap.php
  router.php                Router for the PHP built-in server
  sw.js                     Service worker: shell precache, runtime cache, purge
  .htaccess                 Apache rewrite and security headers
  app/
    bootstrap.php           Paths, autoloader, config, headers, session, dispatch
    routes.php              The 130-entry route table
    helpers.php             e(), flash(), redirect(), csrf_field(), settings()
    Core/
      Auth.php              Sign-in, password hashing, sessions, lockout
      Guard.php             Permission and role gates
      Csrf.php              Token generation and verification
      Database.php          PDO singleton and query helpers
      Audit.php             Append-only audit events with before/after JSON
      Notify.php            Notification and email_queue writer
      Mailer.php            Log and SMTP delivery
      Messenger.php         Placement conversations: scope, posting, unread, access
      Router.php            Route lookup and controller dispatch
      SyncRejected.php      Typed conflict for an offline write that cannot apply
      Upload.php            Allow-list, MIME, size and storage checks
      Settings.php          Typed runtime settings
      Preference.php        Per-member settings: emailed families, text size, motion
      Validator.php         Server-side field validation
      View.php              Layout rendering and error pages
    Services/
      Attendance.php        Student check-ins, haversine distance, organisation pin
      SiteCheckin.php       GPS-verified staff visits, 500 m rule, per-visit proof
      LogbookDigest.php     Daily and weekly record emails, per-day dedupe
      Diva.php              DIVA intents, guidebook retrieval, answer assembly
      DivaContract.php      The shared answer contract, evidence and verifier
      DivaFacts.php         Read-only record lookups scoped to the signed-in user
      DivaCritique.php      .docx reading and the eight-criterion report check
    Controllers/            One controller per role or shared concern (api/diva, api/sync)
    Views/                  Plain PHP templates, no business logic
  app/.htaccess             Denies direct HTTP access to app/ under Apache
  bin/
    send_daily_records.php  Emails each new daily record to the supervisor and mentor
    send_mail_queue.php     Processes due email_queue rows
    remind_logbooks.php     Creates weekly logbook reminders
    diva-eval.php           Runs the legacy and golden DIVA evaluation sets
    build-diva-corpus.php   Regenerates the guidebook text extract (needs pdftotext)
    build-diva-index.php    Rebuilds public/assets/data/diva-index.json
  bin/.htaccess             Denies direct HTTP access to bin/ under Apache
  config/
    config.sample.php       Template; copy to config.php locally
    .htaccess               Denies direct access
  public/
    manifest.webmanifest    Install metadata (name, icons, theme)
    offline.html            Offline fallback document
    assets/css/             app.css (layers) and offline.css
    assets/js/              app.js, offline.js, diva-offline.js, attendance.js
    assets/data/            diva-index.json for the offline engine
    assets/img/             logo, DIVA mark, icon-192/512
  storage/
    uploads/                Evidence files and profile photos, denied to direct HTTP
                            requests by storage/.htaccess (and by router.php on the
                            built-in server); files are served only through the
                            permission-checked download and avatar routes
    templates/              the log book, the Career Fair report template and the
                            guidebook PDF, served only through the download routes
    knowledge/              guidebook.txt, the dated text extract DIVA searches
    logs/                   mail.log, php-error.log, server.log
deploy/
  Dockerfile                PHP 8.3 + Apache image with pdo_mysql
  docker-compose.yml        Application plus MariaDB for a local stack
  apache-vhost.conf         DocumentRoot on digital-leap/, private folders denied
  php.ini                   Upload and session settings for the image
  README.md                 Deployment, Codespaces, HTTPS and PWA notes
.devcontainer/
  devcontainer.json         Codespaces: builds the stack, publishes port 8080
  docker-compose.yml        App plus MariaDB; the seed imports on first start
  config.codespaces.php     Disposable config for that stack, not a dev secret
  setup.sh                  Waits for the seed, gives storage/ to www-data
.github/workflows/lint.yml  php -l and node --check on every push, no secrets
docs/                       test log, VERIFY, user guide, design system, DIVA sets
```

The zip deliberately omits `config/config.php` and `storage/logs/*`; they
are created locally. `storage/uploads/` ships only the four sample
documents referenced by the seed data, so every download link on a fresh
import works. Uploads made in the running system stay local.

---

## 3. Request lifecycle

1. `index.php` requires `app/bootstrap.php`.
2. Bootstrap defines base paths, registers the autoloader, loads
   `config.php`, sets the timezone, creates storage directories, sends
   security headers and starts a hardened session.
3. Idle and absolute timeouts are checked. A POST without a valid CSRF
   token is rejected with HTTP 419. Forced password changes are redirected
   to `profile`.
4. The router looks up `"METHOD route"` in `app/routes.php`, yielding a
   controller, a method and a permission (or `null` for public pages).
5. `Guard` checks the session and permission before the controller is
   constructed. Role controllers also enforce `Guard::requireRole(...)`.
6. The controller reads input, calls core services and selects a view.
   Views are wrapped in the shared layout, except printable documents.

Authorization is deliberately centralised in the router. Adding a route
without a permission is visible in one file rather than hidden in a
controller.

---

## 4. Routes

All routes are query-string routes: `index.php?r=student/logbook`. Apache
rewrites `/student/logbook` to the same front controller.

| Area | Routes | Examples |
| --- | ---: | --- |
| Public and auth | 12 | root, `login`, `register`, `forgot`, `reset`, `token/supervisor` |
| Shared signed-in | 18 | `dashboard`, `profile`, `settings`, `notifications`, `guidebook`, `guidebook/pdf`, `download`, `logbook/template`, `career-fair/template` |
| Internal API | 2 | `api/sync`, `api/diva` |
| DIVA | 6 | `diva`, `diva/ask`, `diva/archive`, `diva/source`, `diva/report-check` |
| Messaging | 8 | `messages`, `messages/thread`, `messages/poll`, `messages/send` |
| Student | 24 | `student/preferences`, `student/logbook`, `student/placement`, `student/attendance/check`, `student/report`, `student/career-fair`, `student/results` |
| Supervisor | 7 | `supervisor/queue`, `supervisor/entry/comment`, `supervisor/evaluation` |
| Mentor | 13 | `mentor/students`, `mentor/visits`, `mentor/visits/checkin`, `mentor/reports` |
| Coordinator | 22 | `coordinator/placements`, `coordinator/placements/checkin`, `coordinator/grades`, `coordinator/letter` |
| Management | 3 | `management/kpis`, `management/export`, `management/print` |
| Administrator | 15 | `admin/users`, `admin/roles`, `admin/settings`, `admin/audit`, `admin/feedback`, `admin/mail` |

The exact table is `app/routes.php`.

**DIVA** returns one contract in both engines:
`{mode, intent, slots, evidence[{type,value,page?}], confidence, answer,
next_action}`. Online, `app/Services/Diva.php` and `DivaContract.php` produce
it through `POST ?r=api/diva`; offline, `public/assets/js/diva-offline.js`
runs the same stages over `public/assets/data/diva-index.json` (25 pages,
250 chunks, about 32 KB gzip). The pipeline is route → slots → weighted
BM25 retrieval over the guidebook with the shared synonym table →
conversation memory → compose → verify. Verification is the last gate: a
quotation must be a normalised substring of the page it cites, and every
figure must appear in the evidence, a cited page, the captured records or
the question itself; otherwise the draft is replaced with "I stopped short
of answering:" and the reason. A guidebook answer opens with the passage
itself — “Quoted from the internship guidebook, page N:” — before any
explanation, and a question that matches nothing in the corpus is refused
rather than answered from a weak page. An optional local model on
`127.0.0.1:11434` is disabled by default, accepts loopback only and falls
back silently after 400 ms; no external API is used. DIVA cannot approve
work, publish grades or send messages. Questions and answers are saved in
account-scoped `diva_chat` and `diva_turn` tables; the chat layout offers a
transparent compact logo, conversation search, New conversation, archive,
source disclosures and an answer-basis summary. Follow-ups use at most six
recent turns. Saved answers are snapshots, so ask again for current records.
`diva/report-check` reads a `.docx` (ZipArchive) or pasted text against the
eight Form E criteria and never prints a mark.

`php bin/diva-eval.php` runs 74 legacy cases; `--golden` adds the 60
questions in `docs/diva-golden-set.md`, a table that doubles as the fixture.
The run is read-only and writes `docs/diva-eval-results.txt`; a failed route,
citation, refusal or content check sets a nonzero exit status.

**Messaging** is scoped to the academic triangle — a student talks to their
own mentor and coordinator, never to another student. It supports quoted
replies, reactions, unread counts, a 15-minute edit window, tombstone
deletion, an idempotent `client_token` and ~8 s polling on the open thread.

---

## 5. Roles and permissions

Six roles are seeded: student, host supervisor, academic mentor, LEAP
coordinator, management/AQA and system administrator. Permissions are
stored in `permissions` and mapped through `role_permissions`; there are
39 permission keys. `diva.use` is granted to all six roles;
`message.use` only to student, mentor and coordinator.

Authorization has two levels:

- **Functional** — the route permission, checked by `Guard` before the
  controller exists.
- **Object-level** — SQL scoping by `student_id`, `mentor_id` or
  `supervisor_id`. A valid permission never grants access to another
  person's record. A failed attempt returns 403 and writes a
  `security.access_denied` audit event.

Role controllers call `Guard::requireRole()` in their constructors, so a
mentor cannot open supervisor pages even if a permission mapping is later
changed by mistake.

Nine routes are gated on `auth` rather than a permission key: `logout`,
`feedback`, `download`, `logbook/template`, `career-fair/template`, the two
`settings` routes and the two `guidebook` routes. They only touch the
signed-in member's own account or a published document, so there is no
object-level question for a permission to answer, and a key of their own
would have to be added to all six roles. `GET`
and `POST settings` deliberately do not reuse `profile.edit`: the management
role reads profiles but may not change account details, and it still needs to
choose its own text size and email families. Nothing in the settings page
touches another person's record. The `guidebook` pair is the same decision
seen from the other side: all six roles are meant to read the guidebook, so a
key held by every role would add nothing a session check does not already
enforce.

---

## 6. Data model

Thirty-three InnoDB tables in third normal form:

- Identity: `roles`, `permissions`, `role_permissions`, `users`,
  `access_token`, `user_preference`
- Placement: `student`, `application`, `organisation`, `placement`
- Evidence: `logbook_entry`, `logbook_day`, `logbook_comment`, `document`,
  `site_visit`
- Assessment: `criterion`, `evaluation`, `evaluation_score`, `mark`,
  `grade_scale`, `internship_report`
- Messaging: `conversation`, `conversation_member`, `message`,
  `message_reaction`
- Assistant history: `diva_chat`, `diva_turn`
- Operations: `notification`, `email_queue`, `audit_log`, `setting`,
  `feedback`, `sync_op`

Key rules enforced in the database and services:

- One placement per student per academic year; one preference per
  organisation per student.
- Supervisor must belong to the placement organisation; allocation is
  refused when capacity is full.
- Logbook states: draft → submitted → approved or returned →
  mentor_reviewed. Only mentor_reviewed weeks count towards the mark.
- The final mark is out of 200: Practical Internship 100 marks (logbook
  50, Form D host supervisor 40, student self evaluation 10), Internship
  Report 75 marks and Career Fair 25 marks. The mentor evaluation is
  advisory. Weights are settings, never hard-coded. A published mark is
  immutable.
- Internship workflow changes use transactions and audit events. DIVA query
  metadata is logged before conversation persistence, not in the same transaction.
- A message can be edited for 15 minutes; deletion leaves a tombstone row so
  replies keep their anchor. `client_token` makes a retried send idempotent.
- DIVA stores questions, answers and source metadata in private per-account
  history (`diva_chat`, `diva_turn`). Archiving sets `archived_at` rather than
  deleting messages. The separate `diva.query` audit event contains intent,
  tier and cited page numbers, not the raw question or uploaded report.
- `user_preference` is one row per (user, key), cascaded on user delete. An
  absent key means the documented default — every email family on, normal text,
  motion allowed — so a new account needs no seeding and an administrator can
  clear a member's choices without writing a row per default.

---

## 7. Security controls

- bcrypt password hashing (`password_hash`), generic login errors and a
  five-attempt, fifteen-minute lockout.
- Session cookie with HttpOnly, SameSite=Lax and Secure under HTTPS;
  strict mode; ID regeneration on login; 20-minute idle and 8-hour
  absolute timeouts.
- Per-session CSRF token, verified globally for every POST and rotated at
  login.
- PDO prepared statements everywhere; output escaped through `e()`.
- Content Security Policy `default-src 'self'` (plus `blob:` for the local
  profile-photo preview), no inline script or style.
- Upload allow-list by extension and detected MIME, 5 MB limit for evidence
  files (8 MB for profile photos), random stored name, storage outside the
  document root, permission-checked download controller. Profile photos are
  served through `?r=avatar` and can be removed by their owner.
- Single-use SHA-256 hashed tokens for password reset and supervisor
  invitations.
- Append-only audit log with before/after JSON and a field-level diff.
- Secrets are excluded from the repository; `config.sample.php` ships
  instead.

---

## 8. Operations scripts

```
php bin/send_daily_records.php   # email newly recorded days to supervisor and mentor
php bin/send_mail_queue.php      # deliver due messages
php bin/remind_logbooks.php      # create weekly reminders
php bin/diva-eval.php            # run the expanded DIVA evaluation set
php bin/build-diva-corpus.php "guidebook.pdf"   # rebuild the text extract (needs pdftotext)
```

Run `send_daily_records.php` once each evening before the mail queue. It
looks at days saved in the last two days on weeks that are still drafts and
emails one plain-text record per day to the placement's host supervisor and
academic mentor. A week sent to review gets no daily emails; its full record
is emailed once when the student submits. A day already emailed is never
sent twice. Add `--dry-run` to preview, or `--date=YYYY-MM-DD` to run as at
another date. Students can also press **Send** on a finished day row to queue
that one day immediately, through the same dedupe markers, so the evening run
skips anything already sent.

In the default `mail_mode=log`, messages are appended to
`storage/logs/mail.log`. The administrator can process or requeue
messages from the mail screen.

---

## 9. Deployment

Four supported shapes, all documented in `deploy/README.md`:

- **XAMPP / shared Apache** — copy the folder under the document root or set
  `app.base_url`, import the schema, copy the sample config. The `.htaccess`
  files rewrite routes and deny `app/`, `bin/`, `config/` and `storage/`.
- **Docker** — build `deploy/Dockerfile` and mount a config file and a
  database; `deploy/apache-vhost.conf` sets the document root to
  `digital-leap/` so only public files are reachable.
- **GitHub Codespaces** — open
  `https://codespaces.new/EssenceShady/Digital_LEAP_Assignment3_Group_G` and
  create the codespace; `.devcontainer/` builds the same image, starts
  MariaDB, imports the seed and publishes the app on port 8080 with a public
  forwarded link. It uses its own disposable config, so no local
  `config.php` is involved. A codespace stops on idle, so the link is a demo
  address rather than permanent hosting.
- **GitHub checkout** — clone the repository, run
  `php bin/build-diva-index.php` only if the guidebook extract changed, and
  follow the same config and import steps. `.github/workflows/lint.yml` runs
  `php -l` over every PHP file and `node --check` over every script; it needs
  no secrets.

The PWA requires HTTPS or `localhost`: browsers only register a service worker
in a secure context. Behind a reverse proxy, forward the original scheme (or
terminate TLS at Apache) so `bootstrap.php` emits HSTS and the Secure cookie
flag. The application reaches no internet host of its own; the only optional
network behaviour is the localhost model endpoint in `config.php`, off by
default.

`config/config.php` is written locally and ignored by git. It is still
tracked in this repository's older commits, as is the runtime file
`storage/logs/php-error.log`; `.gitignore` cannot hide a file that is already
tracked. The owner should remove both from the index once, with
`git rm --cached digital-leap/config/config.php digital-leap/storage/logs/php-error.log`,
then rotate any password the configuration held. No commit or push was made
as part of this build.

## 10. Testing

`docs/test-log.md` preserves the 102-case historical baseline and records the
Workstream A–D passes separately: the 62-route sweep at four widths, the
installable shell, offline drafts and sync, and the DIVA contract in both
engines. A final pass (TC-191 to TC-212) covers the three things the owner sent
back after that build — words breaking one character per line in the Documents
card, no way to install the app after **Not now**, and the absence of a settings
page for the person using it. TC-216 to TC-222 cover the guidebook page the
owner asked for next: the PDF opened in the browser, the PDF downloaded,
byte-for-byte comparison with the published file, the page-by-page list and
DIVA's page-guide entry for the new screen.
A historical pass does not imply that every later
screen was re-tested; each current claim cites the case that produced it.

`php bin/diva-eval.php` passes 74/74 legacy cases; `--golden` passes the 60
questions in `docs/diva-golden-set.md` with 60/60 routes, 32/32 citations and
3/3 refusals, and writes `docs/diva-eval-results.txt`. The local-model tier
remains disabled and untested. SMTP delivery and the full wall-clock
session-timeout windows also remain outside live testing. The final offline
browser pass is evidenced by DOM text and database rows because the
automation browser could not capture screenshots.

`docs/VERIFY.md` is the marker's script: accounts, role-by-role expected
results, the DIVA contract checks, security checks and known limitations.

## 11. Conventions

- One controller per role; methods map one-to-one to routes.
- Services in `app/Core` own rules that must not be duplicated.
- Views contain no SQL and no business logic; all output goes through
  `e()`.
- POST forms include `csrf_field()`; destructive actions use
  `data-confirm`.
- Runtime settings that a coordinator may change live in `setting`, not
  in code.
- New capability = permission row + role mapping + route permission.
- New report = controller method + route + view.
- `asset()` appends the file's modification time to CSS and JS URLs, so a
  changed stylesheet or script is never served from an old browser cache.

---

## 12. Licence

Source code: MIT. Report, figures and documentation: CC BY 4.0.
See `LICENSE`.

Group G: Mohamed Alie Kamara (905005581), Shadrach Saidu Saccoh Sesay
(905005695), Musa Bayoh (905005050), Fatmata BF Conteh (905005463),
Deborah Juliet Moiforay (905005455).
