# Digital LEAP Internship Management System

A secure, offline-first, multi-role PHP 8 and MariaDB application built for
COMP217 Advanced Database Systems, Assignment 3, Group G, BSEM 2102,
Limkokwing University, Sierra Leone.

The system digitises the Digital LEAP internship process modelled in
Assignment 2: ranked preferences, placement allocation, weekly logbooks,
supervisor and mentor evaluation, internship reports, weighted marking,
moderation, publication and management reporting.

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
`docs/test-log.md`.

The sign-in screen offers one-click demo credentials for all six roles. The
interface provides a single password field with a show-password eye button
(no second checkbox), a notification bell and inbox preview, a profile page
that previews a chosen photo in the header before saving (and can remove
it), card and row shortcuts, an off-canvas navigation drawer, a five-step
guided tour, a top loading bar with button spinners, an internship progress
tracker and a floating feedback form. Registration and account creation use
the official Limkokwing faculty and programme list. Every role that handles
logbooks can open or download the 16-page internship log book template.
Students also have a Career Fair 2026 page that ships the official two-day
participation report template and records the uploaded report with its
deadline, late status and mentor review. Every signed-in role can ask DIVA,
an offline assistant that answers from the account's own records and from the
internship guidebook and can comment on a report draft without giving a mark.
Students, mentors and coordinators also have placement messaging with quoted
replies, reactions, unread counts and an edit window.
The layout was checked at 320 px, 360 px, 390 px, 427 px, 768 px, 1024 px,
1280 px and 1440 px without horizontal page overflow; wide tables stack
into labelled rows on phones, and dashboard charts label every bar with a
count and a percentage.

---

## 2. Directory layout

```
digital-leap/
  index.php                 Front controller; requires app/bootstrap.php
  router.php                Router for the PHP built-in server
  .htaccess                 Apache rewrite and security headers
  app/
    bootstrap.php           Paths, autoloader, config, headers, session, dispatch
    routes.php              The 120-entry route table
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
      Upload.php            Allow-list, MIME, size and storage checks
      Settings.php          Typed runtime settings
      Validator.php         Server-side field validation
      View.php              Layout rendering and error pages
    Services/
      Diva.php              DIVA intents, guidebook retrieval, answer assembly
      DivaFacts.php         Read-only record lookups scoped to the signed-in user
      DivaCritique.php      .docx reading and the eight-criterion report check
    Controllers/            One controller per role or shared concern
    Views/                  Plain PHP templates, no business logic
  bin/
    send_mail_queue.php     Processes due email_queue rows
    remind_logbooks.php     Creates weekly logbook reminders
    diva-eval.php           Runs the expanded DIVA evaluation set
    build-diva-corpus.php   Regenerates the guidebook text extract (needs pdftotext)
  config/
    config.sample.php       Template; copy to config.php locally
    .htaccess               Denies direct access
  public/
    assets/                 CSS and the single JavaScript file
  storage/
    uploads/                Evidence files and profile photos, outside the document root
    templates/              internship_log_book.pdf, the official 16-page template
    knowledge/              guidebook.txt, the dated text extract DIVA searches
    logs/                   mail.log, php-error.log, server.log
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
| Shared signed-in | 14 | `dashboard`, `profile`, `notifications`, `download`, `logbook/template`, `career-fair/template` |
| DIVA | 6 | `diva`, `diva/ask`, `diva/archive`, `diva/source`, `diva/report-check` |
| Messaging | 8 | `messages`, `messages/thread`, `messages/poll`, `messages/send` |
| Student | 22 | `student/preferences`, `student/logbook`, `student/report`, `student/career-fair`, `student/results` |
| Supervisor | 7 | `supervisor/queue`, `supervisor/entry/comment`, `supervisor/evaluation` |
| Mentor | 12 | `mentor/students`, `mentor/visits`, `mentor/reports` |
| Coordinator | 21 | `coordinator/placements`, `coordinator/grades`, `coordinator/letter` |
| Management | 3 | `management/kpis`, `management/export`, `management/print` |
| Administrator | 15 | `admin/users`, `admin/roles`, `admin/settings`, `admin/audit`, `admin/feedback`, `admin/mail` |

The exact table is `app/routes.php`.

**DIVA** answers in three tiers and always reports which one answered:
fixed rules over the signed-in user's own records, ranked search over the
25-page guidebook extract, and an optional local model on `127.0.0.1:11434`
that is disabled by default and falls back silently after 400 ms. It cannot
approve work, publish grades or send messages. Questions and answers are saved
in account-scoped `diva_chat` and `diva_turn` tables. The chat layout offers a
transparent compact logo, conversation search, New conversation, archive,
source disclosures and an answer-basis summary. Follow-ups use at most six
recent turns; this is bounded rule-based context, not a general-purpose LLM.
Saved answers are snapshots, so ask again for current records.
`diva/report-check` reads a `.docx` (ZipArchive) or pasted text against the eight
Form E criteria and never prints a mark.

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

---

## 6. Data model

Thirty InnoDB tables in third normal form:

- Identity: `roles`, `permissions`, `role_permissions`, `users`,
  `access_token`
- Placement: `student`, `application`, `organisation`, `placement`
- Evidence: `logbook_entry`, `logbook_comment`, `document`, `site_visit`
- Assessment: `criterion`, `evaluation`, `evaluation_score`, `mark`,
  `grade_scale`, `internship_report`
- Messaging: `conversation`, `conversation_member`, `message`,
  `message_reaction`
- Assistant history: `diva_chat`, `diva_turn`
- Operations: `notification`, `email_queue`, `audit_log`, `setting`,
  `feedback`

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
php bin/send_mail_queue.php      # deliver due messages
php bin/remind_logbooks.php      # create weekly reminders
php bin/diva-eval.php            # run the expanded DIVA evaluation set
php bin/build-diva-corpus.php "guidebook.pdf"   # rebuild the text extract (needs pdftotext)
```

In the default `mail_mode=log`, messages are appended to
`storage/logs/mail.log`. The administrator can process or requeue
messages from the mail screen.

---

## 9. Testing

`docs/test-log.md` preserves the historical 102-case baseline and separately
records the latest DIVA history, interface and comment-thread verification.
Historical passes do not imply that all redesigned screens have been
re-tested. Supervisor/mentor comment UI, archive confirmation,
sign-in/reopen and the full responsive matrix remain outstanding; the
browser permission guard blocked account switching. Student-thread Reply
and Cancel, DIVA input validation and persisted-row ownership were checked.

`docs/diva-eval-results.txt` contains the corrected expanded evaluation.
The harness now restores and asserts the selected account on every case,
checks response content and carried topics, and fails with a nonzero exit
status. The old harness cached role metadata without restoring the session,
so its role-labelled results are not reliable cross-role evidence.
The local-model tier remains disabled and untested. SMTP delivery and the
full wall-clock session-timeout windows also remain outside live testing.

`docs/VERIFY.md` is the marker's script: accounts, role-by-role expected
results, security checks and known limitations.

---

## 10. Conventions

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

## 11. Licence

Source code: MIT. Report, figures and documentation: CC BY 4.0.
See `LICENSE`.

Group G: Mohamed Alie Kamara (905005581), Shadrach Saidu Saccoh Sesay
(905005695), Musa Bayoh (905005050), Fatmata BF Conteh (905005463),
Deborah Juliet Moiforay (905005455).
