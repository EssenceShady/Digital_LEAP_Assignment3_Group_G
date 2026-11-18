# VERIFY — Digital LEAP Internship Management System

This file tells the marker how to confirm that the submitted application matches the report and that the security claims hold. Everything here runs offline on XAMPP.

## 1. What is in the zip

```
COMP217_Assignment3_Digital_LEAP_Group_G.zip
├── digital-leap/            application (front controller, app, public assets, bin scripts)
├── database/digital_leap.sql   full schema + Sierra Leone seed data (30 tables)
├── docs/test-log.md           historical test cases and latest verification status
├── docs/diva-eval-results.txt expanded DIVA evaluation output
├── docs/VERIFY.md             this file
├── REPORT/                    Assignment 3 report (DOCX + PDF) and figures
├── INSTALL.txt                setup steps and demo credentials
├── README.md                  project overview and structure
└── LICENSE                    MIT (code) and CC BY 4.0 (report)
```

`digital-leap/config/config.php` is deliberately **not** shipped. The application copies `config.sample.php` on first run; set the database credentials in the copy.

## 2. Install (about five minutes)

1. Start Apache and MySQL in the XAMPP control panel. Use a disposable clean installation for the walkthrough, not a database containing user work.
2. Import the database (phpMyAdmin → Import, or):
   `C:\xampp\mysql\bin\mysql.exe -u root < database\digital_leap.sql`
   This creates the `digital_leap` database and seed rows, but drops any existing application tables. It is not an upgrade command; never run it over the live database.
3. Copy `digital-leap/config/config.sample.php` to `digital-leap/config/config.php` and set `db.name`, `db.user`, `db.pass` if your MySQL root account has a password.
4. Open `http://localhost/digital-leap/` with the folder inside `htdocs`, **or** from inside `digital-leap` run:
   `C:\xampp\php\php.exe -S 127.0.0.1:8081 router.php`
   and open `http://127.0.0.1:8081/`.

The first page is the public landing page. Sign-in is at `?r=login`. A newly registered student is signed in automatically and offered a one-minute welcome tour; the tour can also be started at any time from the **Take the tour** control.

The four sample PDFs referenced by the seed ship in `digital-leap/storage/uploads/`, and the official 16-page log book template plus the Career Fair 2026 report template ship in `digital-leap/storage/templates/`, so every **Download** link works straight after import.

## 3. Demo accounts

| Role | Email | Password |
|---|---|---|
| System Administrator | sesayshadrachsaidu@gmail.com | Shadrach@2026 |
| LEAP / Internship Coordinator | bayohmusa001@gmail.com | Musa@2026 |
| Academic Mentor (Deborah Moiforay) | deborahjulietmoiforay@gmail.com | Mentor@1234 |
| Host Supervisor | supervisor@brightfuture.sl | Super@1234 |
| Student (Mohamed Alie Kamara) | mohamedalie247@gmail.com | Student@1234 |
| Management / AQA | fatmatabfc@gmail.com | Fatmata@2026 |

Secondary seeded accounts (same password pattern) exist for a second mentor, a second supervisor, and four more students; see `INSTALL.txt`.

## 4. Role walkthrough — expected results

