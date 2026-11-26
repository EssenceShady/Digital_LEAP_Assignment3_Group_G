# VERIFY — Digital LEAP Internship Management System

This file tells the marker how to confirm that the submitted application matches the report and that the security claims hold. Everything here runs offline on XAMPP.

## 1. What is in the zip

```
COMP217_Assignment3_Digital_LEAP_Group_G.zip
├── digital-leap/            application (front controller, app, public assets, bin scripts)
├── database/digital_leap.sql   full schema + Sierra Leone seed data (33 tables)
├── .devcontainer/           optional Codespaces stack (PHP 8 + MariaDB, seed imported)
├── docs/test-log.md           historical test cases and latest verification status
├── docs/Digital_LEAP_User_Guide.md  three-page guide for the six roles
├── docs/diva-eval-results.txt expanded DIVA evaluation output
├── docs/diva-golden-set.md   60-question golden set (fixture and expected route/citation)
├── docs/VERIFY.md             this file
├── REPORT/                    Assignment 3 report (DOCX + PDF) and figures
├── INSTALL.txt                setup steps and demo credentials
├── README.md                  project overview and structure
└── LICENSE                    MIT (code) and CC BY 4.0 (report)
```

`digital-leap/config/config.php` is deliberately **not** shipped. The application copies `config.sample.php` on first run; set the database credentials in the copy.

A hosted copy is optional: `.devcontainer/` builds the same application with MariaDB inside GitHub Codespaces from the public repository, with its own disposable config (see `deploy/README.md` §4). It needs a GitHub account and a running codespace; the walkthrough below stays local and offline.

## 2. Install (about five minutes)

1. Start Apache and MySQL in the XAMPP control panel. Use a disposable clean installation for the walkthrough, not a database containing user work.
2. Import the database (phpMyAdmin → Import, or):
   `C:\xampp\mysql\bin\mysql.exe -u root < database\digital_leap.sql`
   This creates the `digital_leap` database and seed rows, but drops any existing application tables. It is not an upgrade command; never run it over the live database.
3. Copy `digital-leap/config/config.sample.php` to `digital-leap/config/config.php` and set `db.name`, `db.user`, `db.pass` if your MySQL root account has a password.
4. Open `http://localhost/digital-leap/` with the folder inside `htdocs`, **or** from inside `digital-leap` run:
   `C:\xampp\php\php.exe -S 127.0.0.1:8081 router.php`
   and open `http://127.0.0.1:8081/`.

The first page is the public landing page. On a cold tab the boot splash turns the supplied flag globe for about 1–2 seconds and then reveals that page; a second navigation in the same tab skips it, and clearing the tab's session storage brings it back. Sign-in is at `?r=login`. A newly registered student is signed in automatically and offered a one-minute welcome tour; the tour can also be started at any time from the **Take the tour** control.

The app can be installed from the browser's own install control, or from **How to install this app** in the account menu and on **My settings → This device**. Choosing **Not now** on the first banner hides the banner only: both of those controls stay, and each opens its own short note about what this browser can do.

