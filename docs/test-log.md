# Digital LEAP Internship Management System — Test Log

**Module:** COMP217 Advanced Database Systems — Assignment 3
**Group:** BSEM 2102, Semester 5, Year 3 — Group G
**Build date:** 16 November 2026
**Environment:** Windows 11, PHP 8.0.30 (XAMPP), MariaDB 10.4.32, Apache, headless Chrome at 427×952, 390×844, 360×800 and 320×700 (phone), plus 768×1024, 1024×768, 1280×800 and 1440×900 (tablet/desktop); the fifth pass also ran in the in-app Chromium at 279×505
**Database snapshot:** the earlier baseline had 28 tables. The schema now has 33: `diva_chat` and `diva_turn` for assistant history, `sync_op` for offline writes and `user_preference` for per-member settings were added on top of it. Every one of those was applied additively to the live database; existing records were not re-imported or deleted.
**Accounts used:** `sesayshadrachsaidu@gmail.com` (administrator), `bayohmusa001@gmail.com` (coordinator), `fatmatabfc@gmail.com` (management), `deborahjulietmoiforay@gmail.com` (mentor), `supervisor@brightfuture.sl`, `mohamedalie247@gmail.com` (student), plus the seeded secondary accounts. Passwords are listed in `INSTALL.txt` inside the submission zip.

The historical cases were recorded on 13, 14, 15 and 16 November 2026. Evidence is the observed HTTP status, on-screen message, database query result, or response header. New verification is listed separately below, including checks that could not run. The schema import drops existing tables: only import it into a disposable clean test database, never over live user records.

The earlier DIVA evaluation cached role metadata without restoring that role's session on every iteration. Its old printed role labels are not reliable for cross-role verification; the corrected expanded evaluation supersedes those reports.

## Summary — historical baseline (TC-01 to TC-102)

| Metric | Result |
|---|---|
| Cases executed | 102 |
| Passed | 102 |
| Failed | 0 |
| Defects found during the run | 27 (all fixed and re-tested) |
| Test pass rate | **100%** |

## Cases

### Authentication and session security

| ID | Scenario | Expected | Observed | Result |
|---|---|---|---|---|
| TC-01 | Request `?r=dashboard` with no session | Redirect to sign-in | HTTP 302 → `?r=login` | Pass |
| TC-02 | POST to `?r=login` without `_csrf` | Request rejected | HTTP 419, error page (re-confirmed on Apache after defect D17; the PHP built-in server returns the same) | Pass |
| TC-03 | Five consecutive wrong passwords for a test account | Account locks for 15 minutes, generic message | Fifth attempt wrote `locked_until` +15 min; correct password then returned “This account is temporarily locked. Try again later.” | Pass |
| TC-04 | Administrator unlocks the locked account | Lock cleared, normal sign-in resumes | `failed_attempts=0`, `locked_until=NULL`; sign-in and dashboard returned 200 | Pass |
| TC-05 | Sign in as an account suspended by the administrator | Sign-in refused | “Invalid credentials, or the account is unavailable.”; reactivation restored access | Pass |
| TC-06 | Administrator creates an account with password `password` (no digit, no uppercase letter) | Rejected with the password policy message | “Set an initial password of at least eight characters with an uppercase letter, a lowercase letter and a number.”; no row inserted | Pass |
| TC-07 | Administrator creates an account with a valid initial password, then the user signs in | User must change password before any other page | Redirected to `?r=profile` with “Choose a new password before continuing.”; after the change `must_change_password=0` and the dashboard returned 200 | Pass |
| TC-08 | Administrator resets a password | Single-use reset link queued, delivered in log mode, usable once | Queue row moved `queued → sent`; `mail.log` recorded the message; token marked used; new password signed in; reused link redirected to sign-in | Pass |
| TC-09 | Administrator invites a host supervisor with no password | Account `invited`, seven-day token, activation mail queued | `status=invited`; token `supervisor_access` expiring in 7 days; mail queued; consuming the link set `active`; second use refused | Pass |

### Authorisation and input handling

| ID | Scenario | Expected | Observed | Result |
|---|---|---|---|---|
| TC-10 | Mentor requests `?r=admin/users` | 403 | 403 Forbidden | Pass |
| TC-11 | Coordinator requests `?r=admin/settings` | 403 | 403 Forbidden | Pass |
| TC-12 | Mentor requests `?r=supervisor/queue` | 403 | 403 Forbidden (defect D8 fixed) | Pass |
| TC-13 | Supervisor requests `?r=mentor/students` | 403 | 403 Forbidden | Pass |
| TC-14 | Mentor requests `?r=coordinator/visits` (all-student visit list) | 403 | 403 Forbidden (defect D8 fixed) | Pass |
| TC-15 | Student requests `?r=coordinator/visits` | 403 | 403 Forbidden | Pass |
| TC-16 | Student opens `?r=student/letter&id=1` for their own placement | Printable letter | HTTP 200, reference `DL/PL/2026/0001` rendered | Pass |
| TC-17 | Another student opens the same letter | 403 | 403 Forbidden | Pass |
| TC-18 | Student downloads another student’s uploaded document (`?r=download&id=4`) | 403 | 403 Forbidden | Pass |
| TC-19 | Application form submitted with `<script>alert(1)</script>` in a text field | Stored and rendered escaped | Coordinator view contained `&lt;script&gt;alert(1)&lt;/script&gt;`; no script executed | Pass |
| TC-20 | Upload a text file renamed to `.pdf` | Rejected before storage | “The file contents do not match its extension.”; no `document` row | Pass |
| TC-21 | Request `/config/config.php` and `/storage/logs/mail.log` directly | Not served | Both returned 404; `/public/assets/css/app.css` returned 200 | Pass |
| TC-22 | Every route in `app/routes.php` resolves to a real controller method | No missing handler | 101 routes checked by script; 0 missing classes or methods | Pass |

### Logbook workflow

| ID | Scenario | Expected | Observed | Result |
|---|---|---|---|---|
| TC-23 | Student submits week 12 with 38 hours | Entry becomes `submitted`; supervisor and mentor notified | Entry 12 `submitted`; notification rows and queued mail created for both reviewers | Pass |
| TC-24 | Supervisor returns an entry without a comment | Rejected | “Enter a comment explaining why the entry is being returned.”; status unchanged | Pass |
| TC-25 | Supervisor returns an entry with a comment, student resubmits | `returned → submitted` | Comment visible to the student; resubmission restored `submitted` | Pass |
| TC-26 | Mentor comments on a supervisor-approved entry | Status becomes `mentor_reviewed` | Entry 11 `mentor_reviewed`; comment stored; student notified | Pass |
| TC-27 | Supervisor submits only part of the evaluation form | Rejected | “Score every criterion before saving the evaluation.”; no total stored | Pass |
| TC-28 | Supervisor completes the evaluation | Total cached and status `submitted` | Evaluation 1 total `79.00`, status `submitted` | Pass |

### Marking and publication

> TC-29 to TC-36 were executed in the first pass, when the weights were logbook 20, Form D 40, mentor 20 and report 20. The assessment was later re-based on the lecturer's 200-mark model; the first-pass values are kept as historical evidence, and the re-tested arithmetic is in TC-92 to TC-102.

| ID | Scenario | Expected | Observed | Result |
|---|---|---|---|---|
| TC-29 | Compute a mark while a component is missing | Blocked with the missing components named | Flash listed missing components; no mark written | Pass |
| TC-30 | Compute the mark for placement 1 | Weighted total and grade computed from 20/40/20/20 | Logbook `75.00`, supervisor `79.00`, mentor `76.50`, report `76.00` → weighted `77.10`, grade B+ | Pass |
| TC-31 | Moderate then publish the mark | Status `moderated → published`; student sees result | Audit rows written; student results page showed `77.10`, `B+`, `Published` | Pass |
| TC-32 | Recompute a published mark | Refused | “A published mark cannot be recomputed.”; values unchanged | Pass |
| TC-33 | Allocation with an existing placement for the same academic year | Rejected | “That student already has a placement for 2026/2027.” | Pass |
| TC-34 | Allocation where the supervisor belongs to another organisation | Rejected | “The host supervisor does not belong to the chosen organisation.” | Pass |
| TC-35 | Allocation when the organisation is at capacity | Rejected | “The organisation is at capacity (1) for 2026/2027.” (capacity temporarily set to 1, then restored) | Pass |
| TC-36 | Reorder two placement preferences | Ranks swap without a duplicate-key error | Ranks 4 and 5 swapped; three-step temporary rank prevented `uq_app_rank` collision (defect D6 fixed) | Pass |

### Documents, settings, exports

| ID | Scenario | Expected | Observed | Result |
|---|---|---|---|---|
| TC-37 | Upload a valid 447-byte PDF as a logbook attachment | Stored with random name, listed on entry | `document` row `logbook_attachment`, MIME `application/pdf`, random stored name; file exists under `storage/uploads` | Pass |
| TC-38 | Download that attachment as its owner | File served as attachment | HTTP 200, `Content-Type: application/pdf`, `Content-Disposition: attachment; filename="attach_test.pdf"` | Pass |
| TC-39 | Change mark weights so they total 105 | Rejected | “The four mark weights must add up to exactly 100 (currently 105.00).”; values unchanged | Pass |
| TC-40 | Change weights to 19/40/20/21 (total 100) | Accepted and audited | Two settings updated; audit diff page showed `weight_report` 20 → 21 and `weight_logbook` 20 → 19; reverted afterwards | Pass |
| TC-41 | Process the mail queue from the admin screen | Queued rows sent in log mode | Queue rows `sent` with `attempts=1`; messages appended to `storage/logs/mail.log` | Pass |
| TC-42 | Open the printable grade sheet and CSV export | Rendered/streamed without error | Grade sheet HTTP 200; CSV HTTP 200 with attachment headers and audit `export.grades` | Pass |

### Packaged build and presentation

| ID | Scenario | Expected | Observed | Result |
|---|---|---|---|---|
| TC-43 | Open the packaged build under an Apache subfolder, then exercise the interface at four viewport widths | Stylesheets and scripts load; the landing page, sign-in and every role dashboard render without horizontal overflow; interactive controls work | CSS and JS returned HTTP 200 with `text/css` and `application/javascript` (defect D9 fixed). Headless Chrome at 390x844, 768x1024, 1024x768 and 1440x900 showed no horizontal overflow on the landing page, sign-in, registration or any of the six role dashboards. Password reveal, strength meter, demo role fill, notification and user menus, mobile drawer and the five-step tour all responded; no new defect was raised | Pass |

### Account migration, onboarding and interface revision (same build, second pass)

| ID | Scenario | Expected | Observed | Result |
|---|---|---|---|---|
| TC-44 | Sign in with each of the three named member accounts | Each reaches its own dashboard | `sesayshadrachsaidu@gmail.com` (administrator), `bayohmusa001@gmail.com` (coordinator) and `fatmatabfc@gmail.com` (management) each returned HTTP 302 → `?r=dashboard` with the three named users shown on the account and audit screens | Pass |
| TC-45 | Register with `password1` (lowercase and digit only), then `Password` (no digit) | Both rejected with a policy that names all three character classes | Both submissions returned HTTP 200 with “Use at least 8 characters with an uppercase letter, a lowercase letter and a number.”; no user row was written. A password meeting all three classes was accepted | Pass |
| TC-46 | Open the registration form and the placement letter | Eight choices — Year 1–4 and Semester 1–8 — with the Limkokwing year model; the letter uses “Year” wording | Form rendered `Year 1`–`Year 4` (default `Year 3`) and `Semester 1`–`Semester 8` (default `Semester 5`); the `student` semesters column is `varchar(10)` so “Semester 8” fits; placement letter for placement 1 rendered “Year 3 / Semester 5”, with no “Level” label on any screen | Pass |
| TC-47 | Submit a rejected sign-in so the feedback alert appears; measure the alert and card at three widths | The alert stays a compact banner above the card instead of stretching down the column | Alert height 54 px at 390×844, 768×1024 and 1440×900; card sits directly below with the panel gap unchanged (defect D10 fixed) | Pass |
| TC-48 | Coordinator and mentor open a student document for review | Uploader sees that the file was opened, with reviewer and time | Downloading CV document 1 as the coordinator wrote `reviewed_by=2` and `reviewed_at`; mentor download of document 3 wrote `reviewed_by=3` and `16:47`. The placement page then labelled that report “Reviewed by Deborah Moiforay · 13 Nov 2026, 16:47” | Pass |
| TC-49 | Sign out, then use the browser Back button or re-request the dashboard | No cached dashboard is shown; the request returns to sign-in | Dashboard responses carried `Cache-Control: no-store, no-cache, must-revalidate` and `Pragma: no-cache`; the logout POST returned HTTP 302 with `Clear-Site-Data: "cache"`; the next dashboard request returned HTTP 302 → `?r=login` (defect D11 fixed) | Pass |
| TC-50 | Complete registration as a new student | The new user lands in their workspace and is offered a tour once | Auto-signed-in to `?r=dashboard`; welcome dialog “Your workspace is ready” shown; “Show me around” opened the existing five-step tour at “STEP 1 OF 5”; the prompt was cleared on the next page load; at 390×844 the dialog fitted without horizontal overflow | Pass |

### Interface revision: mobile layout, feedback, tracking and guidance (14 November 2026)

| ID | Scenario | Expected | Observed | Result |
|---|---|---|---|---|
| TC-51 | Open every role page at 427×952, 390×844, 360×800 and 320×700 | No horizontal page overflow at any width; the header keeps one height; the menu toggle stays visible | A CDP audit measured `document.scrollWidth == window.innerWidth` on every screen tested — all six dashboards, student pages, mentor, supervisor, coordinator and management pages, the administrator screens and the public auth pages; topbar height was 64 px at every width; `.nav-toggle` stayed visible. Seven-column tables (evaluations, grade breakdown, results, report files, applications, audit changes) were converted to the stacked `table--stack` pattern with `data-label` captions | Pass |
| TC-52 | Inspect the weekly bar chart, district share bars and site-visit bars inside stacked tables at 320 px | Bars keep a visible length and never collapse to zero | Each `.chart-track` measured 194 px with a 189 px fill inside the stacked cell (defect D13 fixed) | Pass |
| TC-53 | Reply to a comment as the student, mentor and host supervisor, including a reply to a reply | The reply is stored with `parent_id`, appears under the parent, and the author is taken to it | Student comment 10, mentor comment 11 and nested mentor reply 12 (`parent_id=10`) were written; the redirect carried `#comment-10/11/12`; each page rendered matching `id="comment-N"` anchors; students, mentors and supervisors each notified the other two parties. The supervisor had no reply form before this pass (defect D14 fixed) | Pass |
| TC-54 | Click **Reply** in a thread, then **Cancel** | A banner names the author, the hidden `parent_id` is set, and Cancel clears both | Banner read “Replying to Mohamed Alie Kamara — your comment will appear under theirs. Cancel”; `parent_id=10`; after Cancel the field was empty and the banner hidden | Pass |
| TC-55 | Follow a sidebar link and submit a form | A top loading bar and a button spinner appear immediately; the bar clears on arrival | Link and submit both set `.loadbar.is-active` and `body.is-navigating`; the submit button gained `.is-loading` and `aria-busy="true"`. Links inside the notification dropdown were missed until the listener moved to the capture phase (defect D16 fixed). CSS/JS stay external (no inline script) under the CSP | Pass |
| TC-56 | Open the student dashboard tracker | One card shows placement, logbook, evaluations, visit, report and publication steps with a percentage | “Internship progress — 91% complete”, six tracker steps with done/in-progress/upcoming states and captions; rendered at 294 px inside a 360 px viewport | Pass |
| TC-57 | Submit feedback from the floating **Send feedback** control, then inspect it as the administrator | Rating (1–5), thoughts and suggestions are stored and listed | The dialog opened from the floating button, the form was replaced by the thank-you panel and a toast, and Escape closed the layer; JSON response `{"ok":true,…}` wrote feedback rows 6, 7 and 8 (ratings 5, 4 and 5, page paths recorded); an invalid rating 9 returned HTTP 422; a form-encoded post without JavaScript redirected to the dashboard with a success message; `?r=admin/feedback` listed every row | Pass |
| TC-58 | Replace the profile photo as the student | The new image replaces the old one and is served back | `profile/avatar` accepted a 70-byte PNG (HTTP 302), `storage/uploads/avatars/6.jpg` was removed, `6.png` stored, and `?r=avatar` returned `200 image/png` with identical bytes | Pass |
| TC-59 | Open the sign-in form | One password control only: the reveal eye icon, with no “Save password” checkbox | Rendered at 320–427 px with a single `Password` field and its eye button; no checkbox on the page | Pass |
| TC-60 | Download each seeded document as its owner | Every download link serves the file named in the seed | After adding the three sample PDFs, the student CV download returned `200`, `%PDF-1.4`, 1,402 bytes and extracted cleanly with `pdftotext`; report downloads are served the same way (defect D15 fixed) | Pass |
| TC-61 | Crawl every GET route as all six roles | No HTTP 500 and no PHP warning, notice or deprecation in any response | 51 GET routes × 6 roles (306 requests) returned only expected 200/302/403/404 statuses; zero 500s and zero PHP diagnostics. Re-run after the November interface revision with 67 role-scoped GET requests plus the log book template and student letter: only expected statuses again, zero 500s and zero diagnostics | Pass |