### Student (`mohamedalie247@gmail.com`)
1. **Preferences** — five ranked choices exist; each shows its rank, sector, district, capacity and motivation. Move one up and confirm the ranks swap with no error; **Move up** and **Move down** are disabled at the ends of the list, and both controls disappear once a placement exists.
2. **Placement** — active placement at Bright Future Mining Ltd, shown as **Year 3, Semester 5**, weeks and supervisor visible; **Placement letter** opens a printable letter (reference `DL/PL/2026/0001`).
3. **Dashboard** — the **Internship progress** card tracks placement, logbook, both evaluations, the site visit, the report and publication, with a percentage and step states.
4. **Logbook** — weeks 1–8 approved, 9–10 returned with feedback, 11 mentor-reviewed, 12 submitted. Open week 12 and add a PDF attachment; it appears in the attachment list with a **Download** link. Use **Reply** under an existing comment: the banner names the author and the reply appears indented under the parent. The **Official log book template** card opens the 16-page PDF in the browser or downloads it.
5. **Report** — uploaded report visible and downloadable. **Results** — once a mark is published the breakdown reads Practical Internship out of 100 (logbook 50, Form D 40, self evaluation 10), Internship Report out of 75 and Career Fair out of 25, ending with “Final mark X / 200” and its percentage. To see one, sign in as `maria.kamara@leap.edu.sl`: her 2025/2026 placement is published at 147.45 / 200 = 72.98%, grade B.
6. **Self Evaluation** — the seeded self evaluation for this student is submitted and shown read-only with its four scores and overall reflection. On a placement whose self evaluation is still a draft, the same page shows the scoring form; a partial form or weights that do not total 100 are refused (see the supervisor evaluation rules).
7. **Career Fair 2026** — the page states the 11 November 2026, 12:00 pm deadline and shows one seeded on-time report from Mohamed with its review state. **Open template** shows the official two-day participation report and **Download template** saves it. Upload another PDF: it is listed with its timing (late, when after the deadline) and the academic mentor is notified. The Form E weights printed in the guide book are unchanged; this report is an additional item in the assessment portfolio.
8. **Documents** — a file opened by a supervisor, mentor or coordinator is labelled “Reviewed by …” with the reviewer name and time; files nobody has opened read “Not yet opened”. Signing in as the reviewer and downloading the CV, then reopening the placement, shows the label change.
9. **Profile** — choose a JPG, PNG or WebP; the new photo appears in the header immediately, before saving, with its file name and size. **Save photo** stores it, **Remove photo** returns the header to the initials, and the password fields stay separate.
10. **Feedback** — the floating **Send feedback** button opens a short form (rating 1–5 plus thoughts and suggestions); submit it and the administrator sees the row under **Feedback**.
11. **Ask DIVA** (left menu) — the page names the three sources and says it cannot change records. Ask “When is my next logbook entry due?”: the answer comes from this student’s logbook and carries the badge **Rules and live records** with an **Open my logbook** button. Ask “How do I record a site visit?”: the badge changes to **Guidebook search** and pages 7, 20 and 8 are listed with snippets; each page opens inside the app. Ask “How is the logbook weighted?” for the 200-mark model. Ask about nothing (submit an empty box) and the page returns “Type a question first.”
12. **Check a report draft** — upload a `.docx` or paste text. DIVA lists the eight Form E criteria with their printed weights, says “Found”, “Thin” or “Not found” for each and quotes the line it matched, runs the presentation checks, and repeats that it gives no mark and rewrites nothing. A `.pdf` is refused with “DIVA cannot read PDF text offline in this build.” Every check writes one audit row with the format, word count and criterion counts — never the text.
13. **Messages** (left menu) — one conversation is seeded with the mentor about this placement. Open it: the system note about the Career Fair deadline, four messages and the seeded 👍 reaction appear. Send a message; an Edit control appears on it for 15 minutes, and after that the control is gone. Delete your message: the bubble becomes “This message was deleted” and the thread keeps its shape. The start-a-conversation block offers only the mentor and the coordinator — students cannot message other students — and opening a second conversation with the mentor returns “You already have a conversation about this placement.”

### Host Supervisor (`supervisor@brightfuture.sl`)
1. **Queue** lists week 12 from Mohamed Alie Kamara. **Log book template** opens the same 16-page PDF that the student sees.
2. Approve it; the student and mentor receive notifications. Try returning an entry without a comment first — it must be refused.
3. **Entry** — the three-way thread is visible and the supervisor can post or reply; the student and mentor are notified.
4. **Evaluation** — six criteria, weights 15/25/20/15/10/15. Saving an incomplete form is refused; a complete form stores a total and it becomes the Form D component (40 of the 100 practical marks). On a phone each criterion stacks with its maximum, weight, score and evidence caption.
5. **Ask DIVA** — every role has the assistant. As supervisor, ask “What is waiting for me to review?” for the live queue, and “How is the logbook weighted?” for the Form E split on page 24.