The four sample PDFs referenced by the seed ship in `digital-leap/storage/uploads/`, and the official 16-page log book template, the Career Fair 2026 report template and the guidebook PDF ship in `digital-leap/storage/templates/`, so every **Download** link works straight after import.

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
4. **Logbook (Form E)** — weeks 1–9 approved, week 10 returned with feedback, week 11 submitted, week 12 still a draft. Open any week: the entry follows the paper log book with a **Daily record** table (day, date, tasks, hours) and a week total, then the weekly summary and any technical skills, challenges, meeting and reflection sections. Edit week 12, complete a day row and press **Save draft**; the row and the calculated total come back when the week is reopened, and submitting is refused until at least one day exists. Add a PDF attachment to week 12; it appears in the attachment list with a **Download** link. Use **Reply** under an existing comment: the banner names the author and the reply appears indented under the parent. The **Official log book template** card opens the 16-page PDF in the browser or downloads it.
5. **Report (Form C)** — uploaded report visible and downloadable. **Results (Form E)** — once a mark is published the breakdown reads Practical Internship out of 100 (logbook 50, Form D 40, self evaluation 10), Internship Report out of 75 and Career Fair out of 25, ending with “Final mark X / 200” and its percentage. To see one, sign in as `maria.kamara@leap.edu.sl`: her 2025/2026 placement is published at 147.45 / 200 = 72.98%, grade B.
6. **Self Evaluation** — the seeded self evaluation for this student is submitted and shown read-only with its four scores and overall reflection. On a placement whose self evaluation is still a draft, the same page shows the scoring form; a partial form or weights that do not total 100 are refused (see the supervisor evaluation rules).
7. **Career Fair 2026** — the page states the 11 November 2026, 12:00 pm deadline and shows one seeded on-time report from Mohamed with its review state. **Open template** shows the official two-day participation report and **Download template** saves it. Upload another PDF: it is listed with its timing (late, when after the deadline) and the academic mentor is notified. The Form E weights printed in the guide book are unchanged; this report is an additional item in the assessment portfolio.
8. **Documents** — a file opened by a supervisor, mentor or coordinator is labelled “Reviewed by …” with the reviewer name and time; files nobody has opened read “Not yet opened”. Signing in as the reviewer and downloading the CV, then reopening the placement, shows the label change. The card is a list rather than a table, so a long file name owns its line, its size, type and date sit under it, and **Download** stays whole; widen the window and the name and the button move onto one row. No word breaks in the middle at any width.
9. **Profile** — choose a JPG, PNG or WebP; the new photo appears in the header immediately, before saving, with its file name and size. **Save photo** stores it, **Remove photo** returns the header to the initials, and the password fields stay separate.
10. **Feedback** — **Send feedback** in the account menu (or **Share feedback** at the foot of the sidebar) opens a short form (rating 1–5 plus thoughts and suggestions); submit it and the administrator sees the row under **Feedback**. The floating corner button is the DIVA launcher, not feedback.
11. **Ask DIVA** (left menu) — the page names the three sources and says it cannot change records. Ask “When is my next logbook entry due?”: the answer comes from this student’s logbook and carries the badge **Rules & records** with an **Open my logbook** button. Ask “What are the objectives of LEAP?”: the answer opens with “Quoted from the internship guidebook, page 4:” and the passage itself before the explanation, and cites page 2 as well. Ask “How do I record a site visit?”: the badge changes to **Guidebook search** and the matching pages are listed with snippets; each page opens inside the app. Ask “What is DIVA?” for its own description and limits. Ask “How is the logbook weighted?” for the 200-mark model. Ask something the guidebook does not cover, such as “How can I get a job?”: DIVA refuses honestly instead of quoting an unrelated page. Ask about nothing (submit an empty box) and the page returns “Type a question first.” With the network off, ask a guidebook question: the badge adds “on this device”, the quotes still verify, and the footer says the answer will be saved when you reconnect; a question that needs live records is refused honestly and queued. Restore the network and the queued questions are sent with one toast, into one conversation.
12. **Check a report draft** — upload a `.docx` or paste text. DIVA lists the eight Form E criteria with their printed weights, says “Found”, “Thin” or “Not found” for each and quotes the line it matched, runs the presentation checks, and repeats that it gives no mark and rewrites nothing. A `.pdf` is refused with “DIVA cannot read PDF text offline in this build.” Every check writes one audit row with the format, word count and criterion counts — never the text.
13. **Messages** (left menu) — one conversation is seeded with the mentor about this placement. Open it: the system note about the Career Fair deadline, four messages and the seeded 👍 reaction appear. Send a message; an Edit control appears on it for 15 minutes, and after that the control is gone. Delete your message: the bubble becomes “This message was deleted” and the thread keeps its shape. The start-a-conversation block offers only the mentor and the coordinator — students cannot message other students — and opening a second conversation with the mentor returns “You already have a conversation about this placement.”
14. **My settings** (account menu, or the gear row at the foot of the sidebar) — four boxes choose which notification families are also copied to email; clearing one does not remove anything from the bell or the record history. **Text size** and **Reduce motion** preview the moment they are picked: the page type grows from 16 px to 18 px at the root, or transitions and slide-ins stop, before anything is saved. Saving shows “Your settings are saved.” and writes only the keys that changed (check with `SELECT * FROM user_preference WHERE user_id = 6;`), plus one `settings.updated` audit row holding the before and after values. The **This device** card reports the installed state, the DIVA answers and log book changes held offline and the number of cached responses, and offers to clear the two device-side copies — clearing browsed pages keeps the installed shell. Repeat this step as any other role: the page is the same for all six, and each account sees only its own choices.
15. **GPS check-in** — on **My placement**, press **Check in**. The browser asks for location; allow it and the card shows the time and the distance from the site pin, and **Check out** becomes the enabled button while **Check in** greys out. Refuse location and the check-in is still recorded but reads “location not shared”, because a student's attendance is attested when the device will not co-operate. The seeded database has no pins, so the distance line appears only after the coordinator sets one (Organisations → Edit → Site pin). The full mechanism — what is stored, who sees it and what it proves — is `docs/GPS-GUIDE.md`; for a strict staff visit that refuses without location, see the mentor step.

