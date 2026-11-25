# Submission checklist

Use this before handing the work in and again on the day, from the copy that
will actually be submitted.

## 1. What the submission contains

| Item | Where | Check |
| --- | --- | --- |
| Report, editable | `REPORT/Digital_LEAP_Assignment3_Report.docx` | Opens in Word; 41 pages; contents page lists Appendix A before Conclusion and References |
| Report, printable | `REPORT/Digital_LEAP_Assignment3_Report.pdf` | Same page count as the DOCX; figures 2 and 3 show 33 tables |
| Figures | `REPORT/figures/` | Four PNGs, with matching `.svg` and `.drawio` |
| Application | `digital-leap/` | Runs with PHP 8.0+ and MariaDB 10.4+ |
| Codespaces demo | `.devcontainer/` | Builds PHP 8 + MariaDB and imports the seed; see `deploy/README.md` §4 |
| Schema and seed | `database/digital_leap.sql`, `database/migrations/` | Imports into a clean database; 33 tables |
| Install notes | `INSTALL.txt`, `README.md` | Demo accounts, Codespaces link and troubleshooting present |
| Marker's walkthrough | `docs/VERIFY.md` | Section 3 lists the six demo accounts |
| Test evidence | `docs/test-log.md` | Runs from TC-01 to TC-261 |
| Screenshots | `docs/screenshots/` | 128 files in `pwa/`, `diva-welcome.png` |
| Demo script | `docs/DEMO.md` | Eight minutes, six roles |
| UAT template | `docs/UAT-SIGNOFF.md` | Blank; signed by real testers only |
| Licence | `LICENSE` | MIT for code, CC BY 4.0 for report and documentation |

## 2. What must not be in it

- `digital-leap/config/config.php` — the local database password. The package
  ships `config.sample.php` only.
- `digital-leap/storage/logs/*.log` — mail and error logs.
- `REPORT/backup-*` and any `.tmp-*` folder.
- Personal photos or uploads made during testing that the seed data does not
  reference.

## 3. Build the package

From the project root, with the app stopped:

```bash
rm -f Digital_LEAP_Assignment3_Group_G.zip
zip -r Digital_LEAP_Assignment3_Group_G.zip \
  digital-leap database deploy .devcontainer docs REPORT README.md INSTALL.txt LICENSE \
  -x 'digital-leap/config/config.php' \
  -x 'digital-leap/storage/logs/*' \
  -x 'digital-leap/storage/cache/*' \
  -x 'REPORT/backup-*/*' \
  -x 'REPORT/*.txt' \
  -x '*/.DS_Store'
```

Then unzip it to a scratch folder once and confirm `digital-leap/config/` holds
only `config.sample.php`.

## 4. Prove it from clean

On a machine that has never run this project:

1. Copy `digital-leap/` into `htdocs`, import `database/digital_leap.sql` into a
   new database, copy `config.sample.php` to `config.php` with that database's
   credentials, start Apache and MySQL.
2. Sign in with each of the six demo accounts and open the dashboard.
3. Open **Guidebook** and download the PDF; it must be 281,848 bytes.
4. Open **My settings**, save a change, and see “Your settings are saved.”
5. Run `C:\xampp\php\php.exe bin\diva-eval.php --golden` inside `digital-leap`
   and confirm the exit status is 0.
6. Open `REPORT/Digital_LEAP_Assignment3_Report.pdf` and check that printed
   page 30 starts the landscape Appendix A, that Conclusion and References
   follow it on printed page 39, and that the figure captions match the images.

## 5. Flash drive

- Root folder named `COMP217_Group_G_Digital_LEAP/`, with the ZIP, the report
  PDF, the report DOCX and the presentation file (if any) at its top level.
- One `README.txt` at the root: group members, module, lecturer, and one line per
  file saying what it is. Keep it plain ASCII.
- Fat32 or exFAT, ejected safely; test the drive on a second machine.
- Do not put `config.php`, database dumps of live accounts, or personal phone
  photos on the drive.
- Label the drive physically with the group and the date.

## 6. Day-of checks

- [ ] Report PDF opens and the page count matches the DOCX.
- [ ] Appendix A appears in the contents page with a page number.
- [ ] The ZIP opens and contains no `config.php`.
- [ ] Demo accounts sign in on the presentation laptop (test the day before too).
- [ ] `docs/DEMO.md` rehearsed once with a stopwatch.
- [ ] Copies of the PDF on the drive and in the cloud/email as a fallback.