### Academic Mentor (`deborahjulietmoiforay@gmail.com`)
1. **Students** shows the assigned placement; **Logbook** shows approved entries awaiting mentor review, and the **Log book template** button opens the PDF.
2. Comment on an approved entry; status becomes mentor-reviewed. **Reply** nests the reply under the parent and moves focus to the box.
3. **Visits** — one completed visit, two scheduled (one virtual).
4. **Reports** — Mohamed’s internship report is assessed; **Evaluation** — three criteria weighted 40/35/25. The **Career Fair 2026 submissions** card lists each student’s participation report with its timing and review state; opening the file marks it as reviewed for the student and coordinator.

### Coordinator (`bayohmusa001@gmail.com`)
1. **Applications** shows ranked preferences; **Placements** shows the five seeded placements and a **Log book template** button in the page actions.
2. **Allocate** — try allocating student 5 again for 2026/2027; the duplicate is refused. Try a supervisor from another organisation; it is refused. Capacity is enforced per organisation.
3. **Grades** — the list shows Logbook, Form D, Self, Report, Career Fair and the final percentage. Open placement 2 (Form D 72.50, self 80.00, report 78.00, career fair 76.00, logbook 0/12): practical 37.00, final 114.50 / 200 = 57.25%, grade D. Enter the career fair score, **Compute**, then **Moderate** and **Publish**; a published mark cannot be recomputed. The academic mentor’s evaluation is shown for information and carries no marks. On a phone the breakdown stacks per module and ends with “Final mark X / 200”.
4. **Printable sheet** and **CSV export** download without error.
5. **Placement view** — **Documents** lists every file uploaded against the placement, including the Career Fair 2026 participation report, with its upload date and review label.

### Management / AQA (`fatmatabfc@gmail.com`)
1. **KPIs** — placement, completion, grade distribution and logbook compliance. Every bar carries its count and share, the weekly chart always shows all twelve weeks (including zero weeks), and a legend names the series; the chart keeps its length on a phone.
2. **Print** — printable management report; **Export** — placements/grades CSV.

### Administrator (`sesayshadrachsaidu@gmail.com`)
1. **Users** — create an account with password `password`; it is refused because the policy requires at least eight characters with an uppercase letter, a lowercase letter and a number. Create one with a valid initial password; the new user is forced to change it at first sign-in. The programme select is grouped by the five Limkokwing faculties, matching the public registration form.
2. **Users** — reset a password; the single-use link is queued. Open **Mail** and process the queue; the message appears in `digital-leap/storage/logs/mail.log`.
3. **Users** — suspend an account; sign-in is refused until reactivated. Unlock a locked account.
4. **Settings** — the page states the practical split as 100.00% and hides the advisory mentor weight. Change logbook to 60 so logbook, Form D and self evaluation total 110; the save is refused with “The practical weights (logbook, Form D, self evaluation) must add up to exactly 100 (currently 110.00).” Restore 60 → 50 and save; **Audit** shows the old and new values. Report 75 and Career Fair 25 are marks out of 200, not part of the 100 split.
5. **Roles** — the permission matrix shows 39 permissions mapped across six roles. `diva.use` is on all six; `message.use` only on student, mentor and coordinator; `evaluation.submit.self` only on the student role.
6. **Feedback** — ratings, thoughts and suggestions submitted from any account are listed with the author, role, page and time. There is no separate “ask for feedback” button in the admin panel: the floating **Send feedback** control already appears on every signed-in page, including the administrator's, and this screen is the inbox for all of it.

## 5. Security checks