### Host Supervisor (`supervisor@brightfuture.sl`)
1. **Logbook queue (Form E)** lists week 11 from Mohamed Alie Kamara, newest submission first, and never shows a draft the student has not submitted. Set **Status** to *All submitted work* and press **Apply filters** — the list widens to approved, returned and mentor-reviewed entries; choose a **Week** and only that week remains; **Reset** returns to the default. **Log book template** opens the same 16-page PDF that the student sees.
2. Approve it; the student and mentor receive notifications. Try returning an entry without a comment first — it must be refused.
3. **Entry** — the daily record table, weekly summary and weekly sections are read before the decision, the three-way thread is visible and the supervisor can post or reply; the student and mentor are notified.
4. **Form D evaluation** — the menu item and page title name Form D. Six criteria, weights 15/25/20/15/10/15. Saving an incomplete form is refused; a complete form stores a total and it becomes the Form D component (40 of the 100 practical marks). On a phone each criterion stacks with its maximum, weight, score and evidence caption.
5. **Ask DIVA** — every role has the assistant. As supervisor, ask “What is waiting for me to review?” for the live queue, and “How is the logbook weighted?” for the Form E split on page 24.

### Academic Mentor (`deborahjulietmoiforay@gmail.com`)
1. **Students** shows the assigned placement and offers a search box, a placement-status select and an organisation select — the organisation list holds only organisations this mentor is placed with. Search “Mohamed” with status *Active* and one placement remains; **Reset** restores the list. **Logbook review (Form E)** shows approved entries awaiting mentor review, and the **Log book template** button opens the PDF.
2. Comment on an approved entry; status becomes mentor-reviewed. **Reply** nests the reply under the parent and moves focus to the box.
3. **Visits** — one completed visit, two scheduled (one virtual). Filter by student text, status and a scheduled-from/to range; an invalid date is ignored rather than breaking the page. A scheduled physical visit offers **Check in at site**: without a GPS reading within 500 m of the site pin the row reads **Not checked in** and **Complete** stays disabled; after the check-in it reads **Site verified** with the distance, and a virtual or phone visit reads **Not required**.
4. **Report assessment (Form C)** — Mohamed’s internship report is assessed; **Mentor evaluation** — three criteria weighted 40/35/25, advisory and carrying no marks. Both document lists have a search box and a review-state filter; “reviewed” means the file was opened, not that it was assessed. The **Career Fair 2026 submissions** card lists each student’s participation report with its timing and review state; opening the file marks it as reviewed for the student and coordinator.