### Programme list, log book, profile, ranked list and chart labels (14 November 2026, third pass)

| ID | Scenario | Expected | Observed | Result |
|---|---|---|---|---|
| TC-62 | Open student registration and the administrator’s create-account form | The official Limkokwing programmes appear, grouped by faculty, and a new selection saves | Both forms rendered five `optgroup`s (Communication, Media & Broadcasting; Architecture & the Built Environment; Business Management & Globalisation; Information & Communication Technology; Design Innovation & Multimedia Creativity) holding the 20 official programmes plus the “Choose a programme” placeholder; the seeded student programmes in `digital_leap.sql` use the same names. An existing record outside the list still shows as “(current record)” in the administrator form (verified with user 6) | Pass |
| TC-63 | Open and download the internship log book template as a student, then as an unauthenticated visitor | A 16-page A4 PDF opens inline; `&download=1` sends it as an attachment; signed-out access is refused | `?r=logbook/template` returned HTTP 200, `application/pdf`, `Content-Disposition: inline; filename="Limkokwing_Internship_Log_Book.pdf"`, 267,531 bytes and 16 pages (page-object count). `&download=1` returned `attachment`. Signed out, the same URL returned HTTP 302 → `?r=login`. The **Log book template** link is present on the student logbook, supervisor queue, mentor logbook and coordinator placements pages. The template was rebuilt in the guide book's style in the fourth pass (TC-70) | Pass |
| TC-64 | Choose a profile photo without saving, then save it and remove it | The header photo updates immediately, the saved file is served back, and removal restores the initials | In headless Chrome the chosen 42 KB PNG produced `djangogirls-logo.png · 42 KB · ready to save`, the header `<img>` loaded from a `blob:` URL at its natural size, and the small preview loaded too. After Save, `?r=avatar` returned the new file; after **Remove photo**, `storage/uploads/avatars/6.*` was deleted and the header and topbar showed the initials. The first attempt failed because the CSP blocked `blob:` (defect D18) and the browser reused an old script (defect D19) | Pass |
| TC-65 | Open the ranked preferences page at 1440×900 and 390×844 | Every rank is a readable card with aligned rank number, organisation, metadata, status and motivation; controls do not overflow | At 1440 px `document.documentElement.scrollWidth` was 1403 inside a 1418 px viewport, the list’s `scrollWidth` equalled its `clientWidth`, and each card measured 473 px. At 390 px no horizontal overflow occurred and the cards stacked; **Move up** is disabled on rank 1 and **Move down** on rank 5, and the lock note replaces the controls when a placement exists. Screenshots `prefs-desktop.png` / `prefs-mobile.png` | Pass |
| TC-66 | Inspect the management KPIs and administrator feedback charts at 1440×900 and 390×844 | Every bar is labelled with a count and share; zero weeks still appear; a legend names the series | The weekly chart rendered twelve weeks including zero entries, with “7 of 12 weeks have entries”, “Peak 5 in one week · 38 in total”, count and share on each bar and a legend. Grade distribution, organisation share, site visits and feedback ratings each showed `count` and `share%` with a legend; with no published marks the grade panel read “No marks have been published yet”. Labels remained readable at 390 px. Screenshots `kpis-desktop.png` / `kpis-mobile.png` | Pass |
| TC-67 | Lint and reload the application after the interface revision | No syntax errors; changed assets are served fresh; no route regressions | `php -l` reported no syntax errors in any file under `app/`; `app.css?v=1794687603` and `app.js?v=1794688094` returned HTTP 200 from the versioned URLs; the six-role crawl in TC-61 found no 500s. The 404 on `?r=avatar` for accounts without a photo is the intended response | Pass |

### Career Fair 2026, guide book styling and final lint (14 November 2026, fourth pass)

| ID | Scenario | Expected | Observed | Result |
|---|---|---|---|---|
| TC-68 | Open `?r=student/career-fair` as the seeded student, then upload a PDF | The page shows the 11 November 2026 deadline, the submission status, the official template links and an upload form; the upload is stored and marked late after the deadline | With no report the page rendered “Status Not submitted” and “Timing Deadline has passed”. Uploading a 333,899-byte PDF returned the flash “Career Fair report uploaded. It is after the deadline, so it is recorded as a late submission.”; the file was listed with “Late” and “Not yet opened”. A `document` row was written with `doc_type=career_fair_report` against placement 1. The demo seed now ships one on-time report for Mohamed (`2026-11-10 15:30`), so a fresh import opens with “Submitted / On time” | Pass |
| TC-69 | Open the Career Fair template inline and as a download | A PDF opens with the correct name in both modes | `?r=career-fair/template` returned HTTP 200, `application/pdf`, 333,899 bytes; `&download=1` returned `attachment; filename="Career_Fair_2026_Student_Participation_Report.pdf"` with the same byte count | Pass |
| TC-70 | Rebuild the internship log book template in the guide book's style without editing the guide book | A 16-page A4 PDF using the guide book fonts and palette; the source guide book file is unchanged | The generated PDF is 267,531 bytes with 16 pages; embedded fonts are `ArialMT`, `Arial-BoldMT` and `Georgia-Bold`; the slate/navy palette replaces the earlier charcoal/gold. Headless Chrome rendered the cover, guidelines, week, summary and structure pages at 794×1123 (A4 at 96 dpi) with no clipped content. SHA-256 of `Internship_Guidebook_LEAP_Clean_Layout.pdf` was identical before and after the work | Pass |
| TC-71 | Lint every changed file and resolve all routes | No syntax errors; every route points at a real method | `php -l` passed for `StudentController.php`, `MentorController.php`, `DownloadController.php`, `helpers.php`, `routes.php`, `layout.php`, `student/career_fair.php` and `mentor/reports.php`; the route script resolved all 101 entries | Pass |

### DIVA assistant and placement messaging (15 November 2026, fifth pass)

| ID | Scenario | Expected | Observed | Result |
|---|---|---|---|---|
| TC-72 | Open `?r=diva` as the student and follow a suggestion chip | The assistant page renders its rules, the ask form, role-specific suggestions and the read-only note; nothing overflows at phone width | The page opened with “Digital Internship Virtual Assistant…”, the DIVA mark, three rule bullets, the 500-character box and five student suggestions. At a 279 px viewport `document.documentElement.scrollWidth` equalled `clientWidth` (no horizontal overflow); the page also reported no overflowing child that lacks its own scroll area | Pass |
| TC-73 | Ask “When is my next logbook entry due?” through the form | An answer built from the signed-in student’s own logbook, the tier named on screen, a route link, and one audit row | The answer card read “Your logbook has 12 of 12 weeks started: 8 approved, 2 waiting with your mentor and 1 returned for rework. Week 10 needs your attention first. Every week already has an entry; the placement ends on 20 Nov 2026.” with the badge `badge-muted :: Rules and live records` and the button “Open my logbook”. No guidebook pages were attached, because the answer cites none | Pass |
| TC-74 | Ask a guidebook-only question, “How do I record a site visit?” | Ranked guidebook passages with page numbers and snippets, tier “Guidebook search” | Answer began “From the internship guidebook, page 7:” and quoted the overseeing-of-interns passage; sources listed pages 7, 20 and 8 with a snippet each; badge `badge-info :: Guidebook search` | Pass |
| TC-75 | Open `?r=diva/source&page=24`, page 1 and an out-of-range `page=99` | The page text renders without `[[page:N]]` markers, the pager stops at both ends, and an unknown page is refused | Page 24 showed 1,622 characters including “Practical Internship”, no `[[page:`, title “Guidebook page 24”, links to pages 23 and 25. Page 1 showed only “Page 2”. `page=99` redirected to `?r=diva` with the flash “That page is not in the guidebook. It has 25 pages.” | Pass |
| TC-76 | Submit the ask form with no question (form validation bypassed) | The server refuses and explains | `?r=diva` returned with the alert “Type a question first.” and `alerts: 1` in the DOM | Pass |
| TC-77 | Upload the 366-word test report as `.docx` on `?r=diva/report-check` | Eight criteria judged with the printed weights, evidence quoted from the matching section, presentation checks, and an explicit statement that no mark is given | Result header “366 words · read from a .docx file · this is advice, not a mark”. Introduction, Organisation, Position, Difficulties, Work Environment and Evaluation read “Found” with a different evidence quote each; Conclusion (“…was valuable and I recommend it…”) and Layout read “Thin”. Weights shown 5/15/20/10/10/20/5/15, each linked to its guidebook page. Seven presentation checks ran; Length and “Page numbers and justification” are labelled “Check yourself” because DIVA cannot see them | Pass |
| TC-78 | Upload a `.pdf` to the report checker | Refused with an honest explanation instead of a silent failure | POST returned 302 → GET `?r=diva/report-check`; the alert read “DIVA cannot read PDF text offline in this build. Upload the same report as .docx, or paste the text into the box.” | Pass |
| TC-79 | Paste a 40-word draft instead of uploading | The same eight-criterion check runs on pasted text and names the sections that are absent | Header “40 words · read from pasted text · this is advice, not a mark”; Introduction and Conclusion judged “Thin”; the other six rows read “Not found — Nothing matched this criterion.”; audit row `{"format":"text","words":40,"criteria":{"present":0,"thin":2,"missing":6},"layout_checks":7}` | Pass |
| TC-80 | Inspect the audit rows written by DIVA | The intent, tier and cited pages are recorded; the question and the report text are not | `diva.query` example: `{"intent":"none","tier":"retrieval","chars":29,"question_hash":"78027882ae2cf849","pages":[7,20,8]}`. `diva.report_check` example: `{"format":"docx","words":366,"criteria":{"present":6,"thin":2,"missing":0},"layout_checks":7}`. No body text or file name in either row | Pass |
| TC-81 | Run the 20-question evaluation set across the six roles | Each question reports the intent, the answering tier and the cited pages; totals are stated | `php bin/diva-eval.php --answers` (saved as `docs/diva-eval-results.txt`): intent matched 20/20, tier matched 20/20, tiers rules 18 · retrieval 2 · local 0, “Local model: disabled”. Contexts: student user 6, supervisor 5, mentor 3, coordinator 2, management 4, admin 1 | Pass |
| TC-82 | Tab from the question box on `?r=diva` | Focus moves in DOM order and is visible | Tab moved from `#question` to the “Ask DIVA” button, whose computed outline was `solid 2.67px rgb(184, 145, 47)` (the gold focus token) | Pass |
| TC-83 | Open `?r=messages` and then the thread with the mentor | Inbox lists the student’s threads newest first; opening a thread shows the messages, quotes and reactions and clears the unread marker | Inbox listed “Internship report question” (Musa Bayoh) and “Placement supervision” (Deborah Moiforay); the start-a-conversation block offered only the mentor and the coordinator. Thread 1 rendered the system message, four seeded messages, two quoted replies and the seeded 👍 reaction. `conversation_member.last_read_message_id` for user 6 on conversation 1 moved from 4 to 21 on open | Pass |
| TC-84 | Send a message, then edit it inside the 15-minute window | The message appears anchored at its own id, the author can edit it, and the edit is labelled and audited | Sending returned `…&id=1#m-18`; the bubble appeared with Edit/Delete controls and no error. The edit returned the flash “Message updated.” and the bubble gained the “edited” marker; `audit_log` recorded `message.sent` and `message.edited` for message 18, and the row kept `edited_at = 2026-11-15 01:02:16` | Pass |
| TC-85 | Delete the reply that quoted the mentor | A visible marker replaces the text, the row is kept and the thread keeps its shape | The bubble text became “This message was deleted” with the flash “Message deleted. Replies keep their place in the thread.”; the bubble count stayed at 6. In the database message 19 kept `reply_to = 5`, its body and a `deleted_at` timestamp | Pass |
| TC-86 | Post an edit for a message sent two days earlier | The server refuses edits after 15 minutes | POST `messages/edit` for message 2 returned an opaque redirect; reloading the thread showed “Messages can be edited for 15 minutes after sending.” and message 2 was unchanged | Pass |
| TC-87 | Send the same body twice with one `client_token` | Only one message is stored | Two POSTs with token `a1b2c3d4e5f6071819202122232425ab` both returned 200; `SELECT COUNT(*) … WHERE client_token = …` returned 1, message 21 | Pass |
| TC-88 | As the student, try to open a thread with another student | Refused and audited, because students may only message the people on their placement | POST `messages/start` with `placement_id=1, peer_user_id=7` returned HTTP 403 and wrote a `message.denied` audit row (entity `conversation`) | Pass |
| TC-89 | Poll the open thread and store emoji | The poll endpoint returns JSON; four-byte characters survive the round trip | `?r=messages/poll&id=1&after=18` returned HTTP 200 JSON with three messages (the first read “This message was deleted”) and `unread: 0`. A message containing 👍🏿 ✅ — was stored with bytes `F09F918D F09F8FBF E29C85 E28094`, so utf8mb4 is intact | Pass |
| TC-90 | Reach the messaging and DIVA pages at 279 px | No horizontal page overflow; the edit box stays usable | Inbox and thread both reported `document.documentElement.scrollWidth === clientWidth` at 279 px; an opened edit box measured 133 px wide with no overflow | Pass |
| TC-91 | Lint every PHP file, resolve every route, and re-run the email updates | No syntax errors; 114 routes resolve; the two contact-email updates are idempotent | `php -l` passed for all 101 PHP files under `app/`, `bin/`, `public/` plus `index.php` and `router.php`; the route check resolved 114/114 entries. Running both `UPDATE` statements against the live database changed 0 rows, and `SELECT … GROUP BY email HAVING COUNT(*) > 1` returned nothing | Pass |

### 200-mark assessment model, self evaluation and seed integrity (16 November 2026, sixth pass)

The assessment was re-based on the lecturer's model: Practical Internship out of 100 (logbook 50, Form D supervisor evaluation 40, student self evaluation 10), Internship Report out of 75 and Career Fair out of 25, giving a final mark out of 200 (reported as a percentage). The academic mentor evaluation is advisory only.

| ID | Scenario | Expected | Observed | Result |
|---|---|---|---|---|
| TC-92 | Import `database/digital_leap.sql` into a throwaway schema (`dl_seedcheck`, names substituted) | The file imports without error and the new columns, self evaluations, career fair scores and weights are present | Import exited 0 with no output. `placement.career_fair_score` read 82.00 / 76.00 / NULL / 85.00 / NULL; `evaluation` ids 5, 6 and 7 were `self` evaluations scored 80.00 with four criterion rows (14–17) each; `mark` held only the published row 3 with `self_pct 80.00, practical_pct 72.20, career_fair_pct 85.00, weighted_total 72.98`; settings read logbook 50, supervisor 40, self 10, mentor 0, report 75, career fair 25. The schema was dropped afterwards | Pass |
| TC-93 | Open `?r=admin/settings` as the administrator | The practical split is shown as a 100-mark total and the advisory mentor weight is not editable | The page read “Practical Internship split: 100.00%” with the note that logbook, Form D and self evaluation must add to 100 and that report/Career Fair carry 75 and 25 of 200. Fields rendered logbook 50, supervisor 40, self 10, report 75, career fair 25; no `weight_mentor` field was present | Pass |
| TC-94 | Submit the settings form with logbook 60 (practical weights total 110) | Refused, nothing written | Fetch POST of the real form returned the alert “The practical weights (logbook, Form D, self evaluation) must add up to exactly 100 (currently 110.00).”; `SELECT setting_value … LIKE 'weight%'` still read 50/40/0/75/10/25 and no `setting.updated` audit row was written | Pass |
| TC-95 | Compute the mark for placement 2 (logbook 0/12 weeks approved, Form D 72.50 submitted, self evaluation 80.00 submitted, report 78.00 assessed, career fair 76.00) | Practical 37.00, final 114.50/200 = 57.25%, grade D, status draft | Grade view showed logbook 0.00, Form D 72.50, self 80.00 → practical 37.00; report 58.50 of 75; career fair 19.00 of 25; “Final mark 114.50 / 200” and 57.25%. `mark` row 2 read `practical_pct 37.00, weighted_total 57.25, grade D, status draft`; `audit_log` row 226 was `mark.computed` | Pass |
| TC-96 | Record a career fair score of 76 for placement 2 from the grade view | Stored on the placement, audited, with a flash naming the 25 marks | The form returned the flash “Career fair score recorded. It counts 25 of the 200 final marks.”; `placement.career_fair_score` stayed 76.00 and `audit_log` row 238 was `placement.career_fair_scored` (before and after both 76.00) | Pass |
| TC-97 | Open the printable grade sheet for the 2026/2027 year | Thirteen columns, the 200-mark weighting note and the final percentage per student | `?r=coordinator/grades/sheet` rendered the note “Practical Internship is 100 marks … report 75 … career fair 25 … out of 200” and a table whose header ran #, reg no, student, organisation, Logbook, Form D, Self, Practical, Report, Career Fair, Final %, Grade, Status. Placement 2 showed 0.00 / 72.50 / 80.00 / 37.00 / 78.00 / 76.00 / 57.25 / D | Pass |
| TC-98 | Open `?r=student/results` for the published 2025/2026 placement | The published breakdown adds to the stored 72.98 and matches the grade | Rows read Practical·Logbook 74.00 × 0.5 = 37.00, Practical·Form D 68.00 × 0.4 = 27.20, Practical·Self 80.00 × 0.1 = 8.00, Practical subtotal 72.20 of 100, Internship Report 72.00 of 75, Career Fair 85.00 of 25; “Final mark 147.45 / 200”, 72.98%, grade B, published 15 Dec 2025 | Pass |
| TC-99 | Ask DIVA “How is the logbook weighted?” as a student | The answer states the 200-mark model and cites the guidebook page | The answer titled the final mark out of 200, the practical split 50/40/10, the report at 75 and Career Fair at 25, noted the mentor evaluation carries no marks, and offered sources page 24, “Open my logbook” and “Open report check” | Pass |
| TC-100 | Open `?r=student/self-evaluation` when the student's evaluation is already submitted | A read-only copy of the submitted scores, not an editable form | The page listed the four self-evaluation criteria with maximum and weight, the submitted score of 80.00, the overall reflection and no save buttons | Pass |
| TC-101 | Re-run the DIVA evaluation set after the weighting answers changed | Every question reports its intent, tier and sources | `php bin/diva-eval.php --answers` (saved as `docs/diva-eval-results.txt`): intent matched 26/26, tier matched 26/26, tiers rules 23 · retrieval 3 · local 0 | Pass |
| TC-102 | Lint every file changed for the new model | No syntax errors | `php -l` passed for `StudentController.php`, `CoordinatorController.php`, `AdminController.php`, `ExportController.php`, `ExportPdfController.php`, `DivaFacts.php`, `routes.php`, `helpers.php`, `layout.php`, the new `student/self_evaluation.php` and the edited grade, results, grade-sheet, settings and letter views | Pass |