| Check | How to confirm |
|---|---|
| Unauthenticated access | Sign out, open `?r=dashboard` → redirected to sign-in |
| Back button after sign-out | Sign out, press Back or re-open `?r=dashboard` → redirected to sign-in; the response carries `Cache-Control: no-store` and logout sends `Clear-Site-Data: "cache"` |
| Password policy | Register with `password1` (no uppercase) or `Password` (no digit) → refused; `Password123` → accepted |
| CSRF | Submit any form with the `_csrf` field removed (browser dev tools) → HTTP 419 |
| RBAC | Sign in as mentor, open `?r=admin/users`, `?r=supervisor/queue`, `?r=coordinator/visits` → 403 |
| Object-level access | Sign in as `aisha.koroma@leap.edu.sl`, open `?r=student/letter&id=1` → 403 |
| Upload filtering | Rename a text file to `.pdf` and upload it to a logbook → rejected |
| Log book template | Signed out, request `?r=logbook/template` → redirected to sign-in; signed in, it returns `application/pdf` (inline, or `attachment` with `&download=1`) |
| Career Fair template | Signed out, request `?r=career-fair/template` → redirected to sign-in; signed in, it returns the 3-page participation report as `application/pdf`, inline or `attachment` with `&download=1` |
| Career Fair upload | Upload a `.docx` or a text file renamed to `.pdf` on the student Career Fair page → rejected; only PDF, DOC and DOCX are stored, and the row is written as `career_fair_report` against the student's placement |
| Profile photo | `?r=avatar` serves only the signed-in user's own file through a permission-checked controller and returns 404 when no photo exists; uploads are limited to JPG, PNG or WebP at 8 MB |
| Escaping | Enter `<script>alert(1)</script>` in an application note; view it as coordinator → shown as text |
| Lockout | Enter a wrong password five times → account locked for 15 minutes |
| Source exposure | Request `/config/config.php` and `/storage/logs/mail.log` directly → 404 |
| DIVA boundaries | Answers cannot approve work, publish grades or send messages. Asking writes account-scoped history (`diva_chat`, `diva_turn`); archiving is a CSRF-protected POST and retains the stored rows. A URL question only pre-fills the composer |
| DIVA history | Send two questions, reload, then reopen the conversation from history. Both questions and answers remain. Cross-account conversation IDs must be refused for reading, asking and archiving |
| DIVA audit | Administrator → Audit → filter `diva.query`, `diva.chat_archived` and `diva.report_check`. Audit metadata excludes raw questions/report text; the separate conversation history intentionally stores questions and answers |
| DIVA honesty | Upload a `.pdf` to **Check a report draft** → “DIVA cannot read PDF text offline in this build”; no result is produced and the previous result is not replaced |
| Message scope | As the student, open `?r=messages`: the start-a-conversation list holds only the mentor and the coordinator. A crafted `messages/start` POST naming another student returns 403 and writes `message.denied` |
| Message editing window | Edit a message younger than 15 minutes → saved and marked “edited”. Post an edit for an older message → “Messages can be edited for 15 minutes after sending.” |
| Message deletion | Delete your own message → “This message was deleted” stays in the thread; the row is kept with `deleted_at` set, so replies keep their anchor |
| Message idempotency | Re-submitting the same `client_token` returns the existing message instead of adding a second row |
| Message encoding | Send a message containing 👍 or ✅ → the row keeps the four-byte characters (utf8mb4), and the reaction buttons accept the same set |
| Audit trail | Administrator → Audit lists sign-ins, refusals, uploads, mark changes and settings changes with before/after values. Comment replies are audited as `logbook.comment_added`, feedback as `feedback.submit`, messages as `message.sent` / `message.edited` / `message.deleted` / `message.denied` and DIVA as `diva.query` / `diva.report_check` |

The evidence table is in `docs/test-log.md`: TC-01–TC-102 are the historical baseline. The latest addendum distinguishes executed checks from blocked or unrun checks; it is not an all-current-tests-passed claim.

## 6. Known limitations

