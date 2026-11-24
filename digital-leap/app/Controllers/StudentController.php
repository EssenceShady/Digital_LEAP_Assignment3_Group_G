<?php
declare(strict_types=1);

namespace Leap\Controllers;

use Leap\Core\Audit;
use Leap\Core\Auth;
use Leap\Core\Database;
use Leap\Core\Guard;
use Leap\Core\Notify;
use Leap\Core\Upload;
use Leap\Core\Validator;
use Leap\Core\View;
use Leap\Services\Attendance;
use Leap\Services\LogbookDigest;
use RuntimeException;

final class StudentController
{
    public function __construct()
    {
        Guard::requireRole('student');
    }

    public function preferences(): void
    {
        $student = $this->student();
        $year = $this->academicYear();
        $prefs = Database::all(
            'SELECT a.*, o.name AS org_name, o.district, o.sector, o.capacity, o.mou_status
             FROM application a JOIN organisation o ON o.org_id = a.org_id
             WHERE a.student_id = ? AND a.academic_year = ?
             ORDER BY a.preference_rank',
            [(int) $student['student_id'], $year]
        );
        $used = array_map(static fn (array $p): int => (int) $p['org_id'], $prefs);
        $orgs = Database::all(
            "SELECT org_id, name, district, sector, capacity, mou_status
             FROM organisation
             WHERE status = 'active' AND mou_status IN ('signed', 'pending')
             ORDER BY name"
        );
        $available = array_values(array_filter(
            $orgs,
            static fn (array $o): bool => !in_array((int) $o['org_id'], $used, true)
        ));
        $submitted = $prefs !== [] && !in_array('draft', array_column($prefs, 'status'), true);
        $placement = $this->placementFor((int) $student['student_id']);
        $cv = Database::one(
            "SELECT d.*, rv.first_name AS reviewer_first, rv.last_name AS reviewer_last
             FROM document d
             LEFT JOIN users rv ON rv.user_id = d.reviewed_by
             WHERE d.student_id = ? AND d.doc_type = 'cv'
             ORDER BY d.uploaded_at DESC LIMIT 1",
            [(int) $student['student_id']]
        );

        View::render('student/apply', [
            'title' => 'Apply for an internship',
            'student' => $student,
            'prefs' => $prefs,
            'orgs' => $available,
            'submitted' => $submitted,
            'placement' => $placement,
            'cv' => $cv,
            'year' => $year,
        ]);
    }