## DIVA conversations and comment layout — latest verification (16 November 2026)

These checks apply to the redesigned interface and persistent conversations. They do not replace the historical baseline or establish a full cross-role regression pass. No existing chats or comments were deleted. Only two new test questions were submitted in chat 2.

| ID | Scenario | Observed evidence | Result |
|---|---|---|---|
| TC-103 | Open the redesigned DIVA welcome state and inspect the logo | Running Apache page rendered the history sidebar, prompt cards and compact transparent brain mark. Screenshot `docs/screenshots/diva-welcome.png` was captured and reviewed; logo sizes are 40/64/30 px for heading/welcome/avatar | Pass |
| TC-104 | Ask a greeting and an unrelated question in one conversation | Chat 2 saved “Hello!” and “What is the capital of France?” with a greeting and refusal. Both question/answer pairs appeared in the browser; Enter submitted the second question | Pass |
| TC-105 | Check persistence independently of the browser session | Navigation retained the conversation. Read-only queries after sign-out still found both turns; `turn_count` matched actual rows. Controller hydration reconstructed the first question and its answer-basis array. This is not a sign-in/reopen UI test | Pass |
| TC-106 | Whitespace, keyboard handling, count and history search | Whitespace rejected with “Type a question first.”; Shift+Enter was not intercepted, count reset to 0, and a nonmatching search displayed the empty-result state | Pass |
| TC-107 | Open a question via GET | The URL question prefilled the composer without saving a turn | Pass |
| TC-108 | Reject inaccessible conversations | Browser GET and POST for chat 99999999 returned 403. Read-only service check allowed student user 6 to look up chat 2 and returned null for user 5. Cross-account browser sign-in was not performed | Pass |
| TC-109 | Bypass client length validation with 501 characters | Server returned “Keep your question within 500 characters.” and did not save a turn | Pass |
| TC-110 | Submit without a CSRF token | Browser POST returned HTTP 419 | Pass |
| TC-111 | Exercise student logbook Reply/Cancel without writing a comment | Week 11 showed five comments and two quoted parents. Reply selected comment 4, set `parent_id=4`, displayed the banner and highlighted one bubble. Cancel cleared the field and banner | Pass |
| TC-112 | Check PHP and JavaScript syntax | Changed controller/view/routes PHP lints and `node --check public/assets/js/app.js` passed. DIVA service/evaluator lints passed separately | Pass |
| TC-113 | Run the corrected expanded DIVA evaluator | Final `--answers` run exited 0 and was saved to `docs/diva-eval-results.txt`: intent, tier, identity and content/carried checks each passed 69/69 (64 rules, 5 retrieval). Generated history was round-tripped through the controller's hydration path, preserving resolved topics and recorded basis. All DB queries ran in a read-only transaction; local model disabled | Pass |
| TC-114 | Exercise supervisor and mentor comment interfaces | Browser permission guard blocked supervisor sign-in. No workaround login or comment POST was attempted | Not run |
| TC-115 | Confirm and cancel archiving in the browser | Archive route and ownership checks were inspected; the confirmation flow was not executed. No conversation was archived | Not run |
| TC-116 | Sign in again and reopen saved history | Rows remained after sign-out, but a new authenticated browser session was not established | Not run |
| TC-117 | Recheck all redesigned screens across the full responsive matrix | No page overflow was measured at the available 279 px viewport. The full desktop/tablet/phone matrix was not repeated; later screenshots failed because the browser surface was hidden | Partial |

Browser console inspection found the expected 403/419 negative-test resource errors, not JavaScript exceptions. The final follow-up engine changes were evaluated by CLI; they still need a complete browser conversation pass.

## DIVA self-knowledge, filters, guide and narrow-width review (19 November 2026)

Run on the working Apache deployment against the live demo database. Sign-ins used the one-click demo buttons in the browser (student, mentor, coordinator, administrator and management). The supervisor demo role was **not** retried, because sign-in for that role remains blocked by the browser permission guard from the earlier pass; no alternative route or workaround was attempted. No records were created or deleted by this addendum except the DIVA questions named below, which are saved to the student's own chat history by design.

| ID | Scenario | Observed evidence | Result |
|---|---|---|---|
| TC-118 | Ask DIVA what it is | Question “What is DIVA?” in chat 16 answered with its own role, the three answer sources and its limits (it cannot approve work, publish a mark or send messages), and explained that chats are saved per account. Badge named the rules tier; the turn survived a reload | Pass |
| TC-119 | Ask an off-topic question | “How can I get a job?” returned the insufficient-support refusal and quoted no unrelated guidebook page, where the earlier build attached page 21 to any weak match | Pass |
| TC-120 | Ask a guidebook question | “What are the objectives of LEAP?” opened with “Quoted from the internship guidebook, page 4:” and the objectives passage, with a second quotation from page 2. The quotation comes before the explanation | Pass |
| TC-121 | Re-run the expanded DIVA evaluation set | `php bin/diva-eval.php` passed intent, tier, identity and content checks 74/74 each (`rules=69`, `retrieval=5`), local model disabled, output reviewed and copied to `docs/diva-eval-results.txt` | Pass |
| TC-122 | Mentor student filters | Search “Mohamed” with status `active` returned one placement; a nonexistent name returned no matches; `org_id=999` returned no matches; `q=' OR 1=1 --` returned no matches (prepared statement); all responses HTTP 200 | Pass |
| TC-123 | Mentor and coordinator site-visit filters | Status `completed` plus search “Mohamed” returned one visit in both roles; the whole list returned 3 (mentor) and 4 (coordinator); a 2099 date range returned none; an invalid date was ignored and the unfiltered list rendered with no error | Pass |
| TC-124 | Mentor document list filters | The internship-report and Career Fair lists each returned 2 records unfiltered; `q=Mohamed` with `review=reviewed` returned 2; `NoSuchFile` with `review=unreviewed` returned none in both lists. The page states that opening a file is not an assessment | Pass |
| TC-125 | Coordinator application filters | Seven active students unfiltered; search “Mohamed” with CV `uploaded` returned one; a nonexistent name with CV `missing` returned none | Pass |
| TC-126 | Administrator audit filters | Action `login.success`, actor 6 and a 17 November date range returned 5 rows, all matching; an impossible action/user combination returned the empty-state row instead of an error | Pass |
| TC-127 | Administrator feedback filters | All six responses shown unfiltered; rating 5 returned 4 responses, every one displayed as 5/5; rating 1 with an unknown page returned none. The distribution chart above the list is labelled as covering all feedback | Pass |
| TC-128 | Filter markup at the narrowest available viewport | At 283 CSS px (below the 320 px target) the mentor, coordinator and administrator filter pages reported `documentElement.scrollWidth` 268 against `innerWidth` 283 with no overflowing leaf elements; each filter field measured 175 px and stacked vertically under its label. Student logbook and management KPIs were checked the same way | Partial — one width only; 768 px and 1440 px were not captured |
| TC-129 | Supervisor queue filters | Not executed: supervisor sign-in is blocked by the standing permission guard and was not retried. The query, status allow-list, week filter and GET form are lint-clean and reviewed but have no browser evidence | Not run |
| TC-130 | Lint every file changed by the filter and icon work | `php -l` reported no syntax error in the four controllers and ten views changed for filters, the queue filter view and `app/Views/layout.php` (the DIVA sidebar icon) | Pass |
| TC-131 | Check the user guide against its brief | `docs/Digital_LEAP_User_Guide.md` is 1,371 words in three sections (sign-in and roles, student/supervisor cycle, assessment and oversight), links only the screenshot that exists in `docs/screenshots/`, and marks the missing screenshots instead of inventing them | Pass |

The narrow-width pass used the automation viewport that was actually available (283 px). Desktop and tablet rendering of the new filter bars is supported by the shared `.filter-bar` rules (wrapping flex row with a 160 px field minimum, full-width fields below 560 px), but it was not captured at 768 px or 1440 px in this pass.

## Form D labelling and daily logbook records (19 November 2026)

The supervisor evaluation now names itself Form D wherever a user meets it, and the logbook follows the paper log book's structure: one row per day with date, tasks and hours, followed by the weekly summary and the technical skills, challenges, meeting and reflection sections. The schema change is additive: a new `logbook_day` table plus four nullable text columns on `logbook_entry`, applied to `database/digital_leap.sql` and to the live database through a migration file. The seed carries 54 day rows across weeks 1–11 whose per-week hour totals reproduce every existing `logbook_entry.hours` value.

| ID | Scenario | Observed evidence | Result |
|---|---|---|---|
| TC-132 | Form D naming | `app/helpers.php` menu label, the page `<h1>` and lead paragraph, the supervisor dashboard button and the DIVA guide titles all name Form D; the mentor screen is labelled **Mentor evaluation** to separate it from Form D | Pass |
| TC-133 | Schema and seed integrity | Live `logbook_entry` has `technical_skills`, `challenges`, `meeting_notes`, `reflection`; `logbook_day` exists with `uq_day_once (entry_id, work_date)` and the hours check; 54 rows match the weekly totals 37.5, 40, 38, 40, 39, 40, 38.5, 40, 32, 39.5 and 40 | Pass |
| TC-134 | Daily form renders inside the placement | Signed in as Mohamed Alie Kamara, `student/logbook/new&week=12` listed Mon 16 to Fri 20 November 2026 only, because the placement ends on 20 November; the earlier seven-row version marked the Saturday and Sunday inputs invalid and would have blocked submission | Pass — five rows, no invalid controls |
| TC-135 | Save a draft from the daily grid | Two day rows with 8 hours each and a reflection were saved; `logbook_entry` stored `hours` 16.0, `date_from` 16 November, `date_to` 17 November and the reflection, and `logbook_day` held the two rows with their tasks and hours | Pass |
| TC-136 | Read the saved week | The student entry page rendered the daily table (Mon/Tue, tasks, 8 and 8), a week total of 16, the summary, the Reflection card, attachments and the discussion; the mentor entry page for the same record rendered the same table, summary and sections | Pass |
| TC-137 | Lint the changed files | `php -l` reported no syntax error in the three controllers, `app/helpers.php`, the entry form, the shared logbook body and the three entry views | Pass |

The two day rows written in TC-135 remain on week 12 of the working database; the automated destructive-action guard refused the statement that would have restored the seed's empty draft, so the live demo now shows week 12 as a partly filled draft (16 hours) while a fresh import shows it empty. Re-importing `database/digital_leap.sql` restores the seeded state.

## Weekday sync, text wrapping and form naming (19 November 2026)

The Day column now follows the date the student enters, long values wrap inside their cards and table cells, and every screen that represents a guidebook form names that form. The wrap rules are shared (`overflow-wrap: break-word` on `body`, `min-width: 0` plus `overflow-wrap: anywhere` on `.stat-list .row strong`, `overflow-wrap: anywhere` on `.table td`). Superseded on 20 November: that `anywhere` on `.table td` and `.stat-list .row strong` is exactly what let a five-column table shave words down to one character per line — see TC-191.

| ID | Scenario | Observed evidence | Result |
|---|---|---|---|
| TC-138 | Day label follows an edited date | On `student/logbook/new&week=12` the first row read **Mon** with 2026-11-16; changing the date input to 2026-11-13 changed the Day cell to **Fri** without a reload. The handler in `public/assets/js/app.js` listens for both `change` and `input` | Pass |
| TC-139 | Long values wrap in the placement card | At a 279 px viewport, `student/placement` reported no element wider than its container (`main` overflow check returned zero rows); the mentor email `deborahjulietmoiforay@gmail.com` wrapped inside the Supervision card, and the page had no horizontal scroll (document 264 px against a 279 px viewport) | Pass |
| TC-140 | Overflow sweep across screens | Dashboard, logbook list, messages, results, report, coordinator grade sheet and grade view, mentor log book review and report assessment, admin audit log and settings showed no element wider than its box; the only flagged nodes were `.sr-only` labels and a text input whose value scrolls inside the control. The daily-record table keeps a 620 px minimum inside `.table-wrap` and scrolls inside that wrapper below 760 px | Pass |
| TC-141 | Every guidebook form is named | Sidebar and headings now read A2/A3/B (student placement and coordinator placements), C (student report, mentor report assessment), D with D1/D2 weeks (supervisor evaluation, queue and dashboard), E (logbook, self evaluation, results, grade sheets, settings weights). Checked in the student, coordinator and mentor sessions | Pass — supervisor pages linted but not opened, because that sign-in stays blocked (TC-129) |
| TC-142 | Lint the changed files | `php -l` reported no syntax error in `app/helpers.php`, `DashboardController`, the two dashboards, the log book queue/review views, the entry form and the entry views; `node --check` passed on `public/assets/js/app.js` | Pass |

## Defects found and fixed during the run