- Mail is written to `storage/logs/mail.log` (`mail_mode=log`). SMTP is implemented but was not exercised against a live server.
- The session idle/absolute timeouts (20 minutes / 8 hours) are configuration-verified, not wall-clock tested.
- The application targets PHP 8.0+ and MariaDB 10.4+; it uses only core PDO and no Composer dependencies.
- The report currently contains diagrams, not application screenshots. `docs/screenshots/diva-welcome.png` is a newly captured browser screenshot; it has not been inserted into the report. Earlier width checks below are historical, not a claim that every redesigned page was re-tested at every width.
- The floating **Send feedback** button sits above the page corner; it can overlap the lower-right part of long content while scrolling. It is one small control and the content remains reachable by scrolling.
- `database/digital_leap.sql` stores the official Limkokwing programme names for seeded students. An older live database may retain legacy text; the forms accept it and offer the official list for new selections. Preserve live data and use reviewed, targeted updates for drift; re-importing the schema destroys existing application tables.
- **DIVA report checking** reads `.docx` (parsed with `ZipArchive`) and pasted text. PDF text is not extracted, because the build carries no PDF parser; a `.pdf` is refused with a plain message rather than answered from the filename. An upload is capped at 5 MB, and legacy `.doc` files are refused with a note to save as `.docx`.
- **DIVA local model** is off by default. The latest evaluation reports it disabled; no external API is used. A local model was not tested. The expanded cases and their actual results are in `docs/diva-eval-results.txt`. Rule matching and retrieval are bounded and can misunderstand wording; answer-basis details explain the implemented checks, not human-like reasoning or proof of correctness.
- **DIVA guidebook corpus** is a text extract of the approved guidebook stored in `storage/knowledge/guidebook.txt`. It is a snapshot taken at build time: the shipped application never parses the PDF. If the guidebook is replaced, regenerate the extract on a machine with `pdftotext` by running `php bin/build-diva-corpus.php "path/to/guidebook.pdf"`, otherwise the citations will still point at the old page text.
- **DIVA logo** now uses transparent brain-only PNGs, displayed at 40 px in the heading, 64 px in the welcome state and 30 px beside answers. The original artwork is retained; no runtime GD extension is needed.
- **Latest DIVA/comment verification:** a welcome screenshot was captured and reviewed. The student thread Reply/Cancel controls were tested through the running browser without posting comments. Further screenshots were blocked when the browser became hidden, and the permission guard blocked supervisor sign-in; supervisor/mentor UI, archive confirmation and sign-in/reopen checks remain outstanding. Persisted rows after sign-out and cross-account ownership were checked separately with read-only database/service tests.
- **Student self evaluation** was rendered read-only in the live pass because the seeded record is already submitted; the form appears only while an evaluation is a draft or has not been started, and its save path shares the tested supervisor evaluation validation, transaction and audit code. Re-importing the seed leaves the form editable for placements without a submitted self evaluation.
- **Messaging** has no message search, no typing indicator and no delivery receipt; polling runs every eight seconds while the tab is visible and a thread renders at most its first 300 messages. Conversations cannot be created between two students, and a coordinator joins a placement thread as an observer.

## 7. Assignment submission readiness — audit, not completed repairs

The working application is not sufficient to declare every assignment criterion complete. The current report was inspected read-only; its DOCX/PDF and the submission ZIP have not been rebuilt for this DIVA revision.

| Required work | Current gap |
|---|---|
| Conceptual and logical models | No separate conceptual ERD. The existing ERD shows only 11 entities, not the full 30-table schema, and lacks comprehensive cardinality/optionality |
| Relationship analysis | Needs explicit relationship, participation and business-rule explanations |
| Data dictionary | The table catalogue is not a column-level dictionary; add types, sizes, keys, nullability, defaults and constraints |
| Required structure | Align Parts A–F to Conceptual ERD, Logical ERD, Relationships, Data Dictionary, System Architecture and Security Design |
| Formatting and length | The inspected body has 6,226 words excluding tables/references, beyond the 3,000–4,000 requirement. It uses Calibri rather than Tahoma 12 and is not double-spaced |
| Evidence and references | Embed actual application screenshots, add appropriate in-text Harvard citations, and obtain genuine user-acceptance evidence; no signatures or results should be invented |
| Accuracy and installation | Update stale schema/feature claims, provide least-privilege database-user setup, and distinguish tested controls from unverified claims |
| Group handover | Presentation/exhibition, printed report and submission-media/delivery requirements still need confirmation by the group |

Architecture/security material and licensing are present, but the security section needs clear threat → control → implementation mapping. No numerical grade is predicted from this audit.