    public function addPreference(): void
    {
        $student = $this->student();
        $year = $this->academicYear();
        $orgId = (int) \post('org_id', 0);
        $motivation = trim((string) \post('motivation', ''));

        if (Database::one('SELECT placement_id FROM placement WHERE student_id = ? AND academic_year = ?', [(int) $student['student_id'], $year]) !== null) {
            \flash('error', 'A placement has already been allocated for this academic year.');
            \redirect('student/apply');
        }

        $count = (int) Database::val(
            'SELECT COUNT(*) FROM application WHERE student_id = ? AND academic_year = ?',
            [(int) $student['student_id'], $year]
        );
        if ($count >= 5) {
            \flash('error', 'You may list a maximum of five preferences.');
            \redirect('student/apply');
        }

        $org = Database::one('SELECT org_id, name FROM organisation WHERE org_id = ? AND status = ?', [$orgId, 'active']);
        if ($org === null) {
            \flash('error', 'Choose a host organisation from the list.');
            \redirect('student/apply');
        }
        if (!Validator::length($motivation, 10, 600)) {
            \flash('error', 'Write at least ten characters explaining why this organisation fits your programme.');
            \redirect('student/apply');
        }

        $duplicate = Database::val(
            'SELECT COUNT(*) FROM application WHERE student_id = ? AND org_id = ? AND academic_year = ?',
            [(int) $student['student_id'], $orgId, $year]
        );
        if ((int) $duplicate > 0) {
            \flash('error', 'That organisation is already in your preference list.');
            \redirect('student/apply');
        }

        Database::tx(static function () use ($student, $org, $year, $count, $motivation): void {
            $id = Database::insert('application', [
                'student_id' => (int) $student['student_id'],
                'org_id' => (int) $org['org_id'],
                'preference_rank' => $count + 1,
                'motivation' => $motivation,
                'status' => 'draft',
                'academic_year' => $year,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            Audit::log('application.preference_added', 'application', $id, null, [
                'org' => $org['name'],
                'rank' => $count + 1,
            ]);
        });

        \flash('success', 'Preference added. Use the arrows to reorder, then submit the list for review.');
        \redirect('student/apply');
    }

    public function removePreference(): void
    {
        $student = $this->student();
        $id = (int) \post('application_id', 0);
        $year = $this->academicYear();
        $row = Database::one(
            'SELECT * FROM application WHERE application_id = ? AND student_id = ? AND academic_year = ?',
            [$id, (int) $student['student_id'], $year]
        );
        if ($row === null) {
            Audit::log('application.denied', 'application', $id);
            http_response_code(403);
            View::error(403);
            return;
        }
        if ($row['status'] !== 'draft') {
            \flash('error', 'A submitted preference list cannot be edited. Contact the internship coordinator.');
            \redirect('student/apply');
        }

        Database::tx(static function () use ($row, $student, $year): void {
            Database::q('DELETE FROM application WHERE application_id = ?', [(int) $row['application_id']]);
            Audit::log('application.preference_removed', 'application', (int) $row['application_id'], $row, null);
            $left = Database::all(
                'SELECT application_id FROM application WHERE student_id = ? AND academic_year = ? ORDER BY preference_rank',
                [(int) $student['student_id'], $year]
            );
            $rank = 1;
            foreach ($left as $item) {
                Database::update('application', ['preference_rank' => $rank], 'application_id = ?', [(int) $item['application_id']]);
                $rank++;
            }
        });

        \flash('success', 'Preference removed.');
        \redirect('student/apply');
    }

    public function movePreference(): void
    {
        $student = $this->student();
        $id = (int) \post('application_id', 0);
        $direction = (string) \post('direction', 'up');
        $year = $this->academicYear();
        $row = Database::one(
            'SELECT * FROM application WHERE application_id = ? AND student_id = ? AND academic_year = ?',
            [$id, (int) $student['student_id'], $year]
        );
        if ($row === null || $row['status'] !== 'draft') {
            \flash('error', 'That preference cannot be moved.');
            \redirect('student/apply');
        }
        $neighbour = Database::one(
            'SELECT * FROM application WHERE student_id = ? AND academic_year = ? AND status = ? AND preference_rank ' . ($direction === 'up' ? '<' : '>') . ' ? ORDER BY preference_rank ' . ($direction === 'up' ? 'DESC' : 'ASC') . ' LIMIT 1',
            [(int) $student['student_id'], $year, 'draft', (int) $row['preference_rank']]
        );
        if ($neighbour === null) {
            \redirect('student/apply');
        }

        Database::tx(static function () use ($row, $neighbour): void {
            $own = (int) $row['preference_rank'];
            $other = (int) $neighbour['preference_rank'];
            Database::update('application', ['preference_rank' => 0], 'application_id = ?', [(int) $row['application_id']]);
            Database::update('application', ['preference_rank' => $own], 'application_id = ?', [(int) $neighbour['application_id']]);
            Database::update('application', ['preference_rank' => $other], 'application_id = ?', [(int) $row['application_id']]);
            Audit::log('application.reordered', 'application', (int) $row['application_id'], [
                'rank' => $own,
            ], [
                'rank' => $other,
            ]);
        });

        \redirect('student/apply');
    }

    public function submitPreferences(): void
    {
        $student = $this->student();
        $year = $this->academicYear();
        $drafts = Database::all(
            'SELECT a.application_id, a.preference_rank, o.name
             FROM application a JOIN organisation o ON o.org_id = a.org_id
             WHERE a.student_id = ? AND a.academic_year = ? AND a.status = ?',
            [(int) $student['student_id'], $year, 'draft']
        );
        if ($drafts === []) {
            \flash('error', 'There are no draft preferences to submit.');
            \redirect('student/apply');
        }

        Database::tx(static function () use ($drafts, $student, $year): void {
            foreach ($drafts as $draft) {
                Database::update('application', [
                    'status' => 'submitted',
                    'submitted_at' => date('Y-m-d H:i:s'),
                ], 'application_id = ?', [(int) $draft['application_id']]);
            }
            Audit::log('application.preferences_submitted', 'student', (int) $student['student_id'], null, [
                'year' => $year,
                'ranked' => array_column($drafts, 'name'),
            ]);
            Notify::toRole(
                'coordinator',
                'application',
                'Preference list submitted',
                $student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['reg_no'] . ') submitted ' . count($drafts) . ' ranked host preferences for ' . $year . '.',
                'index.php?r=coordinator/applications'
            );
        });

        \flash('success', 'Preferences submitted for review. You will be notified when a placement is allocated.');
        \redirect('student/apply');
    }

    public function placement(): void
    {
        $student = $this->student();
        $placement = $this->placementFor((int) $student['student_id']);
        $timeline = [];
        $docs = [];
        $report = null;
        $attendance = null;

        if ($placement !== null) {
            $timeline = Database::all(
                'SELECT * FROM audit_log WHERE entity_type = ? AND entity_id = ? ORDER BY created_at, audit_id',
                ['placement', (int) $placement['placement_id']]
            );
            $docs = Database::all(
                'SELECT d.*, rv.first_name AS reviewer_first, rv.last_name AS reviewer_last
                 FROM document d LEFT JOIN users rv ON rv.user_id = d.reviewed_by
                 WHERE d.placement_id = ? ORDER BY d.uploaded_at DESC',
                [(int) $placement['placement_id']]
            );
            $report = Database::one(
                'SELECT * FROM internship_report WHERE placement_id = ?',
                [(int) $placement['placement_id']]
            );
            $attendance = [
                'today' => Attendance::today((int) $placement['placement_id'], (int) Auth::id()),
                'recent' => Attendance::recent((int) $placement['placement_id'], 8, (int) Auth::id()),
                'org' => Attendance::orgPoint((int) $placement['org_id']),
                'active' => $placement['status'] === 'active',
            ];
        }

        View::render('student/placement', [
            'title' => 'My placement',
            'student' => $student,
            'placement' => $placement,
            'timeline' => $timeline,
            'docs' => $docs,
            'report' => $report,
            'attendance' => $attendance,
        ]);
    }

    public function attendance(): void
    {
        $student = $this->student();
        $placement = $this->placementFor((int) $student['student_id']);
        if ($placement === null) {
            \flash('error', 'You do not have a placement yet.');
            \redirect('student/placement');
        }
        if ($placement['status'] !== 'active') {
            \flash('error', 'Attendance can only be recorded while your placement is active.');
            \redirect('student/placement', [], 'attendance');
        }

        $type = (string) \post('type', '');
        $lat = null;
        $lng = null;
        $accuracy = null;
        if (\post('lat') !== null && \post('lat') !== '') {
            $lat = (float) \post('lat');
            $lng = (float) \post('lng');
            if (\post('accuracy') !== null && \post('accuracy') !== '') {
                $accuracy = (float) \post('accuracy');
            }
        }

        $result = Attendance::record((int) Auth::id(), $placement, $type, $lat, $lng, $accuracy);
        \flash($result['ok'] ? 'success' : 'error', $result['message']);
        \redirect('student/placement', [], 'attendance');
    }

    public function sendDay(): void
    {
        $student = $this->student();
        $id = (int) \post('entry_id', 0);
        $workDate = trim((string) \post('work_date', ''));
        $entry = $this->ownEntry((int) $student['student_id'], $id);
        if ($entry === null) {
            \flash('error', 'That entry is not yours.');
            \redirect('student/logbook');
        }
        if (!Validator::date($workDate)) {
            \flash('error', 'Choose a recorded day to send.');
            \redirect('student/logbook/view', ['id' => $id]);
        }

        $result = LogbookDigest::sendDay((int) $entry['entry_id'], $workDate);
        \flash($result['ok'] ? 'success' : 'error', $result['message']);
        \redirect('student/logbook/view', ['id' => $id], 'days');
    }

    public function logbook(): void
    {
        $student = $this->student();
        $placement = $this->placementFor((int) $student['student_id']);
        $entries = [];
        $due = null;
        $nextWeek = null;
        $dueDate = null;

        if ($placement !== null) {
            $entries = Database::all(
                'SELECT * FROM logbook_entry WHERE placement_id = ? ORDER BY week_no',
                [(int) $placement['placement_id']]
            );
            $byWeek = [];
            foreach ($entries as $entry) {
                $byWeek[(int) $entry['week_no']] = $entry;
            }
            for ($w = 1; $w <= (int) $placement['total_weeks']; $w++) {
                if (!isset($byWeek[$w])) {
                    $nextWeek = $w;
                    break;
                }
                if (in_array($byWeek[$w]['status'], ['draft', 'returned'], true) && $nextWeek === null) {
                    $nextWeek = $w;
                    break;
                }
            }
            if ($nextWeek !== null) {
                $start = strtotime((string) $placement['start_date'] . ' +' . ($nextWeek - 1) . ' weeks');
                $dueDate = date('Y-m-d', strtotime(date('Y-m-d', $start) . ' +4 days'));
            }
            $due = (string) Database::val('SELECT setting_value FROM setting WHERE setting_key = ?', ['logbook_due_day']);
        }

        View::render('student/logbook', [
            'title' => 'Weekly logbook',
            'student' => $student,
            'placement' => $placement,
            'entries' => $entries,
            'nextWeek' => $nextWeek,
            'dueDate' => $dueDate,
            'dueDay' => $due,
        ]);
    }

    public function entryForm(): void
    {
        $student = $this->student();
        $placement = $this->placementFor((int) $student['student_id']);
        if ($placement === null) {
            \flash('error', 'You need an approved placement before you can write a logbook entry.');
            \redirect('student/placement');
        }
        if ($placement['status'] !== 'active') {
            \flash('error', 'Logbook entries can only be written while your placement is active.');
            \redirect('student/logbook');
        }

        $week = (int) \qget('week', 0);
        $existing = null;
        if ($week > 0) {
            $existing = Database::one(
                'SELECT * FROM logbook_entry WHERE placement_id = ? AND week_no = ?',
                [(int) $placement['placement_id'], $week]
            );
            if ($existing !== null && !in_array($existing['status'], ['draft', 'returned'], true)) {
                \flash('error', 'Week ' . $week . ' has already been submitted and can no longer be edited.');
                \redirect('student/logbook');
            }
        }
        if ($existing === null) {
            $used = array_map(
                static fn (array $e): int => (int) $e['week_no'],
                Database::all('SELECT week_no FROM logbook_entry WHERE placement_id = ?', [(int) $placement['placement_id']])
            );
            for ($w = 1; $w <= (int) $placement['total_weeks']; $w++) {
                if (!in_array($w, $used, true)) {
                    $week = $w;
                    break;
                }
            }
        }

        $base = date('Y-m-d', strtotime((string) $placement['start_date'] . ' +' . (max(0, $week - 1) * 7) . ' days'));

        $stored = [];
        if ($existing !== null) {
            foreach (Database::all(
                'SELECT work_date, tasks, hours FROM logbook_day WHERE entry_id = ? ORDER BY work_date',
                [(int) $existing['entry_id']]
            ) as $row) {
                $stored[(string) $row['work_date']] = $row;
            }
        }

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime($base . ' +' . $i . ' days'));
            $row = $stored[$date] ?? null;
            unset($stored[$date]);
            if ($date < $placement['start_date'] || $date > $placement['end_date']) {
                continue;
            }
            $days[] = [
                'date' => $date,
                'tasks' => (string) ($row['tasks'] ?? ''),
                'hours' => $row['hours'] ?? null,
            ];
        }
        foreach ($stored as $date => $row) {
            $days[] = ['date' => (string) $date, 'tasks' => (string) $row['tasks'], 'hours' => $row['hours']];
        }

        View::render('student/entry_form', [
            'title' => 'Logbook entry',
            'placement' => $placement,
            'entry' => $existing,
            'week' => $week,
            'days' => $days,
        ]);
    }