| ID | Defect | Fix |
|---|---|---|
| D1 | Printable placement letter used a `javascript:` back link, which the CSP blocks | Controller passes a real back URL; view renders it through `e()` |
| D2 | `coordinator/letter` route pointed at a non-existent controller method | Rewired to `ExportPdfController::letter` |
| D3 | Source, configuration and upload folders were downloadable under the built-in server | Added `router.php` that 404s `/app`, `/bin`, `/config`, `/storage`, `/database` and non-front-controller PHP |
| D4 | Supervisor approval notification read undefined `first_name`/`last_name` keys | `entryFor()` now joins `users` and selects the names |
| D5 | Student notification path emitted undefined-key warnings | `StudentController::student()` now joins `users` |
| D6 | Swapping preference ranks collided with `uq_app_rank` | Three-step swap through a temporary rank of 0, wrapped in the existing transaction |
| D7 | `must_change_password` was stored but never enforced | Session flag set in `Auth::establish()`, bootstrap redirect gate, cleared in `ProfileController::save()` |
| D8 | Mentors could open the supervisor queue and the coordinator’s all-visits page | Added explicit role gates (`Guard::requireRole`) to the supervisor, mentor, coordinator and student controllers; audited as `security.access_denied` |
| D9 | Installed under an Apache subfolder, every page loaded unstyled because `asset()` returned `/public/css/app.css` instead of `/public/assets/css/app.css`, so the CSS and JS requests returned 404 | `asset()` now prefixes `/public/assets/`; CSS and JS return 200 and the page renders with its full styling (TC-43) |
| D10 | On the sign-in and registration pages the feedback alert stretched down the whole right-hand column as a tall pale panel, pushing the card out of place | `.auth-panel` became a column flex container with `align-items: flex-end` and non-growing children, so the alert and card keep their natural heights; measured at 54 px at all three widths (TC-47) |
| D11 | After signing out, the browser Back button could redisplay the cached dashboard until the session was checked | Added `Cache-Control: no-store` and `Pragma: no-cache` to every dynamic response, `Clear-Site-Data: "cache"` on logout, and a `pageshow` reload for restored pages; a post-logout dashboard request now returns 302 to sign-in (TC-49) |
| D12 | At phone widths seven-column tables clipped their last columns and forced the page sideways (evaluation sheet, grade breakdown, results, report files, ranked applications, audit changes) | Converted those tables to the stacked `.table--stack` pattern with `data-label` captions, `sr-only` field labels and a `.table-total` summary row replacing the footers; `.table--fit` keeps genuinely small tables compact and scrolls wide matrices inside their own `.table-wrap` |
| D13 | Bar charts placed inside stacked table cells collapsed to zero width because the flex cell gave the track no basis | Added `.table--stack td .chart-track { flex: 1 1 90px; min-width: 70px; }`; tracks measured 194 px with a 189 px fill (TC-52) |
| D14 | The host supervisor could read a student thread but had no reply form, so one quarter of the intended three-way discussion was missing | Added `POST supervisor/entry/comment`, a controller method with parent validation, audit and notifications, and an always-visible “Add to the discussion” card using the shared reply banner (TC-53) |
| D15 | The seeded CV and both internship-report rows pointed at files that were never shipped, so every **Download** link on a fresh import returned 404 | Generated the three sample PDFs into `storage/uploads/`, aligned their `file_size` values in the seed, and updated the packaging note; downloads now return the stored file (TC-60) |
| D16 | Links inside the notification dropdown started no loading bar because the panel stops click propagation | Moved the navigation listener to the capture phase, so the bar starts before the panel handles the click; sidebar, card and dropdown links now all show it (TC-55) |
| D17 | Under Apache the CSRF rejection page returned HTTP 500 instead of 419 because `http_response_code(419)` is rewritten for the non-standard code | The gate now sends an explicit `HTTP/1.1 419 Authentication Timeout` status line, which both Apache and the PHP built-in server preserve; re-tested on both entry points (TC-02) |
| D18 | The profile photo preview never appeared: the chosen image was read into a `blob:` object URL, but the CSP allowed only `'self' data:` for images, so the preview `<img>` loaded nothing | Added `blob:` to `img-src` in both `app/bootstrap.php` and `.htaccess`; the preview now renders before saving and the CSP still blocks every external image source (TC-64) |
| D19 | After the preview fix, browsers kept running the previously cached `app.js`, so the page still behaved as before and the size hint still read `0.0 MB` | `asset()` now appends `?v=<filemtime>` to CSS and JS URLs, so a changed asset is fetched immediately; the CSS and JS returned HTTP 200 from their versioned URLs (TC-67) |
| D20 | The ranked preferences table squeezed the rank, organisation, motivation and actions into one row; text wrapped awkwardly on desktop and the controls were hard to associate with their row on a phone | Rebuilt the list as `.rank-item` cards with a rank number, a title/meta line, a status badge, a labelled “Why this organisation” block and an action row; verified at 1440 px and 390 px with no overflow (TC-65) |
| D21 | Every DIVA answer claimed to come from the local model, even with the local model disabled, because the early-return branch returned the string it stored in `localOutcome` and the caller treated it as a rewrite | Each early return now sets the outcome and returns `null`; the evaluator reports `rules=18, retrieval=2, local=0` (TC-81) |
| D22 | “How many placements are active?”, “How many marks are published?” and “How many accounts are there?” were answered by the wrong intent because `results` and `placement` were tested before `accounts` and `overview` | Reordered the intent table and re-ran the evaluation set; all 20 intents now match (TC-81) |
| D23 | “How is the logbook weighted?” returned the eight report criteria, because only the report weighting had an intent | Added `weighting.logbook`, answered from the Form E table on guidebook page 24 (40/10/50) and pointed at the report criteria separately (TC-81) |
| D24 | Live-record answers carried unrelated guidebook pages as if they were the source, for example a logbook answer citing pages 9 and 19 | `citedSources()` now attaches only the pages the answer itself names, and none when it names none (TC-73) |
| D25 | “pages 24–25” yielded only page 24 because the citation pattern had no `u` modifier and the three-byte en dash broke the character class | Added the `u` modifier; both pages are listed for the weighting answers (TC-81) |
| D26 | The report checker quoted the Introduction paragraph as evidence for every criterion it did not find, because a `.docx` heading and its paragraph formed separate blocks and the first match won | Headings are merged with the paragraph that follows them and first-line matches outrank body matches; each row now quotes its own section (TC-77) |
| D27 | The seed carried two draft `mark` rows with component values the breakdown cannot produce: placement 2 has no logbook entries yet the row claimed 82.00, and placement 1 has 8 of 12 approved weeks but claimed 78.00 | The seed now ships only the published 2025/2026 mark; the current-year placements have no mark until the coordinator computes one from complete components (TC-92) |

## Not covered by live execution

| Area | Reason | Mitigation |
|---|---|---|
| 20-minute idle timeout and 8-hour absolute timeout | Would require leaving a session idle for the full window during the test pass | Logic reviewed in `app/bootstrap.php`; idle and absolute values are settings seeded as 20 minutes / 8 hours; the 14 November runs did observe the idle gate expiring stale sessions and forcing a fresh sign-in |
| SMTP delivery | The offline build ships `mail_mode=log`; no external SMTP server is available in the lab | `Mailer` supports SMTP; queue processing and log delivery were tested end to end |
| Apache mod_rewrite `.htaccess` | Most cases ran on the PHP built-in server with `router.php`; the Apache deployment was used to re-confirm the CSRF rejection status (TC-02) | Both entry points share `index.php`; `INSTALL.txt` documents both launch methods |
| Destructive POST flows not re-run after 16 November (moderate/publish, allocation, account lockout) | They were exercised in the first pass; re-running them would publish marks for the sample students and overwrite the demo story | Compute was re-run in the sixth pass on placement 2, which left a draft mark (TC-95); moderation and publication were not repeated because the seeded published record already demonstrates them. The working database keeps whatever state those earlier runs left behind; re-importing the seed restores the intended demo state |
| Visual rendering of the log book template | No PDF rasteriser is installed, so the finished PDF could not be exported to images | The HTML source pages were captured in headless Chrome at 794×1123 (A4 at 96 dpi) and inspected page by page; page count (16), A4 `@page`, embedded fonts, text extraction and both inline/attachment delivery were verified. The PDF was generated from the same HTML by headless Chrome |
| Re-import of the seed after the programme update | Re-importing would reset the working demo database used for the other cases | The seed file was inspected and the programme strings match the five-faculty list; the forms were loaded and rendered correctly against the live database (TC-62). The drift note in `docs/VERIFY.md` states that an old live database keeps legacy programme text until it is re-imported |
| DIVA local-model tier | No model is installed on the test machine; the tier is disabled by default | The probe is bounded to `127.0.0.1`/`localhost` with a 400 ms timeout and falls back silently; the evaluator reports “Local model: disabled” and lists the tier as rules or retrieval (TC-81) |
| PDF text extraction in DIVA | PHP has no PDF reader in this build; the report checker refuses PDFs by design | The refusal message was exercised against a real `.pdf` upload (TC-78); the `.docx` and pasted-text paths were tested end to end (TC-77, TC-79) |
| Screenshots of the DIVA and messaging pages | The in-app browser reported no visible surface, so only structural and layout measurements were captured | Every claim in TC-72 to TC-90 is backed by DOM text, computed style, scrollWidth/clientWidth, HTTP status or a database row rather than an image |
| Messaging with the mentor and coordinator signed in | Changing the signed-in account would have abandoned the student session used by the other cases | Scope, unread counters and the coordinator’s unread total were verified through the database and the `message.denied` audit path (TC-83, TC-88); the service-level checks in `Messenger` are shared by all three roles |
| Polling over several intervals | The 8-second poll was exercised as a single request, not left running | `?r=messages/poll` returned the expected JSON for messages after a given id (TC-89); the JavaScript reuses that response shape |
| Submitting the student self evaluation through the form | The seeded self evaluation for the working student is already `submitted`, so the form renders read-only; resetting it to `draft` would have altered live demo data that is not backed by a re-import during the run | The read-only view was rendered and checked (TC-100); the save method mirrors the tested supervisor evaluation form and shares its validation, transaction and audit path. A fresh import leaves the form editable for placements whose evaluation is still a draft |
| Supervisor queue filters in the browser | Supervisor sign-in is blocked by the browser permission guard and was not retried (TC-129) | The controller query, status allow-list, week filter and the GET form were reviewed and linted (TC-130); the pattern is the same one exercised in the mentor, coordinator and administrator queues (TC-122 to TC-127) |
| Responsive capture at 768 px and 1440 px | The available automation viewport is 283 px wide and the wider rendering could not be captured in this pass (TC-128) | The new filter bars use the shared wrapping `.filter-bar` rules already used by the Users screen, with full-width fields below 560 px; the stacked-table behaviour at phone widths was exercised on the live pages |

## Reproducing the run

1. Import `database/digital_leap.sql` into a clean `digital_leap` database; the four sample PDFs ship in `digital-leap/storage/uploads/` and the log book and Career Fair templates in `digital-leap/storage/templates/`.
2. Copy `config/config.sample.php` to `config/config.php` and set the database credentials.
3. Start Apache/XAMPP, or run `php -S 127.0.0.1:8081 router.php` from the `digital-leap` folder.
4. Sign in with the demo accounts in `INSTALL.txt` and follow the role walkthrough in `docs/VERIFY.md`.
5. For the mobile check, open the browser device toolbar at 427×952, 390×844, 360×800 and 320×700; no page should scroll sideways and the menu button replaces the sidebar.

---

# Whole-system upgrade — 20 November 2026

Run-to-completion build: design system, installable offline shell, offline logbook drafts with sync, the DIVA brain, and a GitHub-ready repository. Every case added below states what was actually executed.

## Phase 0 — route and view inventory (read-only)

The router is a flat method-plus-route table in `digital-leap/app/routes.php` (91 entries when this inventory was taken; 122 after the later API, PWA and DIVA routes were added) guarded by `Leap\Core\Guard::requirePermission()`, which resolves the permission from `role_permissions` in the seeded matrix (roles 1 admin, 2 coordinator, 3 mentor, 4 supervisor, 5 student, 6 management). Every view was opened and its primary heading recorded. There are 55 page views plus the shared partials (`layout`, `logbook_body`, `comment_stream`, `notifications`, `tracker`) and three printable views (`pdf/letter`, `pdf/grade_sheet`, `management/print_report`).

Role keys used below: **Ad** administrator, **Co** coordinator, **Me** academic mentor, **Su** host supervisor, **St** student, **Mg** management/AQA. "Cached read" means the page is written to the per-user offline store only after that user opens it, and is read-only while offline. "Online only" means the screen cannot be used without the server.

| Route (`?r=`) | View | Permission | Roles | Offline behaviour |
|---|---|---|---|---|
| (none) | `auth/landing` | public | guest | Online only; offline fallback page |
| `login`, `register`, `forgot`, `reset`, `token/supervisor` | `auth/*` | public | guest | Online only |
| `logout` (POST) | — | auth | all | Online only |
| `dashboard` | `dashboard/*` (six) | dashboard.view | Ad Co Me Su St Mg | Cached read; shell opens offline |
| `profile` (GET/POST) | `profile` | profile.view / .edit | all | Cached read; edits online only |
| `profile/cv`, `profile/avatar`, `profile/avatar/remove` | — | profile.edit | all | Online only |
| `avatar` | image | profile.view | all | Cached on view |
| `feedback` (POST) | — | auth | all | Online only |
| `notifications`, `notifications/read`, `notifications/read-all` | `shared/notifications` | notification.view | all | Cached read; marking read online only |
| `download` | file stream | auth | all | Cached on open |
| `logbook/template`, `career-fair/template` | PDF stream | auth | all | Cached on open |
| `diva`, `diva/source`, `diva/report-check` (GET) | `diva/index`, `diva/source`, `diva/report_check` | diva.use | all six | Cached read; JS engine answers offline |
| `diva/ask`, `diva/archive`, `diva/report-check` (POST) | — | diva.use | all six | Online only (offline answers are composed on device) |
| `messages`, `messages/thread`, `messages/poll` | `messages/*` | message.use | St Me Co | Cached read; sending online only |
| `messages/start`, `send`, `edit`, `delete`, `react` | — | message.use | St Me Co | Online only |
| `student/apply`, `student/preferences` | `student/apply`, `student/preferences` | preference.view | St | Cached read; changes online only |
| `student/preferences/add`, `remove`, `move`, `submit` | — | preference.submit | St | Online only |
| `student/placement` | `student/placement` | placement.view.own | St | Cached read |
| `student/logbook` | `student/logbook` | logbook.view.own | St | Cached read |
| `student/logbook/new`, `student/logbook/view` | `student/entry_form`, `student/entry_view` | logbook.submit / view.own | St | Cached read; form editable offline as draft |
| `student/logbook/save` | — | logbook.submit | St | Offline draft via outbox; submit online only |
| `student/logbook/submit` | — | logbook.submit | St | Online only |
| `student/logbook/comment` | — | logbook.comment | St Me Co Su | Online only |
| `student/logbook/attach` | — | logbook.submit | St | Online only |
| `student/report`, `student/report/upload` | `student/report` | report.submit | St | Cached read; upload online only |
| `student/career-fair`, `.../upload` | `student/career_fair` | report.submit | St | Cached read; upload online only |
| `student/self-evaluation`, `.../save` | `student/self_evaluation` | evaluation.submit.self | St | Cached read; save online only |
| `student/results` | `student/results` | mark.view.own | St | Cached read |
| `student/letter` | `pdf/letter` | placement.view.own | St | Cached on open |
| `supervisor/queue` | `supervisor/queue` | logbook.review | Su | Cached read |
| `supervisor/entry` | `supervisor/entry` | logbook.review | Su | Cached read |
| `supervisor/entry/approve`, `.../return`, `.../comment` | — | logbook.review / comment | Su | Online only |
| `supervisor/evaluation`, `.../save` | `supervisor/evaluation` | evaluation.submit.supervisor | Su | Cached read; save online only |
| `mentor/students` | `mentor/students` | placement.view.own | Me | Cached read |
| `mentor/logbook`, `mentor/entry` | `mentor/logbook`, `mentor/entry` | logbook.review | Me | Cached read |
| `mentor/entry/comment` | — | logbook.comment | Me | Online only |
| `mentor/visits`, `mentor/visits/form` | `mentor/visits`, `mentor/visit_form` | visit.view / .manage | Me | Cached read |
| `mentor/visits/save`, `.../complete` | — | visit.manage | Me | Online only |
| `mentor/evaluation`, `.../save` | `mentor/evaluation` | evaluation.submit.mentor | Me | Cached read; save online only |
| `mentor/reports`, `.../assess` | `mentor/reports` | report.assess | Me Co | Cached read; assess online only |
| `coordinator/organisations`, `.../form` | `coordinator/organisations`, `org_form` | org.view / .manage | Co | Cached read |
| `coordinator/organisations/save` | — | org.manage | Co | Online only |
| `coordinator/applications`, `.../view` | `coordinator/applications`, `application_view` | placement.view.all | Co | Cached read |
| `coordinator/placements`, `.../view` | `coordinator/placements`, `placement_view` | placement.view.all | Co | Cached read |
| `coordinator/placements/allocate`, `.../save`, `.../status`, `.../type`, `.../approve` | `coordinator/allocate_form` | placement.allocate / .approve | Co | Online only |
| `coordinator/visits` | `coordinator/visits` | visit.view | Co Me | Cached read |
| `coordinator/grades`, `.../view` | `coordinator/grades`, `grade_view` | mark.compute | Co | Cached read |
| `coordinator/grades/compute`, `.../career-fair`, `.../moderate`, `.../publish` | — | mark.compute / .moderate / .publish | Co | Online only |
| `coordinator/grades/print` | `pdf/grade_sheet` | mark.compute | Co | Cached on open |
| `coordinator/letter` | `pdf/letter` | placement.view.all | Co | Cached on open |
| `management/kpis` | `management/kpis` | kpi.view | Mg | Cached read |
| `management/export` | CSV stream | export.data | Mg Co | Online only |
| `management/print` | `management/print_report` | kpi.view | Mg | Cached on open |
| `admin/users`, `.../form` | `admin/users`, `user_form` | user.manage | Ad | Cached read (if offline caching allows) |
| `admin/users/save`, `.../toggle`, `.../unlock`, `.../reset` | — | user.manage | Ad | Online only |
| `admin/roles` | `admin/roles` | role.view | Ad | Cached read |
| `admin/settings`, `.../save` | `admin/settings` | setting.manage | Ad | Cached read; save online only |
| `admin/audit`, `.../view` | `admin/audit`, `audit_view` | audit.view | Ad | Cached read |
| `admin/feedback` | `admin/feedback` | audit.view | Ad | Cached read |
| `admin/mail`, `.../resend`, `.../process` | `admin/mail` | mail.manage | Ad | Cached read; actions online only |

Empty states exist in every list view (`queue`, `audit`, `feedback`, `mail`, `visits`, `applications`, `placements`, `organisations`, `reports`, `messages`, `notifications`, `users`); error pages are `View::error(403/404/419/500)` rendered through the shared error view.

Decisions recorded from this inventory: (1) staff screens cache exactly like student screens but obey the administrator's offline-caching setting; (2) every POST that notifies, approves, returns, publishes, messages or evaluates is online only; (3) the three printable documents and both PDF templates are cached only after first open.

## Workstream A — design system verification