### Coordinator (`bayohmusa001@gmail.com`)
1. **Applications** shows ranked preferences and carries a student search and a CV-status filter (uploaded / missing). **Placements** shows the seeded placements, filtered by academic year, status and organisation, with a **Log book template** button in the page actions; the page states that each placement is the record behind Forms A2, A3 and B. **Visits** filters by student text, status and scheduled date range.
2. **Allocate** — try allocating student 5 again for 2026/2027; the duplicate is refused. Try a supervisor from another organisation; it is refused. Capacity is enforced per organisation.
3. **Grades (Form E)** — the list shows Logbook, Form D, Self, Report, Career Fair and the final percentage. Open placement 2 (Form D 72.50, self 80.00, report 78.00, career fair 76.00, logbook 0/12): practical 37.00, final 114.50 / 200 = 57.25%, grade D. Enter the career fair score, **Compute**, then **Moderate** and **Publish**; a published mark cannot be recomputed. The academic mentor’s evaluation is shown for information and carries no marks. On a phone the breakdown stacks per module and ends with “Final mark X / 200”.
4. **Printable sheet** and **CSV export** download without error.
5. **Placement view** — **Documents** lists every file uploaded against the placement, including the Career Fair 2026 participation report, with its upload date and review label. **Staff site visits** records the coordinator's own monitoring check-in (name, role, time, distance and optional purpose); a reading more than 500 m from the site pin is refused. **Visits** lists each mentor visit's proof and the latest coordinator check-ins.

### Management / AQA (`fatmatabfc@gmail.com`)
1. **KPIs** — placement, completion, grade distribution and logbook compliance. Every bar carries its count and share, the weekly chart always shows all twelve weeks (including zero weeks), and a legend names the series; the chart keeps its length on a phone.
2. **Print** — printable management report; **Export** — placements/grades CSV.

### Administrator (`sesayshadrachsaidu@gmail.com`)
1. **Users** — create an account with password `password`; it is refused because the policy requires at least eight characters with an uppercase letter, a lowercase letter and a number. Create one with a valid initial password; the new user is forced to change it at first sign-in. The programme select is grouped by the five Limkokwing faculties, matching the public registration form.
2. **Users** — reset a password; the single-use link is queued. Open **Mail** and process the queue; the message appears in `digital-leap/storage/logs/mail.log`.
3. **Users** — suspend an account; sign-in is refused until reactivated. Unlock a locked account.
4. **Settings** — the page states the practical split as 100.00% and hides the advisory mentor weight. Change logbook to 60 so logbook, Form D and self evaluation total 110; the save is refused with “The practical weights (logbook, Form D, self evaluation) must add up to exactly 100 (currently 110.00).” Restore 60 → 50 and save; **Audit** shows the old and new values. Report 75 and Career Fair 25 are marks out of 200, not part of the 100 split. This is the system-wide settings page; **My settings** in the account menu is the administrator's own per-account copy, exactly as in student step 14. **Audit** itself filters by action, entity, actor and a from/to date range, keeps the filters in the pagination links, and shows an empty-state row when nothing matches.
5. **Roles** — the permission matrix shows 39 permissions mapped across six roles. `diva.use` is on all six; `message.use` only on student, mentor and coordinator; `evaluation.submit.self` only on the student role.
6. **Feedback** — ratings, thoughts and suggestions submitted from any account are listed with the author, role, page and time, newest first, and filter by rating and by the page they came from. The distribution chart above the list is labelled as covering all feedback, not only the filtered rows. There is no separate “ask for feedback” button in the admin panel: **Send feedback** in the account menu and **Share feedback** in the sidebar reach the same form from every signed-in page, including the administrator's, and this screen is the inbox for all of it.

### Every role — Guidebook
1. **Guidebook** sits at the foot of the navigation for all six roles. The page offers **Open the PDF**, which shows the 25-page guidebook in the browser's own PDF viewer, and **Download PDF (275 KB)**, which saves the same file. The list under it names all 25 pages with a line of their text; **Open in PDF** on any row opens the same document in the PDF viewer at that page (`?r=guidebook/pdf#page=N`) rather than showing a text copy. Only DIVA's own citation pages remain plain text, and each carries **Open page N in the PDF** back to the document.
2. The downloaded file is byte-for-byte the published guidebook: its SHA-256 is `1aad743f4d671e081042b183c7d7ec9b40e46d3dd91eec11604c7580fa252b14` and it is 281,848 bytes. Downloading writes one `guidebook.download` audit row; opening the PDF inline writes none.