    public function entrySave(): void
    {
        $input = $_POST;
        $submit = \post('submit') !== null;
        $result = $this->persistEntry($input, $submit);

        if (!$result['ok']) {
            foreach ($result['errors'] as $message) {
                \flash('error', $message);
            }
            $id = (int) ($input['entry_id'] ?? 0);
            $week = (int) ($input['week_no'] ?? 0);
            \redirect('student/logbook/new' . ($id > 0 ? '&week=' . $week : ''));
        }

        \flash('success', $submit ? 'Logbook week submitted to your host supervisor.' : 'Draft saved.');
        \redirect('student/logbook');
    }

    /**
     * Validate and write one weekly entry from a field map.
     * The browser form and the offline sync endpoint both call this, so a
     * queued offline entry goes through exactly the same rules.
     *
     * @return array{ok: bool, errors: string[], entry_id?: int, week?: int, status?: string}
     */
    public function persistEntry(array $input, bool $submit): array
    {
        $student = $this->student();
        $placement = $this->placementFor((int) $student['student_id']);
        if ($placement === null || $placement['status'] !== 'active') {
            return ['ok' => false, 'errors' => ['Your placement is not active.']];
        }

        $id = (int) ($input['entry_id'] ?? 0);
        $week = (int) ($input['week_no'] ?? 0);
        $weekBase = date('Y-m-d', strtotime((string) $placement['start_date'] . ' +' . (max(0, $week - 1) * 7) . ' days'));
        $from = $weekBase;
        $to = date('Y-m-d', strtotime($weekBase . ' +6 days'));
        $activities = trim((string) ($input['activities'] ?? ''));
        $skills = trim((string) ($input['technical_skills'] ?? ''));
        $challenges = trim((string) ($input['challenges'] ?? ''));
        $meetingNotes = trim((string) ($input['meeting_notes'] ?? ''));
        $reflection = trim((string) ($input['reflection'] ?? ''));

        $dayDates = (array) ($input['day_date'] ?? []);
        $dayTasks = (array) ($input['day_tasks'] ?? []);
        $dayHours = (array) ($input['day_hours'] ?? []);
        $days = [];
        $totalHours = 0.0;
        $hasHours = false;

        $errors = [];
        if (!Validator::int($week, 1, (int) $placement['total_weeks'])) {
            $errors[] = 'Week number must be between 1 and ' . (int) $placement['total_weeks'] . '.';
        }

        foreach ($dayDates as $i => $rawDate) {
            $date = trim((string) $rawDate);
            $tasks = trim((string) ($dayTasks[$i] ?? ''));
            $rawHours = trim((string) ($dayHours[$i] ?? ''));
            if ($tasks === '' && $rawHours === '') {
                continue;
            }
            $label = Validator::date($date) ? fmt_date($date) : 'Row ' . ((int) $i + 1);
            if (!Validator::date($date)) {
                $errors[] = 'Give every working day a valid date.';
                continue;
            }
            if ($date < $placement['start_date'] || $date > $placement['end_date']) {
                $errors[] = $label . ': the date must fall inside your placement period.';
            }
            if (!Validator::length($tasks, 3, 1000)) {
                $errors[] = $label . ': describe the tasks in at least three characters.';
            }
            if (isset($days[$date])) {
                $errors[] = $label . ' appears twice.';
                continue;
            }
            $hours = null;
            if ($rawHours !== '') {
                if (!Validator::decimal($rawHours, 0, 24)) {
                    $errors[] = $label . ': hours must be between 0 and 24.';
                    continue;
                }
                $hours = (float) $rawHours;
                $totalHours += $hours;
                $hasHours = true;
            }
            $days[$date] = ['date' => $date, 'tasks' => $tasks, 'hours' => $hours];
        }

        if ($days !== []) {
            $dates = array_keys($days);
            sort($dates);
            $from = (string) $dates[0];
            $to = (string) $dates[count($dates) - 1];
        }

        if (!Validator::date($from) || !Validator::date($to)) {
            $errors[] = 'Enter valid dates for the week.';
        } elseif ($from < $placement['start_date'] || $to > $placement['end_date']) {
            $errors[] = 'The entry dates must fall inside your placement period.';
        } elseif ($to < $from) {
            $errors[] = 'The end date cannot be before the start date.';
        }
        if (!Validator::length($activities, 20, 3000)) {
            $errors[] = 'Write the weekly summary in at least twenty characters.';
        }
        foreach ([[$skills, 'technical skills'], [$challenges, 'challenges'], [$meetingNotes, 'meeting notes'], [$reflection, 'reflection']] as [$text, $name]) {
            if (mb_strlen($text) > 2000) {
                $errors[] = 'The ' . $name . ' section is limited to 2000 characters.';
            }
        }

        $hours = $hasHours ? round($totalHours, 1) : null;
        if ($submit && $days === []) {
            $errors[] = 'Record at least one working day before submitting.';
        }
        if ($submit && $hours === null) {
            $errors[] = 'Enter the hours worked for at least one day.';
        } elseif ($hours !== null && ($hours < 0.5 || $hours > 60)) {
            $errors[] = 'Total hours for the week must be between 0.5 and 60.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $entryId = Database::tx(static function () use ($id, $week, $from, $to, $activities, $skills, $challenges, $meetingNotes, $reflection, $hours, $days, $submit, $placement, $student): int {
            $now = date('Y-m-d H:i:s');
            $id = (int) $id;
            if ($id > 0) {
                $row = Database::one(
                    'SELECT * FROM logbook_entry WHERE entry_id = ? AND placement_id = ?',
                    [$id, (int) $placement['placement_id']]
                );
                if ($row === null) {
                    throw new RuntimeException('Entry not found for this placement.');
                }
                if (!in_array($row['status'], ['draft', 'returned'], true)) {
                    throw new RuntimeException('Only draft or returned entries can be edited.');
                }
                $before = $row;
                $data = [
                    'week_no' => $week,
                    'date_from' => $from,
                    'date_to' => $to,
                    'activities' => $activities,
                    'technical_skills' => $skills !== '' ? $skills : null,
                    'challenges' => $challenges !== '' ? $challenges : null,
                    'meeting_notes' => $meetingNotes !== '' ? $meetingNotes : null,
                    'reflection' => $reflection !== '' ? $reflection : null,
                    'hours' => $hours,
                    'status' => $submit ? 'submitted' : 'draft',
                    'updated_at' => $now,
                ];
                if ($submit) {
                    $data['submitted_at'] = $now;
                }
                Database::update('logbook_entry', $data, 'entry_id = ?', [$id]);
                Audit::log('logbook.updated', 'logbook_entry', $id, $before, $data);
            } else {
                $id = Database::insert('logbook_entry', [
                    'placement_id' => (int) $placement['placement_id'],
                    'week_no' => $week,
                    'date_from' => $from,
                    'date_to' => $to,
                    'activities' => $activities,
                    'technical_skills' => $skills !== '' ? $skills : null,
                    'challenges' => $challenges !== '' ? $challenges : null,
                    'meeting_notes' => $meetingNotes !== '' ? $meetingNotes : null,
                    'reflection' => $reflection !== '' ? $reflection : null,
                    'hours' => $hours,
                    'status' => $submit ? 'submitted' : 'draft',
                    'submitted_at' => $submit ? $now : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                Audit::log('logbook.created', 'logbook_entry', $id, null, [
                    'week_no' => $week,
                    'status' => $submit ? 'submitted' : 'draft',
                ]);
            }

            Database::q('DELETE FROM logbook_day WHERE entry_id = ?', [$id]);
            ksort($days);
            foreach ($days as $day) {
                Database::insert('logbook_day', [
                    'entry_id' => $id,
                    'work_date' => $day['date'],
                    'tasks' => $day['tasks'],
                    'hours' => $day['hours'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            Audit::log('logbook.days_saved', 'logbook_entry', $id, null, ['days' => count($days)]);

            if ($submit) {
                $entryData = [
                    'week_no' => $week,
                    'date_from' => $from,
                    'date_to' => $to,
                    'hours' => $hours,
                    'activities' => $activities,
                    'technical_skills' => $skills,
                    'challenges' => $challenges,
                    'meeting_notes' => $meetingNotes,
                    'reflection' => $reflection,
                    'submitted_at' => $now,
                ];
                $dayRows = [];
                foreach ($days as $day) {
                    $dayRows[] = ['work_date' => $day['date'], 'tasks' => $day['tasks'], 'hours' => $day['hours']];
                }
                $studentName = $student['first_name'] . ' ' . $student['last_name'];

                $supervisor = $placement['supervisor_id'];
                if ($supervisor !== null) {
                    [$subject, $mail] = LogbookDigest::weeklyMail($entryData, $dayRows, $placement, $student, 'supervisor/entry', ['id' => $id]);
                    Notify::push(
                        (int) $supervisor,
                        'logbook',
                        $subject,
                        $studentName . ' submitted week ' . $week . ' of the logbook for approval.',
                        'index.php?r=supervisor/entry&id=' . $id,
                        true,
                        $mail
                    );
                }
                [$subject, $mail] = LogbookDigest::weeklyMail($entryData, $dayRows, $placement, $student, 'mentor/entry', ['id' => $id]);
                Notify::push(
                    (int) $placement['mentor_id'],
                    'logbook',
                    $subject,
                    $studentName . ' submitted week ' . $week . '. It is now with the host supervisor.',
                    'index.php?r=mentor/entry&id=' . $id,
                    true,
                    $mail
                );
            }

            return $id;
        });

        return ['ok' => true, 'entry_id' => $entryId, 'week' => $week, 'status' => $submit ? 'submitted' : 'draft'];
    }

    public function entrySubmit(): void
    {
        $student = $this->student();
        $id = (int) \post('entry_id', 0);
        $entry = Database::one(
            'SELECT e.*, p.supervisor_id, p.mentor_id, p.total_weeks,
                    o.name AS org_name, o.district AS org_district
             FROM logbook_entry e
             JOIN placement p ON p.placement_id = e.placement_id
             JOIN organisation o ON o.org_id = p.org_id
             WHERE e.entry_id = ? AND p.student_id = ?',
            [$id, (int) $student['student_id']]
        );
        if ($entry === null) {
            Audit::log('logbook.denied', 'logbook_entry', $id);
            http_response_code(403);
            View::error(403);
            return;
        }
        if (!in_array($entry['status'], ['draft', 'returned'], true)) {
            \flash('error', 'That entry is not in a state that can be submitted.');
            \redirect('student/logbook');
        }
        $dayCount = (int) Database::val('SELECT COUNT(*) FROM logbook_day WHERE entry_id = ?', [(int) $entry['entry_id']]);
        if ($dayCount === 0) {
            \flash('error', 'Add at least one working day to the week before submitting it.');
            \redirect('student/logbook/new&week=' . (int) $entry['week_no']);
        }

        $days = Database::all(
            'SELECT work_date, tasks, hours FROM logbook_day WHERE entry_id = ? ORDER BY work_date',
            [(int) $entry['entry_id']]
        );
        $entryData = [
            'week_no' => (int) $entry['week_no'],
            'date_from' => (string) $entry['date_from'],
            'date_to' => (string) $entry['date_to'],
            'hours' => $entry['hours'],
            'activities' => (string) $entry['activities'],
            'technical_skills' => (string) ($entry['technical_skills'] ?? ''),
            'challenges' => (string) ($entry['challenges'] ?? ''),
            'meeting_notes' => (string) ($entry['meeting_notes'] ?? ''),
            'reflection' => (string) ($entry['reflection'] ?? ''),
            'submitted_at' => date('Y-m-d H:i:s'),
        ];
        $mailPlacement = [
            'org_name' => (string) $entry['org_name'],
            'district' => $entry['org_district'],
            'total_weeks' => (int) $entry['total_weeks'],
            'supervisor_id' => $entry['supervisor_id'],
        ];
        $studentName = $student['first_name'] . ' ' . $student['last_name'];
        [$supSubject, $supMail] = LogbookDigest::weeklyMail($entryData, $days, $mailPlacement, $student, 'supervisor/entry', ['id' => (int) $entry['entry_id']]);
        [$menSubject, $menMail] = LogbookDigest::weeklyMail($entryData, $days, $mailPlacement, $student, 'mentor/entry', ['id' => (int) $entry['entry_id']]);

        Database::tx(static function () use ($entry, $student, $studentName, $supSubject, $supMail, $menSubject, $menMail): void {
            Database::update('logbook_entry', [
                'status' => 'submitted',
                'submitted_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'entry_id = ?', [(int) $entry['entry_id']]);
            Audit::log('logbook.submitted', 'logbook_entry', (int) $entry['entry_id'], ['status' => $entry['status']], ['status' => 'submitted']);
            if ($entry['supervisor_id'] !== null) {
                Notify::push(
                    (int) $entry['supervisor_id'],
                    'logbook',
                    $supSubject,
                    $studentName . ' submitted week ' . $entry['week_no'] . '.',
                    'index.php?r=supervisor/entry&id=' . (int) $entry['entry_id'],
                    true,
                    $supMail
                );
            }
            Notify::push(
                (int) $entry['mentor_id'],
                'logbook',
                $menSubject,
                $studentName . ' submitted week ' . $entry['week_no'] . '.',
                'index.php?r=mentor/entry&id=' . (int) $entry['entry_id'],
                true,
                $menMail
            );
        });

        \flash('success', 'Entry submitted.');
        \redirect('student/logbook');
    }

    public function entryView(): void
    {
        $student = $this->student();
        $id = (int) \qget('id', 0);
        $entry = $this->ownEntry((int) $student['student_id'], $id);
        if ($entry === null) {
            Audit::log('logbook.denied', 'logbook_entry', $id);
            http_response_code(403);
            View::error(403);
            return;
        }

        $days = Database::all(
            'SELECT work_date, tasks, hours FROM logbook_day WHERE entry_id = ? ORDER BY work_date',
            [$id]
        );
        $sentDays = [];
        $sendDayRoute = null;
        if (in_array((string) $entry['status'], ['draft', 'returned'], true)) {
            $recipients = 1 + (($entry['supervisor_id'] ?? null) !== null ? 1 : 0);
            foreach (LogbookDigest::deliveredDays($id) as $workDate => $userIds) {
                if (count(array_unique($userIds)) >= $recipients) {
                    $sentDays[$workDate] = true;
                }
            }
            $sendDayRoute = 'student/logbook/send-day';
        }

        View::render('student/entry_view', [
            'title' => 'Week ' . $entry['week_no'] . ' entry',
            'entry' => $entry,
            'days' => $days,
            'comments' => $this->comments($id),
            'sentDays' => $sentDays,
            'sendDayRoute' => $sendDayRoute,
            'attachments' => Database::all(
                'SELECT d.*, rv.first_name AS reviewer_first, rv.last_name AS reviewer_last
                 FROM document d LEFT JOIN users rv ON rv.user_id = d.reviewed_by
                 WHERE d.entry_id = ? ORDER BY d.uploaded_at',
                [$id]
            ),
            'threadRoute' => 'student/logbook/comment',
            'backRoute' => 'student/logbook',
        ]);
    }

    public function comment(): void
    {
        $student = $this->student();
        $id = (int) \post('entry_id', 0);
        $entry = $this->ownEntry((int) $student['student_id'], $id);
        if ($entry === null) {
            Audit::log('comment.denied', 'logbook_entry', $id);
            http_response_code(403);
            View::error(403);
            return;
        }
        $commentId = $this->storeComment($id, null);
        \redirect('student/logbook/view', ['id' => $id], $commentId !== null ? 'comment-' . $commentId : '');
    }

    public function attach(): void
    {
        $student = $this->student();
        $id = (int) \post('entry_id', 0);
        $entry = $this->ownEntry((int) $student['student_id'], $id);
        if ($entry === null) {
            http_response_code(403);
            View::error(403);
            return;
        }
        try {
            $docId = Upload::store($_FILES['attachment'] ?? [], 'logbook_attachment', ['entry_id' => $id]);
            Audit::log('document.uploaded', 'document', $docId, null, ['entry_id' => $id]);
            \flash('success', 'Attachment uploaded.');
        } catch (RuntimeException $e) {
            \flash('error', $e->getMessage());
        }
        \redirect('student/logbook/view', ['id' => $id]);
    }

    public function report(): void
    {
        $student = $this->student();
        $placement = $this->placementFor((int) $student['student_id']);
        $report = null;
        $docs = [];
        if ($placement !== null) {
            $report = Database::one('SELECT * FROM internship_report WHERE placement_id = ?', [(int) $placement['placement_id']]);
            $docs = Database::all(
                'SELECT d.*, rv.first_name AS reviewer_first, rv.last_name AS reviewer_last
                 FROM document d
                 JOIN internship_report r ON r.document_id = d.doc_id
                 LEFT JOIN users rv ON rv.user_id = d.reviewed_by
                 WHERE r.placement_id = ?',
                [(int) $placement['placement_id']]
            );
        }

        View::render('student/report', [
            'title' => 'Internship report',
            'placement' => $placement,
            'report' => $report,
            'docs' => $docs,
        ]);
    }

    public function reportUpload(): void
    {
        $student = $this->student();
        $placement = $this->placementFor((int) $student['student_id']);
        if ($placement === null || $placement['status'] !== 'active') {
            \flash('error', 'The report can only be uploaded while your placement is active.');
            \redirect('student/report');
        }
        $existing = Database::one('SELECT * FROM internship_report WHERE placement_id = ?', [(int) $placement['placement_id']]);
        if ($existing !== null && $existing['status'] === 'assessed') {
            \flash('error', 'The report has already been assessed.');
            \redirect('student/report');
        }

        try {
            Database::tx(static function () use ($placement, $student, $existing): void {
                $docId = Upload::store($_FILES['report'] ?? [], 'internship_report', ['placement_id' => (int) $placement['placement_id']]);
                if ($existing === null) {
                    Database::insert('internship_report', [
                        'placement_id' => (int) $placement['placement_id'],
                        'document_id' => $docId,
                        'submitted_at' => date('Y-m-d H:i:s'),
                        'status' => 'submitted',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    Database::update('internship_report', [
                        'document_id' => $docId,
                        'submitted_at' => date('Y-m-d H:i:s'),
                        'status' => 'submitted',
                        'updated_at' => date('Y-m-d H:i:s'),
                    ], 'report_id = ?', [(int) $existing['report_id']]);
                }
                Audit::log('report.uploaded', 'internship_report', $existing['report_id'] ?? null, $existing, ['document_id' => $docId]);
                Notify::push(
                    (int) $placement['mentor_id'],
                    'report',
                    'Internship report ready for assessment',
                    $student['first_name'] . ' ' . $student['last_name'] . ' uploaded the internship report.',
                    'index.php?r=mentor/reports'
                );
            });
            \flash('success', 'Report uploaded and sent to your academic mentor.');
        } catch (RuntimeException $e) {
            \flash('error', $e->getMessage());
        }
        \redirect('student/report');
    }

    public function careerFair(): void
    {
        $student = $this->student();
        $placement = $this->placementFor((int) $student['student_id']);
        $docs = [];
        if ($placement !== null) {
            $docs = Database::all(
                'SELECT d.*, rv.first_name AS reviewer_first, rv.last_name AS reviewer_last
                 FROM document d
                 LEFT JOIN users rv ON rv.user_id = d.reviewed_by
                 WHERE d.placement_id = ? AND d.doc_type = ?
                 ORDER BY d.uploaded_at DESC',
                [(int) $placement['placement_id'], 'career_fair_report']
            );
        }

        View::render('student/career_fair', [
            'title' => 'Career Fair 2026',
            'placement' => $placement,
            'docs' => $docs,
            'deadline' => \career_fair_deadline(),
            'late' => strtotime(\career_fair_deadline()) < time(),
        ]);
    }

    public function careerFairUpload(): void
    {
        $student = $this->student();
        $placement = $this->placementFor((int) $student['student_id']);
        if ($placement === null) {
            \flash('error', 'A Career Fair report is linked to your placement. No placement is recorded yet.');
            \redirect('student/career-fair');
        }

        try {
            Database::tx(static function () use ($placement, $student): void {
                $docId = Upload::store($_FILES['career_fair'] ?? [], 'career_fair_report', ['placement_id' => (int) $placement['placement_id']]);
                Audit::log('career_fair.uploaded', 'document', $docId, null, ['placement_id' => (int) $placement['placement_id']]);
                Notify::push(
                    (int) $placement['mentor_id'],
                    'career_fair',
                    'Career Fair 2026 report submitted',
                    $student['first_name'] . ' ' . $student['last_name'] . ' submitted the Career Fair 2026 student participation report.',
                    'index.php?r=mentor/reports'
                );
            });
            $late = strtotime(\career_fair_deadline()) < time();
            \flash('success', $late
                ? 'Career Fair report uploaded. It is after the deadline, so it is recorded as a late submission.'
                : 'Career Fair report uploaded and sent to your academic mentor.');
        } catch (RuntimeException $e) {
            \flash('error', $e->getMessage());
        }
        \redirect('student/career-fair');
    }

    public function results(): void
    {
        $student = $this->student();
        $mark = Database::one(
            'SELECT m.*, p.academic_year, o.name AS org_name, g.grade_letter, g.classification, g.points
             FROM mark m
             JOIN placement p ON p.placement_id = m.placement_id
             JOIN organisation o ON o.org_id = p.org_id
             LEFT JOIN grade_scale g ON g.grade_id = m.grade_id
             WHERE p.student_id = ? AND m.status = ?
             ORDER BY p.academic_year DESC LIMIT 1',
            [(int) $student['student_id'], 'published']
        );

        View::render('student/results', [
            'title' => 'Results',
            'student' => $student,
            'mark' => $mark,
        ]);
    }

    public function selfEvaluation(): void
    {
        $student = $this->student();
        $placement = $this->placementFor((int) $student['student_id']);
        if ($placement === null) {
            \flash('error', 'The self evaluation opens once a placement is recorded for you.');
            \redirect('student/placement');
        }

        $criteria = Database::all(
            'SELECT * FROM criterion WHERE evaluation_type = ? AND active = 1 ORDER BY sort_order',
            ['self']
        );
        $evaluation = Database::one(
            'SELECT * FROM evaluation WHERE placement_id = ? AND evaluator_id = ? AND evaluation_type = ?',
            [(int) $placement['placement_id'], (int) Auth::id(), 'self']
        );
        $scores = [];
        if ($evaluation !== null) {
            foreach (Database::all('SELECT * FROM evaluation_score WHERE evaluation_id = ?', [(int) $evaluation['evaluation_id']]) as $row) {
                $scores[(int) $row['criterion_id']] = $row;
            }
        }

        View::render('student/self_evaluation', [
            'title' => 'Self evaluation',
            'placement' => $placement,
            'criteria' => $criteria,
            'evaluation' => $evaluation,
            'scores' => $scores,
            'weightTotal' => \weight_total_for('self'),
        ]);
    }

    public function selfEvaluationSave(): void
    {
        $student = $this->student();
        $placement = $this->placementFor((int) $student['student_id']);
        if ($placement === null) {
            Audit::log('evaluation.denied', 'student', (int) $student['student_id'], null, ['type' => 'self']);
            http_response_code(403);
            View::error(403);
            return;
        }

        $criteria = Database::all(
            'SELECT * FROM criterion WHERE evaluation_type = ? AND active = 1 ORDER BY sort_order',
            ['self']
        );
        $submit = \post('submit') !== null;
        $input = (array) \post('scores', []);
        $comments = (array) \post('comments', []);
        $general = trim((string) \post('comments_general', ''));

        $clean = [];
        $complete = true;
        foreach ($criteria as $criterion) {
            $cid = (int) $criterion['criterion_id'];
            $value = $input[$cid] ?? '';
            if ($value === '' || !Validator::decimal($value, 0, (float) $criterion['max_score'])) {
                $complete = false;
                $clean[$cid] = null;
                continue;
            }
            $clean[$cid] = (float) $value;
        }

        if ($submit && !$complete) {
            \flash('error', 'Score every criterion between zero and its maximum before submitting.');
            \redirect('student/self-evaluation');
        }
        if ($submit && abs(\weight_total_for('self') - 100) > 0.01) {
            \flash('error', 'Criterion weights must total 100 before the self evaluation can be submitted.');
            \redirect('student/self-evaluation');
        }

        $placementId = (int) $placement['placement_id'];
        Database::tx(static function () use ($placementId, $criteria, $clean, $comments, $general, $submit): void {
            $existing = Database::one(
                'SELECT * FROM evaluation WHERE placement_id = ? AND evaluator_id = ? AND evaluation_type = ?',
                [$placementId, (int) Auth::id(), 'self']
            );
            $total = 0.0;
            foreach ($criteria as $criterion) {
                $value = $clean[(int) $criterion['criterion_id']];
                if ($value !== null) {
                    $total += ($value / (float) $criterion['max_score']) * (float) $criterion['weight'];
                }
            }

            $data = [
                'status' => $submit ? 'submitted' : 'draft',
                'total_score' => $submit ? round($total, 2) : null,
                'comments' => $general,
                'submitted_at' => $submit ? date('Y-m-d H:i:s') : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($existing === null) {
                $evaluationId = Database::insert('evaluation', [
                    'placement_id' => $placementId,
                    'evaluator_id' => (int) Auth::id(),
                    'evaluation_type' => 'self',
                ] + $data + ['created_at' => date('Y-m-d H:i:s')]);
            } else {
                $evaluationId = (int) $existing['evaluation_id'];
                Database::update('evaluation', $data, 'evaluation_id = ?', [$evaluationId]);
            }

            foreach ($criteria as $criterion) {
                $cid = (int) $criterion['criterion_id'];
                if ($clean[$cid] === null) {
                    Database::q('DELETE FROM evaluation_score WHERE evaluation_id = ? AND criterion_id = ?', [$evaluationId, $cid]);
                    continue;
                }
                $comment = trim((string) ($comments[$cid] ?? ''));
                $row = Database::one(
                    'SELECT score_id FROM evaluation_score WHERE evaluation_id = ? AND criterion_id = ?',
                    [$evaluationId, $cid]
                );
                if ($row === null) {
                    Database::insert('evaluation_score', [
                        'evaluation_id' => $evaluationId,
                        'criterion_id' => $cid,
                        'score' => $clean[$cid],
                        'comment' => mb_substr($comment, 0, 500),
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    Database::update('evaluation_score', [
                        'score' => $clean[$cid],
                        'comment' => mb_substr($comment, 0, 500),
                    ], 'score_id = ?', [(int) $row['score_id']]);
                }
            }

            Audit::log(
                $submit ? 'evaluation.submitted' : 'evaluation.draft_saved',
                'evaluation',
                $evaluationId,
                null,
                ['type' => 'self', 'placement_id' => $placementId, 'total' => $submit ? round($total, 2) : null]
            );

            if ($submit) {
                Notify::toRole(
                    'coordinator',
                    'evaluation',
                    'Student self evaluation submitted',
                    Auth::name() . ' submitted the self evaluation for placement ' . $placementId . '.',
                    'index.php?r=coordinator/grades/view&placement_id=' . $placementId
                );
            }
        });

        \flash('success', $submit ? 'Self evaluation submitted. It now counts 10% of Practical Internship.' : 'Draft saved.');
        \redirect('student/self-evaluation');
    }

    private function student(): array
    {
        $student = Database::one(
            'SELECT s.*, u.first_name, u.last_name, u.email, u.phone
             FROM student s JOIN users u ON u.user_id = s.user_id
             WHERE s.user_id = ?',
            [(int) Auth::id()]
        );
        if ($student === null) {
            \flash('error', 'Your account has no student record. Contact the system administrator.');
            \redirect('dashboard');
        }
        return $student;
    }

    private function placementFor(int $studentId): ?array
    {
        return Database::one(
            'SELECT p.*, o.name AS org_name, o.district, o.address, o.contact_person,
                    m.first_name AS mentor_first, m.last_name AS mentor_last, m.email AS mentor_email,
                    s.first_name AS sup_first, s.last_name AS sup_last, s.email AS sup_email
             FROM placement p
             JOIN organisation o ON o.org_id = p.org_id
             JOIN users m ON m.user_id = p.mentor_id
             LEFT JOIN users s ON s.user_id = p.supervisor_id
             WHERE p.student_id = ?
             ORDER BY p.academic_year DESC LIMIT 1',
            [$studentId]
        );
    }

    private function ownEntry(int $studentId, int $entryId): ?array
    {
        return Database::one(
            'SELECT e.*, p.student_id, p.mentor_id, p.supervisor_id, p.total_weeks
             FROM logbook_entry e JOIN placement p ON p.placement_id = e.placement_id
             WHERE e.entry_id = ? AND p.student_id = ?',
            [$entryId, $studentId]
        );
    }

    private function comments(int $entryId): array
    {
        return Database::all(
            'SELECT c.*, u.first_name, u.last_name, r.role_name,
                    pu.first_name AS parent_first, pu.last_name AS parent_last, pc.body AS parent_body
             FROM logbook_comment c
             JOIN users u ON u.user_id = c.user_id
             JOIN roles r ON r.role_id = u.role_id
             LEFT JOIN logbook_comment pc ON pc.comment_id = c.parent_id
             LEFT JOIN users pu ON pu.user_id = pc.user_id
             WHERE c.entry_id = ?
             ORDER BY c.created_at, c.comment_id',
            [$entryId]
        );
    }

    private function storeComment(int $entryId, ?int $parentId): ?int
    {
        $body = trim((string) \post('body', ''));
        if (!Validator::length($body, 2, 1000)) {
            \flash('error', 'Write a comment of at least two characters.');
            return null;
        }
        $parent = $parentId;
        if ($parent === null) {
            $requested = (int) \post('parent_id', 0);
            if ($requested > 0) {
                $ok = Database::val('SELECT COUNT(*) FROM logbook_comment WHERE comment_id = ? AND entry_id = ?', [$requested, $entryId]);
                $parent = (int) $ok > 0 ? $requested : null;
            }
        }

        $id = Database::insert('logbook_comment', [
            'entry_id' => $entryId,
            'user_id' => (int) Auth::id(),
            'parent_id' => $parent,
            'body' => $body,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        Audit::log('logbook.comment_added', 'logbook_comment', $id, null, ['entry_id' => $entryId]);

        $entry = Database::one(
            'SELECT e.week_no, e.placement_id, p.student_id, p.mentor_id, p.supervisor_id, s.user_id AS student_user
             FROM logbook_entry e
             JOIN placement p ON p.placement_id = e.placement_id
             JOIN student s ON s.student_id = p.student_id
             WHERE e.entry_id = ?',
            [$entryId]
        );
        if ($entry !== null) {
            $targets = [];
            if (Auth::role() !== 'student') {
                $targets[] = (int) $entry['student_user'];
            }
            if (Auth::role() !== 'supervisor' && $entry['supervisor_id'] !== null) {
                $targets[] = (int) $entry['supervisor_id'];
            }
            if (Auth::role() !== 'mentor') {
                $targets[] = (int) $entry['mentor_id'];
            }
            foreach (array_unique($targets) as $target) {
                Notify::push(
                    $target,
                    'comment',
                    'New comment on week ' . $entry['week_no'],
                    Auth::name() . ' commented on a logbook entry.',
                    'index.php?r=student/logbook/view&id=' . $entryId
                );
            }
        }

        return (int) $id;
    }

    private function academicYear(): string
    {
        $current = (string) Database::val(
            'SELECT academic_year FROM placement WHERE status IN (?,?) GROUP BY academic_year ORDER BY academic_year DESC LIMIT 1',
            ['active', 'approved']
        );
        if ($current !== '') {
            return $current;
        }
        $month = (int) date('n');
        $start = $month >= 8 ? (int) date('Y') : (int) date('Y') - 1;
        return $start . '/' . ($start + 1);
    }
}