| Case | What was executed | Result |
|---|---|---|
| TC-135 | Read `public/assets/css/app.css` end to end: one `@layer` statement at line 18 declares the seven layers in order (`tokens, primitives, components, layout, sections, responsive, print`); every rule sits inside one of them; no duplicate selector is declared in two layers | Pass |
| TC-136 | Grepped every `@media` query. Remaining breakpoints are 1200, 992, 768, 560, 400 (one documented narrow-phone refinement) plus `pointer: coarse`, `prefers-reduced-motion` and `print` | Pass — superseded 1180/1020/900/480/450/350 rules removed |
| TC-137 | Checked the spacing scale: eight 4 px-based steps (`--space-1` … `--space-8`) and zero raw `margin: 13px`-style values added in this pass | Pass |
| TC-138 | Checked the type ramp: five fluid steps `--step--1` … `--step-3` built on `clamp()`, `.lead` used for every page intro, `font-variant-numeric: tabular-nums` on KPI values, tables and money | Pass |
| TC-139 | Checked surfaces: `--surface-page` ivory, `--surface-card` white, `--surface-inset` tint; every list, form and detail screen has one deliberate lead element (page header with eyebrow, title and action) | Pass |
| TC-140 | Checked tables: no `min-width` on any table, `.table--dense` and `.table--comfortable` modes, stacked rows on narrow widths repeat the column name through `data-label` | Pass |
| TC-141 | Checked accessibility rules in the file: `:focus-visible` ring on every interactive selector, 4.5:1 text pairs, 44 px coarse-pointer targets, reduced-motion block, status chips carry text as well as colour, every input has a visible `label` | Pass |
| TC-142 | Confirmed printable views render as documents: `@page { size: A4; margin: 14mm }`, letterhead, signature block, footer; screen chrome (top bar, sidebar, net pill, sync strip, install prompt) hidden in print | Pass |
| TC-143 | Found and fixed: the A4 sheets (`coordinator/grades/print`, `management/print`) were fixed at 210 mm / 297 mm, so they scrolled the whole page at 360 px (439 px) and 768 px (31 px). Added a `@media screen and (max-width: 992px)` rule making the sheet `calc(100vw - 24px)` wide with its own `overflow-x` container; printing is unaffected | Pass after fix |
| TC-144 | Ran the four-width route sweep (below) with the service worker blocked so measurements read server HTML | Pass — 0 overflow, 0 navigation failures across 62 routes × 4 widths |

### Route sweep — horizontal page scroll at 360 / 768 / 1024 / 1440 px

Every figure is `document.documentElement.scrollWidth − clientWidth` after the page settled. `0` means no page-level horizontal scroll. Tool: `sweep2.mjs` driving headless Chrome over CDP at 127.0.0.1:8081; each role signed in with its demo account in a fresh cookie jar, service worker blocked so the HTML is live.

| Role | Route | 360 | 768 | 1024 | 1440 |
|---|---|---|---|---|---|
| student | `dashboard` | 0 | 0 | 0 | 0 |
| student | `profile` | 0 | 0 | 0 | 0 |
| student | `notifications` | 0 | 0 | 0 | 0 |
| student | `student/apply` | 0 | 0 | 0 | 0 |
| student | `student/placement` | 0 | 0 | 0 | 0 |
| student | `student/preferences` | 0 | 0 | 0 | 0 |
| student | `student/logbook` | 0 | 0 | 0 | 0 |
| student | `student/logbook/new` | 0 | 0 | 0 | 0 |
| student | `student/report` | 0 | 0 | 0 | 0 |
| student | `student/career-fair` | 0 | 0 | 0 | 0 |
| student | `student/self-evaluation` | 0 | 0 | 0 | 0 |
| student | `student/results` | 0 | 0 | 0 | 0 |
| student | `diva` | 0 | 0 | 0 | 0 |
| student | `diva/report-check` | 0 | 0 | 0 | 0 |
| student | `messages` | 0 | 0 | 0 | 0 |
| supervisor | `dashboard` | 0 | 0 | 0 | 0 |
| supervisor | `profile` | 0 | 0 | 0 | 0 |
| supervisor | `supervisor/queue` | 0 | 0 | 0 | 0 |
| supervisor | `supervisor/evaluation` | 0 | 0 | 0 | 0 |
| supervisor | `diva` | 0 | 0 | 0 | 0 |
| supervisor | `messages` | 0 | 0 | 0 | 0 |
| supervisor | `notifications` | 0 | 0 | 0 | 0 |
| mentor | `dashboard` | 0 | 0 | 0 | 0 |
| mentor | `profile` | 0 | 0 | 0 | 0 |
| mentor | `mentor/students` | 0 | 0 | 0 | 0 |
| mentor | `mentor/logbook` | 0 | 0 | 0 | 0 |
| mentor | `mentor/visits` | 0 | 0 | 0 | 0 |
| mentor | `mentor/visits/form` | 0 | 0 | 0 | 0 |
| mentor | `mentor/evaluation` | 0 | 0 | 0 | 0 |
| mentor | `mentor/reports` | 0 | 0 | 0 | 0 |
| mentor | `diva` | 0 | 0 | 0 | 0 |
| mentor | `messages` | 0 | 0 | 0 | 0 |
| mentor | `notifications` | 0 | 0 | 0 | 0 |
| coordinator | `dashboard` | 0 | 0 | 0 | 0 |
| coordinator | `profile` | 0 | 0 | 0 | 0 |
| coordinator | `coordinator/applications` | 0 | 0 | 0 | 0 |
| coordinator | `coordinator/placements` | 0 | 0 | 0 | 0 |
| coordinator | `coordinator/placements/allocate` | 0 | 0 | 0 | 0 |
| coordinator | `coordinator/organisations` | 0 | 0 | 0 | 0 |
| coordinator | `coordinator/visits` | 0 | 0 | 0 | 0 |
| coordinator | `coordinator/grades` | 0 | 0 | 0 | 0 |
| coordinator | `coordinator/grades/print` | 0 | 0 | 0 | 0 |
| coordinator | `diva` | 0 | 0 | 0 | 0 |
| coordinator | `messages` | 0 | 0 | 0 | 0 |
| coordinator | `notifications` | 0 | 0 | 0 | 0 |
| admin | `dashboard` | 0 | 0 | 0 | 0 |
| admin | `profile` | 0 | 0 | 0 | 0 |
| admin | `admin/users` | 0 | 0 | 0 | 0 |
| admin | `admin/users/form` | 0 | 0 | 0 | 0 |
| admin | `admin/roles` | 0 | 0 | 0 | 0 |
| admin | `admin/settings` | 0 | 0 | 0 | 0 |
| admin | `admin/audit` | 0 | 0 | 0 | 0 |
| admin | `admin/feedback` | 0 | 0 | 0 | 0 |
| admin | `admin/mail` | 0 | 0 | 0 | 0 |
| admin | `diva` | 0 | 0 | 0 | 0 |
| admin | `notifications` | 0 | 0 | 0 | 0 |
| management | `dashboard` | 0 | 0 | 0 | 0 |
| management | `profile` | 0 | 0 | 0 | 0 |
| management | `management/kpis` | 0 | 0 | 0 | 0 |
| management | `management/print` | 0 | 0 | 0 | 0 |
| management | `diva` | 0 | 0 | 0 | 0 |
| management | `notifications` | 0 | 0 | 0 | 0 |

248 measurements across 62 routes. The detector ignores elements that sit off-screen by design (the skip link at `left: -999px`, the closed drawer) and elements inside a scroll container (`.table-wrap`, `.table--scroll`, `.logbook-day-table`, the print sheet below 992 px), because those scroll inside their own box rather than moving the page.

Screens at 360 px additionally have visually-verified screenshots in `docs/screenshots/` for the log book, apply form, DIVA and the grade sheet.

## Workstream B — installable offline shell

Two probes, both driving headless Chrome over CDP with a fresh profile per run. `pwa-test.mjs` runs against the normal dev server and covers install, caching, the indicator and sign-out. `pwa-offline-test.mjs` starts its own PHP server on port 8082, kills it mid-run so the network path is genuinely dead, then restarts it; that is the only way to test offline honestly, because Chrome's network emulation does not block requests that the service worker itself makes.

| Case | What was executed | Result |
|---|---|---|
| TC-145 | `link[rel=manifest]` fetched and parsed: `application/manifest+json`, `short_name` "Digital LEAP", 3 icons, scope `../` | Pass |
| TC-146 | `navigator.serviceWorker.ready` resolves: scope `http://127.0.0.1:8081/`, active state `activated`, `isSecureContext` true. Registration happens in `app.js` only and is skipped when the context is insecure | Pass |
| TC-147 | After sign-in and two page visits, the page is worker-controlled with `leap-shell-v1` holding 9 entries and `leap-runtime-v1` holding the visited pages | Pass |
| TC-148 | PHP server killed; an unvisited route (`student/results`) opens the offline document: title "Offline · Digital LEAP", heading "You are offline, and this page has not been opened on this device yet." Screenshot `docs/screenshots/pwa/offline-fallback-real-420.png` | Pass |
| TC-149 | PHP server still dead; a visited route (`student/logbook`) replays from the runtime cache with its real title and heading, and the top-bar pill reads "Offline". Screenshot `docs/screenshots/pwa/offline-cached-real-420.png` | Pass |
| TC-150 | With the server dead, `app.css` is answered from the shell cache: 121 539 bytes, `text/css` | Pass |
| TC-151 | Server restarted: dashboard loads live, pill returns to "Online", the page remains worker-controlled | Pass |
| TC-152 | Signing out deletes `leap-runtime-v1`. What remains is only the sign-in page, `offline.js`, the manifest and one landing image, all re-cached by the unauthenticated load; no student page survives | Pass |
| TC-153 | Bug found and fixed: the offline document's `assets/css/offline.css` resolved against the failing route (`/assets/…`), which was never cached, so the fallback rendered unstyled. `offline.css` is now in the shell precache and the worker answers any `*/assets/css/offline.css` request from it; the "Go to dashboard" link is now relative to the app root | Pass after fix |
| TC-154 | Bug found and fixed: the indicator read `navigator.onLine` only, so it claimed "Online" while the server was down. It now also probes the current URL with a `HEAD` request (6 s timeout) on load, on `online`, and when the tab becomes visible, and paints offline when either check fails | Pass after fix |
| TC-155 | Emulated offline (`Network.emulateNetworkConditions`): pill switches to "Offline" and back to "Online" on restore; recorded as a UI check only, since emulation does not stop worker fetches | Informational |

One operational note: the login POST returned HTTP 500 during this run because the local MariaDB service had stopped, not because of application code. Starting `mysqld` restored sign-in with no code change; the failure and recovery are recorded here so the log matches what was seen.

## Workstream C — offline drafts and sync

One end-to-end probe, `sync-test.mjs`. It starts its own PHP server on 8084, signs in as the seeded student, works on the week 12 draft, kills the server so the network is genuinely unavailable, queues changes, restarts the server, and checks both the browser and the database afterwards. Every write in the test went through the application: the form itself or `api/sync`. The demo entry was restored to its seeded text at the end through `api/sync`.

| Case | What was executed | Result |
|---|---|---|
| TC-156 | Typed into `activities` and dispatched `input`; after the 600 ms debounce, IndexedDB draft `logbook.12` held the new text and all five day rows | Pass |
| TC-157 | Reloaded the form with the server up: "Unsaved draft found … Restore it, or keep what the page shows" bar appeared with **Restore draft** and **Discard**; Restore put the typed text back into the textarea | Pass |
| TC-158 | Bug found and fixed: `initLogbookDraft` looked for `form[action*="logbook/save"]`, but `url()` encodes the route (`student%2Flogbook%2Fsave`), so the selector never matched and no draft or queue ever initialised in the real app. The view now marks the form with `data-draft-form` and the script matches that | Pass after fix |
| TC-159 | Server killed while `navigator.onLine` stayed true. The cached form opened, the reachability probe set `LeapServerReachable` false, and pressing **Save draft** queued the operation instead of posting into a dead connection. Outbox: 1 op, strip visible reading "1 change waiting to send — Week 12 logbook — Waiting", pill "Offline" | Pass |
| TC-160 | A second operation was queued, then `flush()` ran while the server was still down. Only the first op was attempted (`attempts: 1`, state `waiting`, next attempt about 0.5 s away at the observed moment, i.e. the 2 s backoff); the second stayed at `attempts: 0`, proving the queue is sequential | Pass |
| TC-161 | Server restarted and `online` dispatched: both operations applied, `pendingCount()` reached 0, the strip cleared, the pill returned to "Online", and exactly one batch toast appeared — "2 changes saved while offline have been sent." No page reload. Database: week 12 activities equal the second queued text, `sync_op` has 2 applied rows, `audit_log` has 2 `sync.applied` events | Pass |
| TC-162 | The first operation's payload was posted again with the same `op_id`. Response `200 {"status":"duplicate"}`, one `sync_op` row for that id, one logbook entry for the week, no second audit event | Pass |
| TC-163 | An operation with a too-short summary and no working days returned `422` with both validation messages, and left no `sync_op` row (the claim rolls back with the transaction) | Pass |
| TC-164 | Bug found and fixed: any `23000` error was treated as "another device won the race" and answered `200 duplicate`, which would have hidden a real conflict. It now re-reads `sync_op`; a genuine race still returns `duplicate`, while a week that already has an entry returns `409` with "Week 1 already has a log book entry. Open that week and edit it instead." | Pass after fix |
| TC-165 | Reconnect snapshot: the fresh server render differed from the page (8 142 chars against 7 791) but the check deliberately stayed silent because the draft bar was on screen — it never reloads or overwrites a form being edited | Pass |
| TC-166 | After every run, `SELECT COUNT(*) FROM sync_op WHERE status='processing'` is 0: claims are always resolved to `applied` or rolled back | Pass |

Screenshots: `docs/screenshots/pwa/sync-strip-queued-460.png` and `sync-strip-drained-460.png`.

Schema: `sync_op` (op UUID primary key, user, kind, payload hash, result entry and status, `processing`/`applied`) is part of `database/digital_leap.sql` and of `database/migrations/2026-11-20_sync_op.sql`, which was applied to the live database additively.

## Workstream D — DIVA answer contract, offline engine and evaluation

DIVA answers with one contract in both engines: `{mode, intent, slots, evidence[{type,value,page?}], confidence, answer, next_action}`. Online, `DivaController::answerAndStore()` runs the PHP pipeline (`Diva` + `DivaContract`); offline, `public/assets/js/diva-offline.js` runs the same stages over the shipped index. Verification is the final gate in both: a quotation must be a normalised substring of the page it cites, and every figure in the answer must appear in the evidence, a cited page, the captured record values or the question itself. A draft that fails is replaced with "I stopped short of answering:" and the reason.

| Case | What was executed | Result |
|---|---|---|
| TC-167 | `php bin/diva-eval.php` — the 74 legacy cases (routing, carried topics, follow-ups, refusals, greeting punctuation) | Pass — 74/74, intent 74/74, content 74/74 |
| TC-168 | `php bin/diva-eval.php --golden` parses `docs/diva-golden-set.md` (60 rows, 10 per role) as the fixture and compares intent, category, cited pages, refusal and quotation text against a read-only run | Pass — routes 60/60, citations 32/32, refusals 3/3, 57 non-refusal rows quote a page |
| TC-169 | The captured output is written to `docs/diva-eval-results.txt` on every run, then echoed. Exit status is nonzero when a legacy or golden case fails | Pass |
| TC-170 | Index size: `public/assets/data/diva-index.json` rebuilds to 25 pages, 250 chunks, 102 485 bytes raw and 32 286 bytes gzip — inside the 300 KB compressed budget | Pass |
| TC-171 | Stop-word regression: "What does the guidebook say about the internship report?" ranked page 22 (Form B consent) because `guidebook`/`say` dominated the BM25 query. Added `guidebook`, `say`, `says`, `according` to `STOP`, rebuilt the index; ranking now starts pages 10/17/12/21 and both evaluation sets stayed green | Pass after fix |
| TC-172 | Guidebook-phrase guard: "What does the guidebook say about roles and responsibilities?" routed to the accounts rule because of `roles`. The router now sends an explicit "guidebook … say/state/cover" request to retrieval, so the quote wins | Pass after fix |
| TC-173 | Online path through the shipped shell: composer posts to `?r=api/diva`, returns the PHP contract, renders "Marking model · saved to your account" with the full 200-mark model and a "Guidebook page 24" source link; the shell takes the returned `chat_id` | Pass — chat 38, `diva_turn` 56 (`evaluation`, rules, high) |
| TC-174 | JSON endpoint semantics from the Apache shell: 200 contract; 400 empty question; 422 question over 500 characters; 403 chat owned by another account; 419 for a POST without `_csrf` (the bootstrap HTML page, not JSON) | Pass |
| TC-175 | Offline guidebook answer: served prompt "What does the guidebook say about the internship report?" renders "Guidebook search · on this device", confidence high, verified quotes and three page links, next action, and the offline note | Pass |
| TC-176 | Offline records question with no saved answer ("When is my next logbook entry due?") renders "Rules & records · on this device", confidence low, and the honest text "That answer needs your live records, and this device is offline…"; the question is queued | Pass |
| TC-177 | Saved-answer replay: after the same question was answered online, asking it offline renders "· saved answer", the full logbook figures, the saved timestamp and "Answered from a copy saved on this device while online" | Pass |
| TC-178 | Fix found while driving the composer: `app.js` `initDiva()` validated the box after `diva-offline.js` had already handled the submit and cleared it, leaving a stale "Type a question first." custom-validity message that blocked the next submit. The listener now returns when `event.defaultPrevented` | Pass after fix |
| TC-179 | Reconnect: `LeapServerReachable` restored and `online` dispatched; pending questions posted, the toast read "2 questions answered on this device have been saved to your account.", pending reached 0 and the shell adopted the returned chat id | Pass |
| TC-180 | Fix found in the same run: each queued question opened its own conversation because the flush always sent `chat_id` 0. `postToServer` now takes a chat id, the first success is reused for the rest of the queue, and identical queued questions are deduplicated. Database: chat 39 holds both turns (`deadline.career_fair`, `internship.duration`) | Pass after fix |
| TC-181 | Fix found in the service worker: `cacheFirst` consulted the unversioned precache with `ignoreSearch` before the network, so a changed asset kept its old body under a new `?v=`. Exact shell and runtime hits are now tried first, then the network, and the precache is only the offline fallback. `VERSION` moved to `v4` | Pass after fix — patched `diva-offline.js` was served under the new `?v=` after one reload |
| TC-182 | Hydration and basis regression in `bin/diva-eval.php`: restored history must keep `resolved` and `reasoning`; "Why?" answers must contain every recorded step of the previous answer; an expanded "Tell me more" must match the pages it returned and never claim it returned everything | Pass — 74/74 |
| TC-183 | Saved answers and queued questions now live under `leap.offline.diva.snap.<uid>` / `.pending.<uid>`. The old unscoped keys are deleted on load. Student (uid 6) saved an answer online and replayed it offline with "· saved answer"; signing out removed every `leap.offline.diva.*` key; signing in as the supervisor (uid 5) and asking the same question offline produced the honest live-records refusal, not the student's answer | Pass after fix |

