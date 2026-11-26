# Eight-minute demonstration script

A six-role walkthrough for the exhibition and the assessment demo: 8 minutes of
speaking, one laptop, no network needed. Every step below names a seeded account,
a page and the thing on screen that proves the point.

## Before you start

- Apache and MySQL running; the app open at
  `http://localhost/Digital_LEAP_Assignment3_Group_G/digital-leap/`.
- Browser at 1440 px wide, zoom 100%, one tab, no bookmarks bar.
- Signed out. The database is the seeded one (see `INSTALL.txt`).
- Two windows ready: the app, and a plain text file named `demo.txt` for the
  offline step.
- Decide who clicks and who speaks. The clicker rehearses once with a stopwatch.

| Role | Email | Password |
| --- | --- | --- |
| Student | mohamedalie247@gmail.com | Student@1234 |
| Host supervisor | supervisor@brightfuture.sl | Super@1234 |
| Academic mentor | deborahjulietmoiforay@gmail.com | Mentor@1234 |
| Coordinator | bayohmusa001@gmail.com | Musa@2026 |
| Management / AQA | fatmatabfc@gmail.com | Fatmata@2026 |
| Administrator | sesayshadrachsaidu@gmail.com | Shadrach@2026 |

The sign-in page offers all six as one-click demo buttons, so no typing on stage.

## The run sheet

### 0:00 – 0:40 · Opening (40 s)

- **Show:** the public landing page. Say what the system does in one sentence:
  it digitises the Digital LEAP internship from ranked preference to published
  mark. A cold tab opens with the boot splash: the supplied flag globe turns as
  a coin while the meter fills, then the landing appears.
- **Say:** “Everything you will see runs on this laptop. No external API, no
  cloud service, no account to create.”
- **If asked about the photo:** the flag-waving crowd on the landing page and
  the graduation scene on the sign-in page are the team's own photographs,
  captioned “Students at Limkokwing University, Sierra Leone” and “Limkokwing
  graduation”. The globe on the splash and the seal on the photo are the
  owner's artwork. `docs/assets.md` lists where every image came from.
- **Click:** **Student** on the sign-in demo buttons. Land on the dashboard.

### 0:40 – 2:10 · Student (90 s)

- **Dashboard:** point at the **Internship progress** card — placement, logbook,
  both evaluations, site visit, report, publication. “One glance answers ‘where
  am I up to?’.”
- **Logbook (Form E):** open week 12. Show the **Daily record** table with the
  week total, then press **Save draft**. “The paper log book, kept.”
- **Apply / Preferences:** open the five ranked choices. Move one up, show the
  ranks swap. “Allocation starts from the student's own order.”
- **Ask DIVA:** ask *“When is my next logbook entry due?”* — badge **Rules &
  records**. Then ask *“What are the objectives of LEAP?”* — the answer opens
  **Quoted from the internship guidebook, page 4:** with the passage first.
  “The assistant quotes before it explains, and refuses when it has nothing.”
- **Guidebook:** open **Guidebook**, press **Open the PDF** — the real 25-page
  guidebook opens in the browser's PDF viewer, not a text copy.
- **My placement:** press **Check in**. The card shows **Checked in at HH:MM**;
  with a site pin set it adds the distance from that pin, and the **Site pin**
  row says whether the coordinator has set one. “Location is read on a button
  press only — nothing tracks in the background.” The full mechanism is in
  `docs/GPS-GUIDE.md` if a marker asks what proves presence.

### 2:10 – 3:05 · Host supervisor (55 s)

- Sign in as supervisor (**Sign out** first).
- **Logbook queue:** set **Status** to *All submitted work* and press **Apply
  filters**; choose a **Week**. “Filters are bound parameters, not string
  concatenation.”
- Open week 11: show the three-way thread, then **Approve**. “The student and
  mentor are notified; the audit log records who approved what and when.”
- **Form D evaluation:** show the six criteria and the 15/25/20/15/10/15 weights.
  Save is refused while a field is empty.

### 3:05 – 3:50 · Academic mentor (45 s)

- Sign in as mentor.
- **Students:** search `Mohamed`, status *Active* — one row remains. “The
  organisation list only holds organisations this mentor is placed with.”
- **Visits:** show one completed and two scheduled, filtered by date range. Each
  row carries its GPS proof — **Site verified** with the distance, or **Not
  checked in** while a physical visit is still pending. “A physical visit cannot
  be completed until the mentor checks in at the site, within 500 m of the pin.”
- **Report assessment (Form C):** open Mohamed's report and show the assessment
  criteria; **Mentor evaluation** is advisory and carries no marks.

### 3:50 – 5:20 · Coordinator (90 s)

- Sign in as coordinator.
- **Allocate:** try allocating student 5 again for 2026/2027 — the duplicate is
  refused. Try a supervisor from another organisation — refused. “Capacity and
  ownership are enforced by the database and the controller.”
- **Visits:** every mentor visit's proof, then **Staff site visits** on a
  placement — “Coordinators record their own monitoring check-in here, and it is
  attributed by name, role, time and distance, exactly like the mentor's.”
- **Grades (Form E):** open placement 2. Logbook, Form D, self, report and
  Career Fair columns are visible; enter the Career Fair score, **Compute**,
  **Moderate**, **Publish**. Point at **Final mark 114.50 / 200 = 57.25%, grade
  D**. “A published mark cannot be recomputed; every change is audited.”
- **Printable sheet** and **CSV export** download.

### 5:20 – 6:05 · Management / AQA (45 s)

- Sign in as management.
- **KPIs:** placement, completion, grade distribution and twelve-week logbook
  compliance. Every bar carries its count and share. “This is the page for the
  quality assurance file.”
- **Print** opens the printable management report; **Export** saves the CSV.

### 6:05 – 6:55 · Administrator (50 s)

- Sign in as administrator.
- **Audit:** filter by action `mark.publish` (or entity *mark*). “Every state
  change above is a row here with before and after values.”
- **Roles:** show the permission matrix — 39 permissions across six roles.
- **Settings:** show the practical split enforced at exactly 100.00%, and that
  Report 75 and Career Fair 25 sit outside it.
- **Feedback:** **Send feedback** sits in the account menu and **Share feedback**
  at the foot of the sidebar on every signed-in page; this is the inbox. The
  floating corner button is DIVA, not feedback.

### 6:55 – 7:40 · Offline, settings and honesty (45 s)

- Still signed in. Open **My settings**: tick a notification family off, show
  “Your settings are saved.”, and that nothing disappears from the bell.
- Open DevTools → Network → **Offline** (or stop Apache).
- Open the logbook week 12, edit a day row and press **Save draft**. Show the
  sync strip: **1 change waiting**.
- Ask DIVA a guidebook question — the badge adds **on this device**.
- Go back online. The change drains and the strip reports it synced. “The
  offline copy is the student's, on their device; the server stays the
  authority.”

### 7:40 – 8:00 · Close (20 s)

- **Say:** “Six roles, one schema, 33 tables, 130 routes and 267 logged test
  cases. It is honest about what it does not do: no external AI, no email
  gateway in this build, and accessibility is a stated gap, not a claim.”

## If something fails on the day

| Symptom | Do this |
| --- | --- |
| Page 500s | Check Apache and MySQL are running; reload once |
| Sign-in rejected | Use the one-click demo buttons, not typed passwords |
| Offline step hangs | Skip it; show **My settings → This device** instead |
| DIVA gives no answer | Ask “What is DIVA?”; it always answers about itself |
| Time is short | Cut the mentor and management beats, keep student, coordinator and admin |

Do not delete records, reset passwords or change settings that the next group
needs. After the demo, sign out.
