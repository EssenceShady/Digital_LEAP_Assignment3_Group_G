# Digital LEAP Internship Management System — User Guide

**For:** students, host supervisors, academic mentors, LEAP coordinators, management/AQA staff and system administrators of the Digital LEAP internship programme.
**What this guide covers:** how to sign in and how to carry out each role's weekly work. Setup and demonstration accounts are in `INSTALL.txt`; the marker's walkthrough is in `VERIFY.md`.

---

## 1. Purpose, roles and signing in

Digital LEAP replaces the paper internship file with one record set: ranked placement
preferences, allocation, weekly logbooks, supervisor and mentor evaluation, the
internship report, the Career Fair report and the final mark out of 200. Every role
sees only its own part of that record; no permission grants access to another
person's file.

The six roles and what each is for:

| Role | What the role does in the system |
| --- | --- |
| Student | Applies for a placement with a CV, writes and submits weekly logbook entries, uploads the internship and Career Fair reports, completes a self evaluation, reads the published result. |
| Host supervisor | Reviews and approves or returns the weekly logbook entries of students placed at their organisation, comments on entries, and scores Form D. |
| Academic mentor | Reviews logbooks after the supervisor, records site visits, assesses the internship report, and sees the Career Fair submissions. |
| LEAP coordinator | Manages organisations and applications, allocates placements, runs site visits, computes, moderates and publishes marks. |
| Management / AQA | Reads programme KPIs, prints the management report and exports data. |
| System administrator | Creates and suspends accounts, resets passwords, maintains the permission matrix and settings, reads the audit trail and the feedback inbox, and processes queued mail. |

**Signing in.** Open the application address supplied with your installation and choose
**Sign in**, or go straight to `?r=login`. Enter your institutional email address and
password; passwords are case-sensitive, and the eye button beside the field reveals what
you have typed. A demonstration copy offers one-click buttons for each demo role.

A new account arrives with an initial password that must be changed at first sign-in: the
system redirects to **Profile** until the new one is saved. A password needs at least eight
characters, an uppercase letter, a lowercase letter and a number. Five wrong passwords in
a row lock the account for fifteen minutes; an administrator can unlock it.

**Finding your way around.** The left menu lists the pages your role may open; on a phone
it collapses into a drawer. The bell shows notifications and an inbox preview, and the
envelope opens placement messages. A short guided tour runs after registration and can be
repeated from **Take the tour**. Your name, role and photo sit at the top of the menu.

---

## 2. Student and host supervisor: the weekly logbook cycle

![DIVA welcome screen](screenshots/diva-welcome.png)

*The assistant welcome screen. DIVA is available to every role and answers from that
account's own records and from the internship guidebook.*

**Applying (student).** **Apply for internship** collects your ranked choices, each with
sector, district and motivation. Use **Move up** and **Move down** to reorder; the ranks
renumber immediately. Attach a CV as PDF, Word, JPG or PNG. Once the coordinator has
allocated a placement the preference list becomes read-only.

**Your placement and progress (student).** **My placement** shows the organisation, the
academic year and semester, your supervisor and mentor. **Placement letter** opens a
printable confirmation letter. The dashboard's **Internship progress** card tracks
placement, logbook, both evaluations, the site visit, the report and publication.

**Writing a logbook entry (student).** Open **Logbook** and choose the week. Record the
activities and learning, then **Save draft** while still working or **Submit** when ready.
A submitted entry cannot be edited; a supervisor may return it with a comment, and a
returned entry can be corrected and resubmitted. Attach evidence files and download them
later. Use **Reply** under a comment to answer in a threaded conversation. The official
16-page log book template opens or downloads from the same page.

**Reviewing entries (host supervisor).** **Queue** lists the entries waiting for you, newest
submission first. Narrow the list with the **Status** and **Week** filters at the top of the
list and press **Apply filters**; press **Reset** to clear them. Open an entry from the
queue to read the full week, its attachments and the conversation. **Approve** passes it to
the mentor; **Return** sends it back to the student and requires a comment saying what must
change. Comment on an approved entry at any time. The draft entries a student has not
submitted are never shown in the queue.

**Form D (host supervisor).** **Evaluation** scores the six workplace criteria with their
printed weights. The form cannot be saved half-finished, and the weights must total the
stated maximum. Once saved, the total becomes the Form D component — 40 of the 100
practical marks.

---

## 3. Mentor, coordinator, management and administrator: assessment and oversight

**The rest of the assessment chain.** The academic mentor opens **Logbook** to review
entries the supervisor has approved; a comment moves the entry to mentor-reviewed.
**Visits** records each site visit with its date, mode and outcome, and filters by student,
status and date range. **Reports** assesses the internship report (75 of the 200 marks) and
lists the Career Fair submissions with their timing; opening a file marks it reviewed. The
mentor's own evaluation (40/35/25) is recorded for information and carries no marks.

**Allocation (coordinator).** **Applications** lists active students with their ranked
preferences and whether a CV is attached; filter by name or CV status. **Placements**
filters by academic year, status and organisation. **Allocate** refuses a student who
already holds a placement for that year, a supervisor from another organisation, and an
allocation beyond the organisation's capacity.

**Marks (coordinator).** **Grades** shows each placement's components and running total.
The final mark is out of 200: Practical Internship 100 (logbook 50, Form D 40, self
evaluation 10) plus Internship Report 75 plus Career Fair 25. Enter the Career Fair score,
then **Compute**, **Moderate** if justified, and **Publish**. A published mark cannot be
recomputed. The grade sheet prints from the same page.

**Oversight (management/AQA and administrator).** Management sees placement, completion,
grade distribution and logbook compliance, each bar labelled with its count and share,
and can print the report or export CSV. The administrator maintains users (create,
suspend, unlock, reset password), the 39-permission matrix, the assessment weights and
session settings, the audit trail and the feedback inbox. Audit filters by action, entity,
user and date range; feedback filters by rating and source page. Every filter is a plain
GET form, so a filtered view can be bookmarked and never changes a record.

**Profile photo.** **Profile** accepts a JPG, PNG or WebP image up to 8 MB, previews it in
the header before saving, and can remove it again. Where no photo exists the person's
initials are shown. A photo is served only to someone who could already see that person in
the product — yourself, staff with directory access, or a participant in the same
placement, conversation or logbook thread.

**Messages.** Students, mentors and coordinators have placement conversations with quoted
replies, reactions, unread counts and a fifteen-minute edit window. A student can message
their own mentor and coordinator; students cannot message one another, and a deleted
message leaves a tombstone so replies keep their place.

**DIVA.** Every role has the assistant in the left menu. It answers in three ways and names
which one it used: fixed rules over your own live records, a quoted passage from the
internship guidebook with its page number, or an honest statement that it cannot help. It
can describe your records, explain its own limits, and comment on a report draft against
the eight Form E criteria without giving a mark. It cannot approve work, publish a grade or
send a message, and it says so when asked. Conversations are saved to your own account and
can be reopened or archived; archiving hides a conversation without deleting it. Answers
are snapshots, so ask again for current figures.

---

*Screenshots accompanying this guide are held in `docs/screenshots/`. Only the welcome
screen shown above exists in the current package; add the remaining role screenshots to
that folder before printing the guide.*