Screenshot capture was blocked in the final run (the in-app browser reported no visible surface), so the last pass is evidenced by DOM text and the database rows above rather than images. The optional localhost rephraser stayed disabled; `diva-eval.php` reports the local model status on every run.

## Workstream E — repository hygiene and deployment

Everything here was executed on the build machine with PHP 8.0.30 and Node
26.13.0. The image itself was not built: no Docker engine is installed.

| Case | What was executed | Result |
|---|---|---|
| TC-184 | `php -l` over every `.php` file under `digital-leap/` (114 files) and `node --check` over `public/assets/js/*.js` plus `sw.js` (5 files), using the same loop the workflow runs | Pass — 114/114 and 5/5, exit status 0 |
| TC-185 | `.gitignore` added: `config/config.php`, storage logs and caches, `node_modules/`, `vendor/`, `deploy/.env`, editor noise and `*.log`. `.dockerignore` added so a build context excludes `config.php`, logs, avatars, `.git` and docs | Pass — both files parse as plain text and every pattern is the documented one |
| TC-186 | `require digital-leap/app/routes.php` returns 122 entries; `README.md` states 122 and its new deployment section is numbered 9, with Testing 10, Conventions 11 and Licence 12 | Pass |
| TC-187 | New `app/.htaccess` and `bin/.htaccess` deny-all files, matching `config/` and `storage/`. Over Apache: `digital-leap/app/bootstrap.php` → 403, `digital-leap/bin/diva-eval.php` → 403, `storage/uploads/demo_cv_mohamed.pdf` → 403, while the landing page, `app.css` and `diva-offline.js` → 200 and `manifest.webmanifest` → 200 `application/manifest+json` | Pass |
| TC-188 | `deploy/php.ini` parsed with `parse_ini_file` (13 keys: 8 MB upload, 12 MB post, expose_php off, Africa/Freetown); `deploy/docker-compose.yml` and `.github/workflows/lint.yml` checked for their required keys (`services`, `depends_on`, `healthcheck`; `checkout@v4`, `setup-php@v2`, `setup-node@v4`, `php -l`, `node --check`) | Pass — structural check only; YAML was not parsed by a YAML library because none is installed here |
| TC-189 | Reviewed `config/config.sample.php`: no credentials, `mail.mode=log`, `diva.local_model.enabled=false` on `127.0.0.1:11434`, `app.base_url` empty so the path is derived | Pass — it remains the only configuration file the repository should carry |
| TC-190 | `git ls-files` still lists `digital-leap/config/config.php` and `digital-leap/storage/logs/php-error.log` from older commits, so `.gitignore` alone cannot hide them (`git check-ignore` returns no match). Neither was read, modified or removed here: `git rm --cached` is an owner action, documented in `README.md`, `deploy/README.md` and `.gitignore` | Recorded — owner action outstanding |

Docker was not available on this machine, so `docker build` and
`docker compose up` are untested. `deploy/README.md` says so explicitly; the
first build must be treated as unverified until it runs somewhere with an
engine. The workflow's YAML is reviewed by eye and by key presence, not by
GitHub Actions itself.

## Final pass — text wrapping, the install entry point and per-member settings (20 November 2026)

Three items came back from the owner after the whole-system build, all from one
screenshot of `?r=student/placement`: file names, the words "Internship Report"
and "Download" were breaking one character per line inside the Documents card;
pressing **Not now** on the install banner left no way to install the app later;
and a system like this was expected to offer the person using it a settings
page. This pass covers all three.

Environment: Apache at `http://localhost/Digital_LEAP_Assignment3_Group_G/digital-leap/`,
PHP 8.0.30, MariaDB 10.4.32, Firefox for the visible check and the in-app
Chromium for the measured probes. The in-app browser surface reported
`NATIVE_BROWSER_VIEWPORT_UNAVAILABLE` (`visible=false`, 382×498) for this whole
pass, so **no screenshots were captured**. Every layout claim below is a
measured geometry or a computed style, and the desktop shape was measured by
forcing the card to 760 px inside the narrow viewport rather than by pretending
the viewport was wider.

| Case | What was executed | Result |
|---|---|---|
| TC-191 | Cause found for the character-by-character breaking: `overflow-wrap: anywhere` on `.table td` and `.doc-name`. Unlike `break-word`, `anywhere` lets a soft-wrap opportunity count in intrinsic min-content sizing, so a five-column table inside a half-width card shaved every word down to one character before it widened a column. `.table td`, `.stat-list .row > strong` and `.doc-name` now use `break-word` (`app.css:861`, `:903`, `:1532`) and `body` keeps it (`:115`); places that must break a genuinely long token — chat body, thread copy, chart labels, profile chips — deliberately keep `anywhere` | Pass after fix |
| TC-192 | A button or badge label is one unit: `.table td .btn, .table td .badge { white-space: nowrap }` (`app.css:864`) and `.doc-action { flex: 0 0 auto; white-space: nowrap }` (`:1536`). Confirmed by computed style on every in-table button in the coordinator screens | Pass |
| TC-193 | The Documents card stopped being a table. `app/Views/student/placement.php:85-95` renders `ul.doc-list > li.doc-row`: the file name in `.doc-name` owns its line, size/type/date hang under it in `.doc-meta`, and the action sits in `.doc-action` | Pass |
| TC-194 | Word-geometry probe over `#main`: a `TreeWalker` collects text nodes and every word of five characters or more is measured with `Range.getClientRects()`; more than one rectangle means the word was split mid-word. At the reported 367 px content width on `student/placement`, the only two-line entries are the two 38-character seeded file names, which is the correct behaviour at that width, and "Internship Report" and "Download" are each on one line | Pass |
| TC-195 | Same probe at the desktop shape, measured by forcing the card to 760 px: `nameLines 1`, `sameRowAsButton true`, `nameWidth 630`, `buttonWidth 85`, `gapToButton 16` — a 38-character name and its button share one row with room left over | Pass |
| TC-196 | Same probe across the rest of the app at the natural width: dashboard, `student/logbook`, `notifications` and `student/results` returned broken words `[]`. Coordinator dashboard: 2 tables / 32 cells, `cellWrap break-word`, `clipped 0`. `coordinator/placements`: 1 table / 49 cells, `clipped []`, broken `[]`. No page gained horizontal scroll | Pass |
| TC-197 | Install entry point after **Not now**: with `localStorage['leap.install.dismissed'] = '1'` the banner stays hidden while both permanent controls remain visible — **How to install this app** in the account menu and in **My settings → This device**. `initInstallPrompt()` now binds every `[data-install-trigger]` instead of the first one it found | Pass |
| TC-198 | Each trigger reveals only its own note: `helpFor(trigger)` walks up to the nearest block containing a `[data-install-help]`, so opening the menu copy left the settings-card copy hidden (`menuHelpHidden false`, `cardHelpStillHidden true`) and the reverse also held. The open account menu stays inside the viewport (right edge 354 px of 382 px) | Pass |
| TC-199 | Install copy is per browser: Chrome and Edge are told the address-bar icon can take a moment and to reload if it has not appeared; Firefox is told it has no install button of its own on desktop for this build and to use Chrome or Edge, which matches Firefox's current desktop PWA support position; where `isSecureContext` is false the settings row reports "Needs HTTPS or localhost" instead of offering an install | Pass |
| TC-200 | `GET`/`POST settings` are gated on `auth`, not `profile.edit`. Signed in as the management account, the only role without `profile.edit`, `?r=settings` opens as "My settings" and a changed box saved with "Your settings are saved." and no 403. Student and coordinator round-trip the same route | Pass |
| TC-201 | Bug found and fixed: `SettingsController::save()` read `email.<family>` while the form posts `email[<family>]`, so every family would have saved as off. It now reads the array once and keys off `isset($families[$family])` | Pass after fix |
| TC-202 | Bug found and fixed: `app/routes.php` had no `use Leap\Controllers\SettingsController;` import, so `::class` resolved against the global namespace, every visit to `?r=settings` rendered the generic error page, and `storage/logs/php-error.log` recorded `Class "SettingsController" not found` at `Router.php:26` | Pass after fix |
| TC-203 | Bug found and fixed: the settings markup grouped its fields in `.card-body`, a class that exists nowhere in `app.css` (cards are `.card-head` plus direct children). Renamed to `.settings-group` and defined it as `display: grid; gap: 8px` (`app.css:1461`) | Pass after fix |
| TC-204 | A save writes only what changed: with the four email families at their defaults and one box cleared, `user_preference` gained exactly one row (`notify.email.mark = off`) instead of six; a later save of `ui.text_size = large` and `ui.reduce_motion = on` added only those two. `settings.updated` landed in `audit_log` with `before_json` `{}` and the changed keys in `after_json` | Pass |
| TC-205 | Text size and motion preview before saving: choosing **Large** set `html[data-text-size="large"]` and the measured root font-size went 16 px → 18 px; ticking **Reduce motion** set `data-reduce-motion="on"` and `.card`'s computed `transition-duration` became 1e-06 s. Both attributes are painted by `layout.php` from `Preference`, so the choice survives a reload and follows the account to another device. Spacing tokens stay in px, so the layout keeps its shape at the larger type | Pass |
| TC-206 | The **This device** card reports real state, not placeholders: install row "Not installed on this browser", "0 answers" for DIVA, "0 changes" in the outbox and "24 responses in 2 caches" counted from `caches.keys()` and each cache's `keys()`. It repaints on the `leap-sync` event rather than on a timer | Pass |
| TC-207 | The two device-side clears are scoped and cancellable. **Clear saved DIVA answers** deletes only `leap.offline.diva.snap.<uid>` and `leap.offline.diva.pending.<uid>` for the signed-in uid. **Clear browsed pages** deletes only caches whose name starts `leap-runtime`, so `leap-shell-v4` survives and the app stays installable. Both listeners return early when `event.defaultPrevented` is set, which is how the `data-confirm` dialog cancels them, and both confirm texts say plainly what is and is not removed | Pass |
| TC-208 | Muting stops the mail copy, never the record. With `notify.email.mark = off` for the student, two `Notify::push()` calls (`mark.published` and `logbook.reminder`) took `notification` from 9 to 11 rows while `email_queue` went from 2 to 3: the bell kept both, only the unmuted family was queued. Ten notification types map onto the four families through `Preference::family()`, with `placement` as the fallback. Test rows were deleted afterwards and both counts confirmed back at 9 and 2 | Pass |
| TC-209 | `php -l` over every `.php` file under `digital-leap/` and `node --check` over the five scripts, using the same loop `.github/workflows/lint.yml` runs | Pass — 117/117 and 5/5, exit status 0 (the three settings files take the PHP count from the 114 recorded at TC-184) |
| TC-210 | DIVA regression after the CSS, JS and settings work: `php bin/diva-eval.php` 74/74; `--golden` routes 60/60, citations 32/32, refusals 3/3, re-captured in `docs/diva-eval-results.txt` | Pass |
| TC-211 | Demo accounts still sign in: student, coordinator and management each authenticated through the form and reached their dashboards. Probe note, not a product defect: the coordinator demo-role button followed immediately by a **Sign in** click did not submit inside the in-app browser, so the credentials were typed into the inputs and the form submitted directly | Pass |
| TC-212 | Counts re-derived from source instead of copied forward: `require app/routes.php` returns 124 entries (the 122 at TC-186 plus the two `settings` routes) and `database/digital_leap.sql` creates 33 tables, matching `information_schema` on the live database. `README.md`, `INSTALL.txt` and `docs/VERIFY.md` now carry 124 and 33 | Pass |
| TC-213 | The route table was summed by group after the edit: 12 public and auth + 16 shared signed-in + 2 internal API + 6 DIVA + 8 messaging + 22 student + 7 supervisor + 12 mentor + 21 coordinator + 3 management + 15 administrator = 124. The `README.md` route table previously summed to 120 because the two internal API routes were in no row; they now have one | Pass — the documented table adds up |
| TC-214 | `docs/design-system.md` gained the wrapping rule (`break-word` versus `anywhere`, and the control-label `nowrap`), the two comfort hooks and the four new components; each class named there was grepped back to `app.css` (`.doc-list:1520`, `.doc-row:1521`, `.doc-name:1532`, `.doc-action:1536`, `.settings-group:1461`, `.install-help:1450`, `.sidebar-settings:622`, `html[data-text-size]` / `html[data-reduce-motion]` at `:2992`–`:2995` inside the `responsive` layer that opens at `:2797`) so no documented selector is fictional | Pass |
| TC-215 | User guide length after the two new paragraphs: 1,785 words, still three sections plus the roles table. TC-131 recorded 1,371 words, which pre-dates the DIVA, offline and filter paragraphs added since. At roughly 600 words a printed page this is about three pages, but the markdown has not been re-laid-out or re-paginated for print since those additions — that check is outstanding and is not claimed as passing | Recorded — length re-measured, print pagination not re-checked |

Test residue from this pass: the `user_preference` rows written while proving
TC-204 and TC-208 were deleted (`SELECT COUNT(*) FROM user_preference` is 0),
`leap.install.dismissed` was cleared in the browser, and the two scratch PHP
probes under `/tmp` were removed. The `settings.updated` audit events stay — the
log is append-only by design and an honest record of a save that really
happened.

Schema: `user_preference` (composite primary key `user_id` + `pref_key`, value,
`updated_at`, foreign key cascading on user delete, utf8mb4) is part of
`database/digital_leap.sql` and of the additive
`database/migrations/2026-11-20_user_preference.sql`, which was applied to the
live database with `CREATE TABLE IF NOT EXISTS`. It drops nothing and changes no
existing row.

## The guidebook as a document (22 November 2026)

| TC | Check | Result |
| --- | --- | --- |
| TC-216 | The guidebook PDF ships unmodified. `sha256sum` over the published file and over `digital-leap/storage/templates/Internship_Guidebook_LEAP_Clean_Layout.pdf` returns the same digest, `1aad743f4d671e081042b183c7d7ec9b40e46d3dd91eec11604c7580fa252b14`, at 281,848 bytes. The file was copied byte for byte; it was not opened for editing, re-exported or regenerated | Pass |
| TC-217 | Two routes added, `GET guidebook` and `GET guidebook/pdf`, both gated on `auth` because the document is published to all six roles. `require app/routes.php` now returns 126 entries and 9 of them are `auth`-gated (logout, settings ×2, feedback, download, logbook/template, career-fair/template, guidebook ×2). `README.md` now carries 126 and the shared signed-in row 18; its §5 sentence had also mis-stated the old set as "three" while listing four, and omitted logout and the two template routes — it now names all nine | Pass |
| TC-218 | The page opens for every role: student, host supervisor, academic mentor, coordinator, management and administrator each signed in and fetched `?r=guidebook` with HTTP 200, the sidebar entry reading `class="active"` and 25 page rows on each. Signed out, both `?r=guidebook` and `?r=guidebook/pdf` answer 302 to `?r=login` | Pass |
| TC-219 | The page itself renders the document: **Open the PDF** links to `?r=guidebook%2Fpdf` with `target="_blank"`, **Download PDF (275 KB)** links to the same route with `download=1`, and the list under the heading has one `doc-row` per page — first "Page 1 Limkokwing Entrepreneurship Acceleration Platform", last "Page 25 Module 2 (2 Credits): Internship Report Weighting". Page links were later changed from the text view to the PDF by TC-223 | Pass |
| TC-220 | The PDF is streamed, not linked to a path. `?r=guidebook/pdf` returned 200 with `Content-Type: application/pdf`, `Content-Disposition: inline; filename="Internship_Guidebook_LEAP_Clean_Layout.pdf"`, `Content-Length: 281848` and `X-Content-Type-Options: nosniff`; the body's SHA-256 matched TC-216. `&download=1` returned the same bytes with `Content-Disposition: attachment` and wrote exactly one `guidebook.download` row to `audit_log`; the inline view wrote none. `storage/.htaccess` still denies direct HTTP to the folder | Pass |
| TC-221 | Layout probe on the rendered page in the in-app browser at 372 px: `documentElement.scrollWidth` equals `clientWidth` (372), so no horizontal page scroll. A TreeWalker over `#main` text nodes, measuring each word of five characters or more with `Range.getClientRects()`, found one two-rect word, "on-the-job", which the browser breaks at its own hyphen as intended; every other word stayed on one line. Screenshots were unavailable in this browser, so the evidence is the DOM probe, not an image | Pass — screenshot unavailable, DOM evidence recorded |
| TC-222 | A navigation entry that DIVA's page guide did not know about would have made its own description wrong ("you can open N pages"), so `DivaGuide::pages()` gained a `guidebook` entry in the workspace group. `php bin/diva-eval.php --golden` after the change: legacy 74/74 and golden 60/60 routes, 32/32 citations, 3/3 refusals, rewritten to `docs/diva-eval-results.txt` | Pass |
| TC-223 | Pages are read as the document, not as extracted text. On `?r=guidebook` the 25 rows now carry 50 links (page name and **Open in PDF**), all to `?r=guidebook%2Fpdf#page=N` with `target="_blank" rel="noopener"`; the rendered page still shows 25 `doc-row`s, 25 "Open in PDF" labels and 51 blank-target links in all (the 50 rows plus **Open the PDF**). `?r=diva/source&page=12` keeps its plain-text citation view but now leads with **Open page 12 in the PDF**, pointing at `…?r=guidebook%2Fpdf#page=12`. Both views pass `php -l`. The PDF itself is untouched, so the `Content-Type`, `Content-Disposition` and SHA-256 results in TC-220 still hold | Pass |