## 5. DIVA contract — how to confirm it

Both engines return the same shape, `{mode, intent, slots, evidence, confidence, answer, next_action}`. Online it is produced by `app/Services/Diva.php` and `DivaContract.php` through `POST ?r=api/diva`; offline it is produced by `public/assets/js/diva-offline.js` from `public/assets/data/diva-index.json`. The two checks a marker can repeat without a network:

1. Run `C:\xampp\php\php.exe bin\diva-eval.php` from `digital-leap`. It executes the 74 legacy cases and, with `--golden`, the 60 questions in `docs/diva-golden-set.md`. All database reads happen in a read-only transaction; the report is rewritten to `docs/diva-eval-results.txt`. A failing case makes the exit status nonzero.
2. Open `?r=diva` in a browser, sign in as any role, then stop the web server (or use DevTools → Network → Offline). Ask “What are the objectives of LEAP?”: quotes must still appear, each cited page opens, and the label must say “on this device”. Ask for a personal figure such as the next logbook week: the answer either replays a previously saved answer with its saved timestamp or says plainly that it needs live records.
3. Restart the server. The queued questions are posted to `?r=api/diva`; expect one toast, no reload, and the questions in the conversation history. The shell caches are `leap-shell-v4` and `leap-runtime-v4`.

The verifier is the point of the contract: any quotation that is not a normalised substring of the cited page, and any figure not present in the evidence, cited pages, captured records or the question, replaces the draft with “I stopped short of answering:”. `bin/diva-eval.php` also checks that a “Why?” answer repeats the recorded basis of the previous answer and that a restored conversation keeps its resolved topic.

## 6. Security checks

