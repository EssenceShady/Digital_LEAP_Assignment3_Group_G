# User acceptance test — sign-off template

Fill this in with real people and real dates. Do not pre-fill a result, a name or
a signature: an unsigned or invented sign-off is worse than none. If a scenario
fails, record it in the defect log rather than marking it passed.

## 1. Record

| Item | Value |
| --- | --- |
| Build tested (date) | |
| Report revision / ZIP name | |
| Machine and OS | |
| Browser and version | |
| Database | MariaDB version, seeded from `database/digital_leap.sql` (yes / no) |
| Tester's relationship to the team | e.g. fellow student, lecturer, staff member |

Instructions to each tester: use the seeded accounts in `INSTALL.txt` (or the
one-click demo buttons), work through the scenarios for your role, and record
**Pass**, **Fail** or **Not tested** with one line of evidence. Screenshot or
note the page you were on.

## 2. Scenarios

### Student (`mohamedalie247@gmail.com`)

| ID | Scenario | Expected | Result | Evidence / notes |
| --- | --- | --- | --- | --- |
| UAT-01 | Sign in and open the dashboard | Internship progress card shows the current stage | | |
| UAT-02 | Reorder one ranked preference | The two rows swap rank with no error | | |
| UAT-03 | Open week 12 of the logbook and save a day row | Row and week total return on reopening | | |
| UAT-04 | Submit a week with no day rows | Refused with a clear message | | |
| UAT-05 | Ask DIVA “What are the objectives of LEAP?” | Answer opens with a guidebook quotation and page number | | |
| UAT-06 | Open the guidebook PDF from the Guidebook page | The published PDF opens in the browser's viewer | | |
| UAT-07 | Change a notification family in My settings and save | Confirmation shown; bell entries are unaffected | | |

### Host supervisor (`supervisor@brightfuture.sl`)

| ID | Scenario | Expected | Result | Evidence / notes |
| --- | --- | --- | --- | --- |
| UAT-08 | Filter the logbook queue by status and week | Only matching submitted entries remain | | |
| UAT-09 | Return an entry without a comment | Refused | | |
| UAT-10 | Approve an entry after commenting | Status changes; student and mentor are notified | | |
| UAT-11 | Save an incomplete Form D | Refused until every criterion is scored | | |

### Academic mentor (`deborahjulietmoiforay@gmail.com`)

| ID | Scenario | Expected | Result | Evidence / notes |
| --- | --- | --- | --- | --- |
| UAT-12 | Search students with a placement-status filter | Only this mentor's matching placements remain | | |
| UAT-13 | Filter visits by a date range | Matching visits only; an invalid date does not break the page | | |
| UAT-14 | Review an approved logbook entry | Status becomes mentor-reviewed and the student is notified | | |

### Coordinator (`bayohmusa001@gmail.com`)

| ID | Scenario | Expected | Result | Evidence / notes |
| --- | --- | --- | --- | --- |
| UAT-15 | Allocate an already-placed student to a second placement | Refused as a duplicate | | |
| UAT-16 | Allocate a supervisor from another organisation | Refused | | |
| UAT-17 | Enter a Career Fair score, compute, moderate and publish | Final mark out of 200 with percentage and grade | | |
| UAT-18 | Try to recompute a published mark | Refused | | |
| UAT-19 | Download the printable grade sheet and the CSV | Both open or save without error | | |

### Management / AQA (`fatmatabfc@gmail.com`)

| ID | Scenario | Expected | Result | Evidence / notes |
| --- | --- | --- | --- | --- |
| UAT-20 | Open the KPI page | Charts show counts and shares, all twelve weeks present | | |
| UAT-21 | Export the placements CSV | File downloads and opens in a spreadsheet | | |

### Administrator (`sesayshadrachsaidu@gmail.com`)

| ID | Scenario | Expected | Result | Evidence / notes |
| --- | --- | --- | --- | --- |
| UAT-22 | Create a user whose password is `password` | Refused by the password policy | | |
| UAT-23 | Filter the audit trail by action and actor | Rows match both filters; pagination keeps them | | |
| UAT-24 | Set the practical weights to 60/40/10 | Refused with the total shown; restoring 50/40/10 saves | | |

### Any role — offline

| ID | Scenario | Expected | Result | Evidence / notes |
| --- | --- | --- | --- | --- |
| UAT-25 | Go offline, edit a logbook day row, save | Change is queued and the sync strip shows it waiting | | |
| UAT-26 | Go back online | The queued change syncs once and the strip clears | | |

## 3. Defect log

| Defect | Scenario ID | What happened | Severity (blocker / major / minor) | Reported to | Status |
| --- | --- | --- | --- | --- | --- |
| 1 | | | | | |
| 2 | | | | | |
| 3 | | | | | |

## 4. Sign-off

Each tester signs only for their own role and their own scenarios.

| Role | Tester name | Relationship to team | Date | Signature |
| --- | --- | --- | --- | --- |
| Student | | | | |
| Host supervisor | | | | |
| Academic mentor | | | | |
| Coordinator | | | | |
| Management / AQA | | | | |
| Administrator | | | | |

### Acceptance statement

I have tested the scenarios listed for my role on the build named in section 1.
The results and any defects are recorded above.

Coordinator / group representative:

Name: ................................................  Date: ................

Signature: ............................................

> Where the criteria or the university require a witness, the lecturer or
> supervisor signs here:
>
> Name: ................................................  Date: ................
>
> Signature: ............................................