Documentation after the change: the user guide gained a **Guidebook** paragraph and now runs
to 1,862 words (TC-215 recorded 1,785); `docs/VERIFY.md` gained a shared walkthrough step, a
security row for the PDF stream and the audit split, and an install-list line for the third
template; `INSTALL.txt` gained the navigation paragraph, the security sentence and the
storage line; `README.md` gained the feature sentence, the 126-entry and 18-route counts and
the corrected `auth` list. TC-223 later moved the page links from the text view to the PDF at
the chosen page and added the same jump to DIVA's citation pages; the user guide, README and
VERIFY step were reworded to match. No new CSS class was introduced, so
`docs/design-system.md` is unchanged: the page reuses the documents list (`app.css:1520`).

## Report order, submission folder and a Codespaces link (23 November 2026)

| TC | Check | Result |
| --- | --- | --- |
| TC-224 | The landscape Appendix A now follows Part F and precedes Conclusion and References. In the exported PDF the contents page lists Appendix A at printed page 30 and Conclusion and References at printed page 39; footer numbers run physical 32 → "30" through physical 41 → "39"; physical pages 32–40 measure 141–173 characters wide (landscape) and physical 41 measures 98 (portrait). The DOCX and PDF agree at 41 pages. The move was made on a copy; the pre-move DOCX and PDF are kept in the offcuts folder on the Desktop | Pass |
| TC-225 | The Discussion header was flush with the card edge because `.chat-card` sets `padding: 0` for its full-bleed message list, leaving `.card-head` as the only direct child with no inner inset. The scoped rule `.chat-card > .card-head { padding: 18px clamp(14px, 2vw, 20px) 0; }` (`app.css:3127`) restores the same left and right inset the other cards use. In a rendered logbook page at 387 px the heading text measured a 15 px inset, equal to the paragraph inset in the neighbouring card, and the card itself still reaches the edge as designed. DIVA's `.diva-chat-head` sets its own padding and is not selected | Pass |
| TC-226 | Project folder after the sort: 91 files outside the submission manifest were reviewed. 73 MB of scratch material moved to `Desktop\Digital_LEAP_offcuts_2026-11-23\` — `.tmp-report/` (report scripts and test DOCX), `REPORT-backups/` (six dated DOCX/PDF pairs and superseded figures) and `storage-junk/` (php-error.log, a stray PNG, an errant DOCX, four unseeded avatar photos). Nothing was deleted. The root now holds `.devcontainer/`, `.dockerignore`, `.github/`, `.gitignore`, `INSTALL.txt`, `LICENSE`, `README.md`, `REPORT/`, `database/`, `deploy/`, `digital-leap/` and `docs/`, plus the local untracked `digital-leap/config/config.php` that the running XAMPP copy needs. The landing page still returned 200 afterwards, and the submission ZIP in Documents was refreshed with the corrected `app.css` | Pass |
| TC-227 | `.devcontainer/` adds a Codespaces stack without touching the application: `devcontainer.json` (compose service `app`, port 8080 forwarded and public, `postCreateCommand`), `docker-compose.yml` (the `deploy/Dockerfile` image, MariaDB 10.11, the seed mounted at `/docker-entrypoint-initdb.d`, `storage/` in a named volume, application source bind-mounted) and `config.codespaces.php` (db host `db`, password `leap-demo`, mail to log, DIVA local model off). `setup.sh` waits up to 180 s for the `users` table, gives `storage/` to `www-data` and marks the workspace safe for root git. Verified here: `php -l` on the config, `bash -n` on `setup.sh`, `JSON.parse` on `devcontainer.json`, and the probe query (with host `127.0.0.1` substituted) returned the seeded count of 17 users; the first probe run named the table `user` and failed, which is how the script now says `users`. No Docker engine or YAML parser exists on this machine, so the image build, `docker compose config` and a live codespace were not run — the same limitation recorded for `deploy/Dockerfile` in Workstream E. Treat the first codespace start as the real test | Pass — file syntax and probe only; build unverified |


## Daily and weekly logbook email (23 November 2026)

| TC | Check | Result |
| --- | --- | --- |
| TC-228 | `Notify::push` gained an optional seventh parameter, `$emailBody`; the bell keeps the short line while `email_queue.body` carries the full text, so every existing caller is unchanged. New `app/Services/LogbookDigest.php` builds plain-text messages and `sendDaily()`, and `bin/send_daily_records.php` runs it with `--date=YYYY-MM-DD` and `--dry-run`. Recipients are the placement's host supervisor (when set) and academic mentor, each with their own entry link. Only days on a week that is still a draft are sent, so a submitted week is not repeated day by day after its weekly email; draft days are labelled as drafts. A day is never emailed twice: each delivery writes one audit row `logbook.day_notice` on the entry with the recipient and the work date in `after_json`, because editing a week deletes and rewrites `logbook_day` rows (TC-77). Evidence: `--dry-run --date=2026-11-22` previewed two groups of five days (16–20 November) — 10 day records, 0 queued. A real run inside a transaction that was then rolled back queued two digest emails and wrote 10 audit markers; the second run in the same transaction skipped all 10 and queued nothing; `notification`, `email_queue` and `audit_log` counts were 52, 31 and 672 before and after, so nothing was left behind. The weekly body was rendered from the seeded week 1 (subject “Week 1 logbook awaiting review — Mohamed Alie Kamara”, five daily rows, every summary section, “Total hours: 37.5 h”, submission time and link); the daily body was rendered with two draft days and the draft paragraph. Copy was updated in the entry form's daily-record hint and submit confirmation, the weekly reminder text, the user guide, `README.md` §8 and `INSTALL.txt` §7. The CLI link now ignores the script folder, which had produced `bin/index.php?...`. Not exercised: the weekly push through a live student session; the body builder and the push API were verified directly. No schema change, so Appendix A and the 33-table count still hold | Pass — web submission path covered by the same builder and API, not a live session |

## GPS attendance and per-day sending (24 November 2026)

| TC | Check | Result |
| --- | --- | --- |
| TC-229 | Attendance is stored without a schema change, so the 33-table count in Appendix A still holds. New `app/Services/Attendance.php` writes append-only `audit_log` rows `attendance.check_in` / `attendance.check_out` against the placement, with type, latitude, longitude, accuracy, distance and source in `after_json`; today's state comes from the latest event since local midnight, so a check-in followed by a check-out allows both once and neither repeats. The organisation pin is a `setting` row `geo.org.<org_id>` holding `{lat,lng}`, absent until a coordinator saves one. Inside a transaction that was rolled back: Lagos (6.524400, 3.379200) to Accra (5.603700, −0.187000) measured 407,393 m by haversine; a check-in was accepted, the duplicate rejected, a check-out accepted and the duplicate rejected; `today()` and `recent()` returned the expected shape. `audit_log` was 0 rows for the placement before and after the rollback | Pass |
| TC-230 | Per-day sending uses the same `logbook.day_notice` markers as the evening run, so a day sent by hand is skipped by `bin/send_daily_records.php` and can never go twice. `LogbookDigest::sendDay()` refuses an entry whose status is not `draft` or `returned`, refuses a malformed date, sends only to recipients not already recorded for that date and returns a sentence naming them. `deliveredDays()` reports what each day has already reached. Tested inside a transaction and rolled back: `sendDay(12, '2026-11-16')` returned “Mon 16 Nov 2026 sent to your host supervisor and academic mentor.”, a second call returned “already sent”, `2026-13-01` was rejected, and `deliveredDays(12)` returned `{"2026-11-16":[5,3]}`; an approved week rejected the call. Notification and email_queue counts were 52 and 31 before and after | Pass |
| TC-231 | The site pin is set from the organisation form by a coordinator. `CoordinatorController::orgSave()` accepts both coordinates or neither, clears the pin when **Remove site pin** is ticked, rejects one coordinate alone and rejects out-of-range values, and writes `organisation.geo_updated` to the audit trail. In a transaction that was rolled back, setting and clearing the pin left no `geo.org.*` setting row. A live attempt with latitude only returned the validation message and wrote no setting row | Pass |
| TC-232 | Student attendance renders and persists. Signed in as the seeded student, **My placement** showed the Attendance card with Today, Site pin (not set), Check in at site and Check out; a check-in and then a check-out were submitted through the forms and appeared in the recent list and the Status timeline as “Attendance check-in” and “Attendance check-out”. With no pin the distance column reads “No distance” and the source is “attested”. Today's state disabled the buttons already used. The two records are real demo data now: `attendance.check_in` and `attendance.check_out` on 23 November 2026 at 23:02 and 23:03 | Pass |
| TC-233 | A finished day can be sent without submitting the week. On draft entry 12 the Monday 16 November row carried **Send** and the other four days did too; pressing it posted `student/logbook/send-day` and returned to the entry with the row now reading **Sent**. Two audit markers were written (`logbook.day_notice`, users 5 and 3, `2026-11-16`) and two notifications “Daily record — Mohamed Alie Kamara — Mon 16 Nov 2026” were queued. The entry stayed a draft and the full-week submit button was unchanged. No Send column appears on an approved entry | Pass |
| TC-234 | Staff see the same check-ins. The supervisor entry page returned 200 with “Attendance check-ins”, the attested source and “Site pin: not set”; the mentor entry page returned the same card with the check-out row; the coordinator placement page showed the attendance card and “Attendance check-in” in its timeline; the organisation form rendered latitude and longitude inputs. All four screens lint and none exposes another placement's rows | Pass |
| TC-235 | Location is read only on a deliberate press. New `public/assets/js/attendance.js` intercepts the two forms, says “You are offline” without a connection, asks for the position with high accuracy and a ten-second timeout, fills the hidden fields and submits; a denied or failed reading records an attested check-in and still submits. No inline script was added, so the CSP `script-src 'self'` holds. The `Permissions-Policy` header in both `app/bootstrap.php` and `digital-leap/.htaccess` now reads `geolocation=(self), microphone=(), camera=()`; duplicates are identical, and a permissive plus a restrictive copy would have been a defect. The real permission-granted path was not exercised end to end because the in-app browser suppresses the prompt; the distance maths was covered by TC-229 | Pass — granted-prompt path not exercised |
| TC-236 | After the changes, `php -l` passes on all 18 touched PHP files and `node --check` on `attendance.js`. `require app/routes.php` returns 128 entries with 24 student routes, and `README.md` now says 128 and 24, lists `attendance.js` and the two new services, and `INSTALL.txt` §7 explains the geolocation permission, the optional pin and the per-day Send. The user guide's student and supervisor sections describe the Attendance card, the Send column and the staff check-in list. No commit or push was made | Pass |

## Devcontainer recovery audit (24 November 2026)

| TC | Check | Result |
| --- | --- | --- |
| TC-237 | The Codespaces recovery screen was traced to container startup, while the public port returned 404. Local audit found three deployment weaknesses: the custom image used end-of-life `php:8.0-apache`, `.dockerignore` removes every runtime log so `storage/logs` was not guaranteed to exist before Apache started, and the Dockerfile comment claimed the application copied `config.sample.php` automatically although `bootstrap.php` only reports a missing config. `deploy/Dockerfile` now pins `php:8.3-apache-bookworm`, creates `storage/logs` and `storage/uploads` before ownership is handed to `www-data`, and documents the required mounted config. The devcontainer name and deployment docs now say PHP 8.3 while the application itself still targets PHP 8.0+. `JSON.parse` accepted `devcontainer.json`, `bash -n` accepted `setup.sh`, both Codespaces and sample config passed `php -l`, and every referenced Dockerfile, ini, vhost and seed file exists. No Docker engine is installed on this machine, so the image build and the exact Codespaces creation failure remain unverified; a rebuild after this commit is the live test | Pass — static validation only; live rebuild still required |

## GPS-verified staff site visits (25 November 2026)

| TC | Check | Result |
| --- | --- | --- |
| TC-238 | Staff visits are stored without a schema change, so the 33-table count and Appendix A still hold. New `app/Services/SiteCheckin.php` writes append-only `audit_log` rows `sitevisit.checkin` against the placement, with type, role, purpose, visit id, latitude, longitude, accuracy, distance and the pin used in `after_json`; `recent()`, `proofsFor()` and `forVisit()` read them back. A check-in without coordinates is refused — “Enable location sharing to record a site visit…” — so a visit is never recorded unattested. With a pin set at the site, a check-in from a point 16 m away was accepted and stored as “Site visit recorded 16 m from the site pin”; one from 45.2 km away was refused with “You are 45.2 km from the site pin. A site visit can only be recorded within 500 m…”. A second check-in for the same visit by the same person returned “You have already checked in for this visit.” With no pin, the distance is null and the record is still written, so a coordinator who has not set one is not blocked | Pass |
| TC-239 | A mentor cannot complete a physical visit unverified. `MentorController::visitComplete()` selects the placement's `org_id` and, for `mode = physical`, looks up the mentor's own proof; with none it flashes “Check in at the site before completing a physical visit.” and returns to the visit row. After `POST mentor/visits/checkin` recorded the proof, the same completion succeeded. Seeded visit 2 (`virtual`) completed with no check-in, and the phone copy states that virtual and phone visits need none. Completing another mentor's visit still fails the ownership check | Pass |
| TC-240 | Coordinators record their own monitoring visits. `POST coordinator/placements/checkin` takes an optional purpose (truncated to 160 characters), records it through the same service with role `coordinator`, and redirects to the placement page anchor `#staff-visits`, where the **Staff site visits** card lists the name, role, time, distance and purpose; `coordinator/visits` repeats the latest twelve in its own table and shows a per-visit Proof column. That write route is gated on `visit.view` because only mentors hold `visit.manage`, and the controller itself calls `requireRole('coordinator')`. Evidence: a mentor POST with a valid CSRF token returned 403 and a student POST returned 403; a POST without a token returned 419; the coordinator POST returned 302 and the record appeared on both screens | Pass |
| TC-241 | The interface states the rule before it is hit. On `mentor/visits` a scheduled physical visit offers **Check in at site** with a live status line, an unverified row reads **Not checked in** in warn, a verified one reads **Site verified** with the time and distance in ok, a non-physical visit reads **Not required**, and the Complete button is disabled with the reason while proof is missing. `attendance.js` now serves both `form[data-attendance-form]` and `form[data-sitecheckin-form]`; the strict form refuses on offline, unsupported or denied location and never falls back to an attested submit, while the student form keeps that fallback. All script remains in the existing external file, so the CSP `script-src 'self'` and `Permissions-Policy: geolocation=(self)` headers are unchanged. `attendance.js` is not in the service-worker `SHELL` list, so no `sw.js` VERSION bump was needed, and `asset()` cache-busting picks up the new file by `filemtime` | Pass |
| TC-242 | After the changes, `php -l` passes on the nine touched PHP files and `node --check` on `attendance.js`. `require app/routes.php` returns 130 entries — 11 public, 9 `auth`-gated and 110 permission-gated — and `README.md`, `docs/DEMO.md` and the `docs/VERIFY.md` accuracy row now carry 130 and 110, with the test log running to TC-242. The README route table was re-counted per area rather than adjusted by guesswork: mentor 12 → 13 and coordinator 21 → 22, which is where the two new routes sit and which is also why the table had fallen two short of the total. `INSTALL.txt` §7 explains both staff check-ins and the 500 m rule. The user guide's student paragraph no longer claims students are the only people tracked, and its Visits paragraph explains the check-in, the rule and the two staff logs; the prose grew by about 90 words to 1,969, and the print pagination check left outstanding at TC-215 is still outstanding. Signed in again after the copy edits, `mentor/visits` and `coordinator/visits` returned 200, the placement page showed **Staff site visits**, and the mentor's remaining scheduled physical visit still offered **Check in at site**. No schema change and no permission-data change were needed, so a `git pull` on the Codespaces volume applies cleanly. No commit or push was made | Pass — with the local demo-data residue below |