| Check | How to confirm |
|---|---|
| Unauthenticated access | Sign out, open `?r=dashboard` → redirected to sign-in |
| Back button after sign-out | Sign out, press Back or re-open `?r=dashboard` → redirected to sign-in; the response carries `Cache-Control: no-store` and logout sends `Clear-Site-Data: "cache"` |
| Password policy | Register with `password1` (no uppercase) or `Password` (no digit) → refused; `Password123` → accepted |
| CSRF | Submit any form with the `_csrf` field removed (browser dev tools) → HTTP 419 |
| RBAC | Sign in as mentor, open `?r=admin/users`, `?r=supervisor/queue`, `?r=coordinator/visits` → 403 |
| Object-level access | Sign in as `aisha.koroma@leap.edu.sl`, open `?r=student/letter&id=1` → 403 |
| Own-account settings | `GET`/`POST settings` are gated on `auth` rather than a permission key, so all six roles reach their own page; the management role, which has no `profile.edit`, still saves with “Your settings are saved.” and no 403. `SettingsController` takes the user id from the session and accepts none from the request, so there is no parameter to point at another member's preferences |
| A mute is not a delete | Clear one email family in **My settings**, then trigger that notification: the bell, the badge and `?r=notifications` still hold the row, and nothing new is written to `email_queue` (TC-208) |
| Upload filtering | Rename a text file to `.pdf` and upload it to a logbook → rejected |
| Log book template | Signed out, request `?r=logbook/template` → redirected to sign-in; signed in, it returns `application/pdf` (inline, or `attachment` with `&download=1`) |
| Career Fair template | Signed out, request `?r=career-fair/template` → redirected to sign-in; signed in, it returns the 3-page participation report as `application/pdf`, inline or `attachment` with `&download=1` |
| Guidebook PDF | Signed out, request `?r=guidebook` or `?r=guidebook/pdf` → redirected to sign-in. Signed in, `?r=guidebook/pdf` returns `application/pdf` inline and `&download=1` returns it as an `attachment`; both carry `Content-Length: 281848` and `X-Content-Type-Options: nosniff`. The file is read from `storage/templates/`, which denies direct HTTP, so it is never reachable by path. Only the download is audited (`guidebook.download`), keeping the audit log to deliberate saves rather than every view |
| Career Fair upload | Upload a `.docx` or a text file renamed to `.pdf` on the student Career Fair page → rejected; only PDF, DOC and DOCX are stored, and the row is written as `career_fair_report` against the student's placement |
| Profile photo | `?r=avatar` serves a photo only after `canViewAvatar()` allows it: your own, staff with directory access, or someone on the same placement, conversation or logbook thread. Any other id, or a user with no photo, returns 404 “No profile photo.”; uploads are limited to JPG, PNG or WebP at 8 MB and re-encoded files must pass `getimagesize()` |
| Escaping | Enter `<script>alert(1)</script>` in an application note; view it as coordinator → shown as text |
| Filter input | Search with `' OR 1=1 --` in the mentor student filter → no rows, HTTP 200 (the filter is a bound parameter); an unknown organisation id returns no rows rather than another mentor's students |
| DIVA quotation | Ask “What are the objectives of LEAP?” → the reply begins “Quoted from the internship guidebook, page 4:” with the passage first; ask “How can I get a job?” → an honest no-support answer and no unrelated page number |
| Lockout | Enter a wrong password five times → account locked for 15 minutes |
| Source exposure | Request `/app/bootstrap.php`, `/bin/diva-eval.php`, `/config/config.php`, `/storage/logs/mail.log` and a file in `/storage/uploads/` directly → HTTP 403 under Apache (`app/`, `bin/`, `config/` and `storage/` each hold a `Require all denied` file), and 404 from the built-in server's `router.php`, which blocks the same prefixes. Uploads are served only through the permission-checked download route. `storage/` sits inside the application folder, so this is an access-control boundary, not a physical move outside the web root |
| DIVA boundaries | Answers cannot approve work, publish grades or send messages. Asking writes account-scoped history (`diva_chat`, `diva_turn`); archiving is a CSRF-protected POST and retains the stored rows. A URL question only pre-fills the composer |
| DIVA offline store | Saved answers and queued questions are stored per signed-in user id (`leap.offline.diva.*.<uid>`); the unscoped keys of earlier builds are deleted on load. Signing out removes every `leap.offline.diva.*` key. As the supervisor, asking a question the student saved offline returns the honest live-records refusal, not the student's answer |
| DIVA history | Send two questions, reload, then reopen the conversation from history. Both questions and answers remain. Cross-account conversation IDs must be refused for reading, asking and archiving |
| DIVA audit | Administrator → Audit → filter `diva.query`, `diva.chat_archived` and `diva.report_check`. Audit metadata excludes raw questions/report text; the separate conversation history intentionally stores questions and answers |
| DIVA honesty | Upload a `.pdf` to **Check a report draft** → “DIVA cannot read PDF text offline in this build”; no result is produced and the previous result is not replaced |
| Message scope | As the student, open `?r=messages`: the start-a-conversation list holds only the mentor and the coordinator. A crafted `messages/start` POST naming another student returns 403 and writes `message.denied` |
| Message editing window | Edit a message younger than 15 minutes → saved and marked “edited”. Post an edit for an older message → “Messages can be edited for 15 minutes after sending.” |
| Message deletion | Delete your own message → “This message was deleted” stays in the thread; the row is kept with `deleted_at` set, so replies keep their anchor |
| Message idempotency | Re-submitting the same `client_token` returns the existing message instead of adding a second row |
| Message encoding | Send a message containing 👍 or ✅ → the row keeps the four-byte characters (utf8mb4), and the reaction buttons accept the same set |
| Audit trail | Administrator → Audit lists sign-ins, refusals, uploads, mark changes and settings changes with before/after values. Comment replies are audited as `logbook.comment_added`, feedback as `feedback.submit`, messages as `message.sent` / `message.edited` / `message.deleted` / `message.denied` and DIVA as `diva.query` / `diva.report_check` |

The evidence table is in `docs/test-log.md`: TC-01–TC-102 are the historical baseline and later sections carry the passes up to TC-280; TC-216–TC-222 record the guidebook page added on 22 November, TC-271–TC-278 record the boot splash, the restructured landing stage and scroll reveal added on 26 November, and TC-279–TC-280 record the sign-in lockup and the install-sheet gate added later that day. Later sections distinguish executed checks from blocked or unrun checks — it is not an all-current-tests-passed claim. The three-page user guide for the six roles is `docs/Digital_LEAP_User_Guide.md`.

## 7. Known limitations

- Mail is written to `storage/logs/mail.log` (`mail_mode=log`). SMTP is implemented but was not exercised against a live server.
- The session idle/absolute timeouts (20 minutes / 8 hours) are configuration-verified, not wall-clock tested.
- The application targets PHP 8.0+ and MariaDB 10.4+; it uses only core PDO and no Composer dependencies.
- The report embeds twelve screenshots from `docs/screenshots/pwa/` (Figures 5–16): nine desktop role screens, the offline fallback, the queued sync strip and the phone dashboard. The full 128-file `pwa/` capture set is described in `docs/screenshots/README.md`; the phase 9, 10 and 11 folders add the later owner-requested states. The student **My settings** and **Guidebook** pages are the two screens still not in the `pwa/` set. Earlier width checks are historical, not a claim that every redesigned page was re-tested at every width. The latest live pass measured the changed screens at the 283 px automation viewport only; 768 px and 1440 px were not recaptured (TC-128). The 20 November pass had no screenshot capture either — the automation surface reported no visible viewport — so the Documents card, the account menu and **My settings** are evidenced by measured geometry and database rows, and the desktop shape was taken at a forced 760 px card width rather than in a real 1440 px window.
- **Installing as an app** depends on the browser. Chrome and Edge offer the install control and show the address-bar icon; desktop Firefox does not offer a user-controlled install for this kind of app, so the settings page and the account menu say so plainly and point to Chrome or Edge instead of showing a button that does nothing. Nothing installs without the person choosing it, and the site works normally either way. The install sheet never opens over the sign-in or registration form: it waits for the landing page's first scroll, and otherwise appears once the visitor is signed in.
- **The boot splash** is presentation, not a gate. It appears once per tab, between 1.0 s and 2.6 s (450–1400 ms with reduced motion), and is skipped entirely when JavaScript or session storage is unavailable, so no page depends on it to become usable. It turns the owner's globe with CSS 3D and shows a determinate meter; it does not fetch or wait for data, and a second navigation never shows it again.
- **My settings** holds four things: which notification families are also emailed, text size, reduced motion, and what this device is keeping offline. It is not a theme, a language or a password change, and it cannot change another member's records. A muted family was confirmed at the queue level — no `email_queue` row — and in the bell, not by opening a real mailbox, because delivery in this build is `mail_mode=log`. Large text was measured at the root (16 px → 18 px) at the automation viewport and a forced 760 px card; the fluid type scale means headings, buttons and tables follow it, but no per-component size audit was run.
- The DIVA sidebar icon is a path-drawn brain mark rendered in the inherited theme colour. It was checked in the DOM (no fill, `stroke` taken from the existing token) but not captured as an image, and at phone widths the sidebar is behind the menu button.
- The floating **Send feedback** button sits above the page corner; it can overlap the lower-right part of long content while scrolling. It is one small control and the content remains reachable by scrolling.
- `database/digital_leap.sql` stores the official Limkokwing programme names for seeded students. An older live database may retain legacy text; the forms accept it and offer the official list for new selections. Preserve live data and use reviewed, targeted updates for drift; re-importing the schema destroys existing application tables.
- **DIVA report checking** reads `.docx` (parsed with `ZipArchive`) and pasted text. PDF text is not extracted, because the build carries no PDF parser; a `.pdf` is refused with a plain message rather than answered from the filename. An upload is capped at 5 MB, and legacy `.doc` files are refused with a note to save as `.docx`.
- **DIVA local model** is off by default. The latest evaluation reports it disabled; no external API is used. A local model was not tested. The expanded cases and their actual results are in `docs/diva-eval-results.txt`. Rule matching and retrieval are bounded and can misunderstand wording; answer-basis details explain the implemented checks, not human-like reasoning or proof of correctness.
- **DIVA guidebook corpus** is a text extract of the approved guidebook stored in `storage/knowledge/guidebook.txt`. It is a snapshot taken at build time: DIVA never parses the PDF. The PDF that the **Guidebook** page serves lives beside the other templates at `storage/templates/Internship_Guidebook_LEAP_Clean_Layout.pdf`. If the guidebook is replaced, replace both: regenerate the extract on a machine with `pdftotext` by running `php bin/build-diva-corpus.php "path/to/guidebook.pdf"`, and drop the new PDF into `storage/templates/`, otherwise the citations and the page list will still point at the old page text.
- **DIVA logo** now uses transparent brain-only PNGs, displayed at 40 px in the heading, 64 px in the welcome state and 30 px beside answers. The original artwork is retained; no runtime GD extension is needed.
- **Latest DIVA verification:** the online contract, the offline engine, the saved-answer replay, the honest live-records refusal and the reconnect flush were driven in the running application; the flushed questions were confirmed in `diva_turn`. The 74 legacy cases and the 60-question golden set both pass (`docs/diva-eval-results.txt`). The final screenshot capture was blocked because the automation browser reported no visible surface, so that pass is evidenced by DOM text and database rows, not images. The optional localhost rephraser stayed disabled and was not tested.
- **Student self evaluation** was rendered read-only in the live pass because the seeded record is already submitted; the form appears only while an evaluation is a draft or has not been started, and its save path shares the tested supervisor evaluation validation, transaction and audit code. Re-importing the seed leaves the form editable for placements without a submitted self evaluation.
- **Messaging** has no message search, no typing indicator and no delivery receipt; polling runs every eight seconds while the tab is visible and a thread renders at most its first 300 messages. Conversations cannot be created between two students, and a coordinator joins a placement thread as an observer.