Test residue from this pass, in the local demo database only: `audit_log` rows 719
(coordinator check-in with no pin set) and 720 (coordinator check-in 16 m from a
temporary pin) remain as real records, as does the mentor check-in used for TC-239;
seeded visits 2 (virtual) and 3 (physical) are now completed with findings. The
temporary pin was cleared with `UPDATE … SET setting_value = ''`, not a delete, and
the page returned to “Site pin: not set”. Nothing was written to the Codespaces
database.

## Brand mark, device fixes and the next-build brief (25 November 2026)

| TC | Check | Result |
| --- | --- | --- |
| TC-243 | The supplied Limkokwing/LEAP artwork became a working brand set without touching the guidebook. Two originals were copied to `docs/brand-source/` (badge 1362 × 1155, flag-globe 234 × 295); derivatives went to `docs/brand-preview/` and `public/assets/img/`: the badge plate, a circular globe crop (456 × 456, white removed), three transparent wordmarks (ink, ivory, gold; 1013 × 566) and new 192/512 app icons. The transparent words were extracted from the badge's white-on-black lockup by taking luminance as alpha and cropping to the inner plate, so the black plate no longer shows on the white topbar. The app header, auth tile and landing header use `leap-wordmark-ink.png`; the footer keeps the LUCT lockup, whose sample is a pale-blue `#E6EFF8` field with near-black lettering and therefore belongs on light surfaces only. Evidence: landing 200, wordmark 200 `image/png` at 78,374 bytes, landing HTML carries the new filename. `sw.js` is now `VERSION = 'v6'` and precaches both wordmarks | Pass |
| TC-244 | Device faults reported by members were reproduced and fixed. At 949 px the fixed "Online" chip overlapped the landing header's **Create account** button (measured rectangles intersected); at 550 px the header buttons wrapped. The chip now sits bottom-right on public pages, clear of the header and of the left-hand sync strip; below 768 px the header actions are `white-space: nowrap`; below 560 px the brand text hides and the chip shrinks to a dot. `viewport-fit=cover` was added to the viewport meta, `env(safe-area-inset-*)` padding to `.topbar`, `.site-foot` and `.sidebar`, and `100dvh` fallbacks to the sidebar and the DIVA stream. After the change the rectangles do not intersect, `scrollWidth === clientWidth`, and both header buttons stay on one line. `app.css` braces balance 1175/1175; landing, login and register return 200 with no PHP warning. The browser surface was hidden for a final screenshot, so this pass is measurement plus the earlier screenshot that showed the overlap | Pass — final screenshot not captured |
| TC-245 | Two documents were written for the next build: `docs/REDESIGN-BRIEF.md` — the four-signature presence model (arrived, confirmed, visited, reviewed), Limkokwing and PWA research, the asset inventory, landing and splash specifications, offline and device diagnoses, the Codespaces recipe, the demo-data story, DIVA work and 20 acceptance tests — and `docs/MASTER-PROMPT-NEXT-BUILD.md`, a self-contained eight-phase instruction. No schema, route, permission or account changed; no commit or push was made. Still outstanding and recorded rather than dropped: `@layer` flattening, the full device matrix, the tracking redesign, the fresh seed, the DIVA golden set and the splash | Pass — documents only |

## DIVA launcher, sign-in backdrop and campus hero (25 November 2026)

| TC | Check | Result |
| --- | --- | --- |
| TC-246 | The floating **Feedback** button is now the DIVA launcher. `layout.php` renders `<a class="diva-fab" href="…?r=diva">` with the gold brain mark and the label **DIVA**, inside `Auth::can('diva.use')` and skipped when `$route === 'diva'`; the `.feedback-fab` button and its CSS are gone, while `.feedback-layer` and the form stay because the account menu still offers **Send feedback** and the sidebar **Share feedback**, and `app.js:733` binds every `[data-feedback-open]`. `.diva-fab` is a 44 px pill (measured 91 × 44 at 366 CSS px), pinned with `max(14px, env(safe-area-inset-*))`, z-index 110, charcoal `#111315` with a `rgba(216,183,92,.48)` hairline and the mark at 26 px. Evidence: administrator session — dashboard 200 with one launcher whose centre is topmost, account-menu button and sidebar button both present; `?r=diva` 200 with zero launchers; print rules hide `.diva-fab`; a database query shows `diva.use` on all six roles, so no role lost the shortcut | Pass |
| TC-247 | The sign-in backdrop is sharp. The old `limkokwing-graduation.jpg` was 952 × 340 shown `cover fixed` across a 1440 × 1050 window — a 3.1× vertical stretch of a 0.32 MP file, and `fixed` is the iOS repaint path. The generated campus scene was converted with PowerShell `System.Drawing` to `auth-campus.jpg`, 1536 × 1024 at 261,583 bytes, and `body.auth-shell` now reads `background: #0a0b0c url("…/auth-campus.jpg") center 40% / cover no-repeat` with no `fixed`; the overlay was lifted from `.91/.76/.40` to `.86/.68/.26` over `.52` at the base so the scene reads without losing text contrast. Evidence: computed style on the real page reports `attachment: scroll`, `size: cover`, `position: 50% 40%`, and the file 200 at 261,583 bytes; a Chrome 1440 × 1050 screenshot shows palms, facade, walkway and coastline crisply with the display type still legible, and a 500 × 900 screenshot shows the stacked card layout unchanged | Pass |
| TC-248 | The place appears under the landing label. `app/Views/auth/landing.php` now runs kicker → `<figure class="home-hero-media">` → `h1`, with `campus-hero.jpg` (1536 × 1024, 244,752 bytes), `object-fit: cover` at `50% 44%`, height `clamp(180px, 21vw, 232px)`, a gold-line border and the caption **Illustrative scene** so the generated picture is never passed off as the real campus. The figure is `margin: 18px 0 4px`, which leaves 16 px to the headline. Evidence: at 366 CSS px the figure measured 338 × 169 with the image loaded at its natural size, the `h1` began at y = 328 inside the first screen and `scrollWidth === clientWidth`; at 768 and 500 px the figure spans the column at 168 px tall; the 1440 screenshot shows the figure above a three-line headline with the preview panel still balanced; the page's live counts (6 roles, 39 permissions, 33 tables, 9 hosts) are unchanged | Pass |
| TC-249 | Everything else stays consistent. `sw.js` is `VERSION = 'v7'` and precaches both new images; `php -l` passes on `layout.php` and `landing.php`; `node --check sw.js` passes; `app/routes.php` still returns 130 entries and `app.css` balances 1183/1183 braces; landing, sign-in, dashboard and DIVA all return 200. `README.md`, `docs/DEMO.md` and `docs/VERIFY.md` no longer promise a floating **Send feedback** button and now point to the account menu and the sidebar; `docs/MASTER-PROMPT-NEXT-BUILD.md` gains §0.1 (the three shipped changes as requirements), the asset rules for the two photographs, and a new §13 Phase 9 polish list, with the definition of done renumbered to §14. No schema, route, permission or seed change was made | Pass — committed and pushed |

## Phase 9 — launcher entrance, mobile sign-in strip and image delivery (25 November 2026)

| TC | Check | Result |
| --- | --- | --- |
| TC-250 | The corner launcher is now an entrance, not a door. `layout.php` builds its href from a `match ($route)` of four record pages: `student/placement` → "What is my next step on this placement?", `student/logbook` → "…on this logbook?", `mentor/visits` → "How do I record a site visit?", `coordinator/placements/view` → "What needs attention on this placement?"; the `from` parameter is the current route. The controller already caps the prefill: a 600-character `q` rendered exactly 500 characters in the textarea, and `<script>alert(1)</script>&"` rendered as `&lt;script&gt;alert(1)&lt;/script&gt;&amp;&quot;` with no raw `<script>` in the document. `from=javascript:alert(1)` is rejected against `routes.php` — `data-from` is empty, the "Page context" line is absent and the string never appears. A GET writes nothing: `SELECT COUNT(*) FROM diva_turn` stayed at 72 before and after the prefill requests, because `ask()` is POST-only. `docs/screenshots/phase9/diva-prefilled-390.png` shows the question sitting unsent in the composer under the greeting | Pass |
| TC-251 | `Alt+Shift+D` reaches DIVA from any page and never steals a keystroke from a field. A new `initDivaShortcut()` reads the launcher's own href, ignores `ctrlKey`/`metaKey`, and returns early when the event target is `contenteditable` or an `INPUT`/`TEXTAREA`/`SELECT`; the anchor carries `aria-keyshortcuts="Alt+Shift+D"`. Driven with real CDP key events: on `student/placement` the URL became `?r=diva&q=What+is+my+next+step+on+this+placement%3F&from=student%2Fplacement`; with focus in the settings input `email[logbook]`, the `student/logbook/new` input `week_no` and the messages `SELECT topic`, all three stayed on the same URL; the logbook index, which has no visible field, navigated as expected. The `student/apply` file input cannot hold focus, so the key navigated there — nothing was being typed | Pass |
| TC-252 | The launcher sits below every overlay. Computed `z-index` on the live pages: `.diva-fab` 90, tour layer 100 / spot 101 / bubble 102, `.toast-stack` 120, welcome tour 130, `.feedback-layer` 140, load bar 200; the earlier value was 110, which put it above the tour spotlight. Measured size 95 × 44 below 560 px and 102 × 46 above it, and the matrix below confirms the launcher is absent on `?r=diva` at every width | Pass |
| TC-253 | The phone sign-in carries the scene without pushing the card below the fold. `layout.php` renders `<figure class="auth-strip">` with `auth-campus.jpg` (1536 × 1024), `width`/`height` attributes and the caption **Illustrative scene** inside `.auth-panel`, before the flash messages. In CSS the strip is `display: none` by default and `display: block` at ≤560 px, `height: 150px`, `max-width: 480px`, `margin: 0` (the UA `figure` margin had pushed it 54 px right), `object-fit: cover` at `50% 42%`, and `.auth-panel` gained `min-width: 0`. Measured: at 390 × 844 the strip is 362 × 150 at top 16 and the card top is 180 — above the 844 fold; at 360 the strip is 332 × 150 and at 320 it is 292 × 150, card top 180 in both; at 768, 1024 and 1440 the strip is absent and the brand column shows instead. Deviation from the brief's "at ≤720 px": `.auth-brand` is only hidden at ≤560 px, so between 561 and 720 px the strip would duplicate the visible brand column. Evidence: `docs/screenshots/phase9/signin-390.png` | Pass — 560 px breakpoint, reasoned |
| TC-254 | The landing hero ships the smallest file that looks right. `landing.php` now emits `srcset` with `campus-hero-768.jpg` 768w, `campus-hero-1200.jpg` 1200w and `campus-hero.jpg` 1536w plus `sizes="(max-width: 560px) calc(100vw - 28px), (max-width: 992px) calc(100vw - 48px), 610px"`; `width`, `height`, `decoding` and `fetchpriority` are kept. Measured `currentSrc`: 1440 @ DPR 1 → 768 (the column is 610 CSS px); 390 @ DPR 2 → 768 (362 × 2 = 724); 320 @ DPR 2 → 768 (292 × 2 = 584); 768 @ DPR 1 → 768 (719 px); 768 @ DPR 2 → the 1536 original, which is the honest boundary of this set. A phone therefore receives 72,066 bytes, under the 120,000-byte target, against the 244,752-byte original; the 1200 variant is 112,777 bytes at quality 64. `sw.js` moves to `VERSION = 'v9'` because `SHELL` changed earlier and `app.css`/`app.js` changed now; the campus files stay out of `SHELL` and are served from the runtime cache after first view | Pass |
| TC-255 | No page scrolls sideways at any target width. Seven pages (landing, login, dashboard, student/placement, diva, settings, student/logbook) at 320, 360, 390, 768, 1024 and 1440 — 42 combinations — all report `scrollWidth === clientWidth`, and a right-edge scan of every element finds nothing past the viewport. The one real defect, landing at 1024 (sw 1014 vs cw 1009), came from the decorative plate `.home-hero-panel::before { inset: 12% -6% -8% 10%; rotate(-3deg) }` hanging past the last column; `@media (max-width: 1200px) { .home-hero-panel::before { right: 0 } }` fixed it (landing now 1009 = 1009). The skip link and the off-canvas mobile sidebar sit at `left: -1` by design and are excluded as left-side, not scroll-producing, overflow | Pass |
| TC-256 | Text over the sign-in scrim clears 4.5 : 1 in the worst case. The scrim is `linear-gradient(100deg, .86/.68/.26)` over `linear-gradient(0deg, .52, transparent 46%)`; assuming a pure-white image beneath the weakest stop gives a background of rgb(86, 88, 87), L 0.0962. Ratios: white 7.18, `.auth-lead` (0.84) 5.66, `.auth-points` (0.86) 5.84, `.auth-kicker` #e9d9ae 5.13, caption chip 8.63. Two values failed and were changed: `.auth-brand-foot` from 0.54 (3.38) to 0.72 (4.66) and `.auth-eyebrow` from `--gold-2` #d8b75c (3.71) to #e9d9ae (5.13). Computed style on the live page confirms `rgba(255, 255, 255, 0.72)` and `rgb(233, 217, 174)`. At 200 % browser zoom the CSS viewport halves; probed at 705 CSS px (1440 at 200 %) the brand column is still shown, the card top is 535 and `sw = cw`, so the same colour pairs apply — zoom changes type size, not the pairs | Pass |
| TC-257 | Reduced motion removes the launcher lift. `.diva-fab:hover` translates -2 px normally; `@media (prefers-reduced-motion: reduce)` and the app's `html[data-reduce-motion="on"]` both set `transform: none`. With the hover pseudo forced through CDP, the normal page measured `matrix(1, 0, 0, 1, 0, -1.906)` mid-transition and, with `prefers-reduced-motion: reduce` emulated, `transform: none` | Pass |
| TC-258 | The tour points at feedback and the spotlight lands on something visible. A step was added after the notifications step: target `[data-tour="feedback"]`, title **Feedback has a home**, body naming both entry points. Walking the real buttons produced 5 of 6 steps with the spotlight at 242 × 56, x 6, y 852, covering the 226 × 40 **Share feedback** button at x 14, y 860. The first run exposed a real bug: the sidebar is its own scroll container (`overflow-y: auto`, height `calc(100vh - 72px)`), so the old window-only scroll left the button 13 px below a 900 px fold and the spotlight pointed at empty space. `position(ensure)` now calls `node.scrollIntoView({ block: 'nearest' })` before measuring, which moves the sidebar. Both entry points were exercised: the sidebar **Share feedback** and the account-menu **Send feedback** each open the single `[data-feedback-layer]` dialog, and it closes again. Evidence: `docs/screenshots/phase9/tour-feedback-1440.png` | Pass |
| TC-259 | The contextual "Ask DIVA about this week" chip was **not** added, because the engine cannot answer it. DIVA returns the same logbook summary for three differently scoped questions — "What is the status of my week 12 entry?", "Has my week 12 entry been approved?" and "What do I still need to do on my logbook?" all returned "Your logbook has 12 of 12 weeks started: 9 approved, 1 waiting with your mentor and 1 returned for rework. Week 10 needs your attention first…". A chip promising *this week* would overpromise. The launcher already asks "What is my next step on this logbook?" on the logbook page, so a second surface would repeat the reachable answer. Decision recorded; no markup added | Pass — skipped with reason |
| TC-260 | Image provenance is written down. `docs/assets.md` tables all 17 files in `public/assets/img/` with pixels, bytes, the screen that uses them and how each was made; it states plainly that `campus-hero.jpg`, `campus-hero-768.jpg`, `campus-hero-1200.jpg` and `auth-campus.jpg` are generated illustrations, not photographs, and that `limkokwing-graduation.jpg` is no longer used. `README.md` links it from the source tree and from §10, and `docs/DEMO.md` adds a talking point at 0:00 for the caption | Pass |
| TC-261 | Nothing else moved. `php -l` passes on `layout.php` and `landing.php`; `node --check` passes on `app.js` and `sw.js`; `require app/routes.php` still returns 130 entries (11 public, 9 auth, 110 permission) and `app.css` balances 0 braces across 128,969 bytes. Supervisor sign-in returns 302 and `dashboard`, `supervisor/queue`, `supervisor/evaluation`, `diva` each return 200; student sign-in returns 200; the mentor launcher on `mentor/visits` reads `q=How+do+I+record+a+site+visit%3F&from=mentor%2Fvisits`. No schema, permission, route, seed or `config/config.php` change; no commit or push. Six captures sit in `docs/screenshots/phase9/`: landings at 1440 and 390, sign-in at 390, the placement launcher at 390, the prefilled DIVA composer at 390 and the tour's feedback step at 1440 | Pass |