## 8. Assignment submission readiness — audit status

This section records the audit of the report against the marking criteria and what has been repaired since. The report DOCX, its PDF and the submission ZIP were rebuilt on 22 November 2026 around the six parts and the appendix.

| Required work | Status |
|---|---|
| Conceptual and logical models | Part A states the conceptual model (fourteen business entities, 15 labelled relationships). Part B's Figure 2 is the logical model: all 33 tables in six bands with keys and data types, regenerated from `database/digital_leap.sql` for this revision |
| Relationship analysis | Part C now walks all 60 foreign keys: Table 1 lists the 16 parent→child relationship groups, Table 2 lists the 17 parent tables with their child keys, and the text explains participation, delete rules and why the Career Fair score sits on `placement` and `document` |
| Data dictionary | Part D keeps the 33-row table summary; Appendix A adds the column-level dictionary for all 310 columns, generated from `information_schema` (types, nullability, defaults, PK/UQ/FK with delete rule, auto-increment) plus Table A2 for the 16 declared CHECK constraints |
| Required structure | Parts A–F cover conceptual model, logical model, relationships, data dictionary, architecture and security design; the landscape Appendix A follows Part F and precedes Conclusion and References |
| Formatting and length | Body is 3,819 words excluding the contents page, tables, figure captions, appendices and references, and the report states the count. Sections use 1-inch (2.54 cm) margins; the appendix is a landscape section with the same margins |
| Evidence and references | Figures 5–16 embed twelve application screenshots (desktop, phone, offline and sync states); Harvard-style references carry 22 November 2026 access dates; the DPG section names the shipped MIT and CC BY 4.0 licences |
| Accuracy and installation | Counts were checked against the live catalogue (33 tables, 310 columns, 60 FKs, 16 declared CHECKs, 18 reported including the two implicit JSON checks) and the route table (130 routes: 11 public, 9 auth-only, 110 permission-gated); `docs/test-log.md` runs to TC-280 |
| Group handover | Still pending: presentation/exhibition, printed copies and the submission-medium requirements are the group's to confirm. No user-acceptance signatures exist yet; `docs/UAT-SIGNOFF.md` is a blank template and must not be filled in on someone's behalf |

Remaining gaps: the student **My settings** and **Guidebook** pages are not yet captured as screenshots (see `docs/screenshots/README.md`), and the security section still benefits from reading as threat → control → implementation. No numerical grade is predicted from this audit.

