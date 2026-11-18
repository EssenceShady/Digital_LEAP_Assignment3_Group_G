<?php
declare(strict_types=1);

namespace Leap\Controllers;

use Leap\Core\Auth;
use Leap\Core\Database;
use Leap\Core\Settings;
use Leap\Core\View;

final class DashboardController
{
    public function index(): void
    {
        $method = 'for' . ucfirst(Auth::role());
        if (method_exists($this, $method)) {
            $this->$method();
            return;
        }
        View::render('dashboard/admin', ['title' => 'Dashboard']);
    }

    private function forStudent(): void
    {
        $userId = (int) Auth::id();
        $student = Database::one('SELECT * FROM student WHERE user_id = ?', [$userId]);

        if ($student === null) {
            View::render('dashboard/student', [
                'title' => 'Dashboard',
                'student' => null,
                'placement' => null,
                'entries' => [],
                'feedback' => null,
                'tasks' => [],
                'prefs' => [],
                'progress' => 0,
                'nextWeek' => null,
                'dueDate' => null,
                'returned' => [],
                'cvOnFile' => false,
                'prefsSubmitted' => false,
            ]);
            return;
        }

        $placement = Database::one(
            'SELECT p.*, o.name AS org_name, o.district, o.contact_person,
                    mu.first_name AS mentor_first, mu.last_name AS mentor_last,
                    su.first_name AS sup_first, su.last_name AS sup_last
             FROM placement p
             JOIN organisation o ON o.org_id = p.org_id
             LEFT JOIN users mu ON mu.user_id = p.mentor_id
             LEFT JOIN users su ON su.user_id = p.supervisor_id
             WHERE p.student_id = ?
             ORDER BY p.start_date DESC LIMIT 1',
            [(int) $student['student_id']]
        );

        $entries = $placement === null ? [] : Database::all(
            'SELECT * FROM logbook_entry WHERE placement_id = ? ORDER BY week_no',
            [(int) $placement['placement_id']]
        );

        $prefs = Database::all(
            'SELECT a.*, o.name AS org_name FROM application a
             JOIN organisation o ON o.org_id = a.org_id
             WHERE a.student_id = ? ORDER BY a.preference_rank',
            [(int) $student['student_id']]
        );

        $feedback = null;
        if ($placement !== null) {
            $feedback = Database::one(
                'SELECT c.*, u.first_name, u.last_name, r.role_name
                 FROM logbook_comment c
                 JOIN logbook_entry e ON e.entry_id = c.entry_id
                 JOIN users u ON u.user_id = c.user_id
                 JOIN roles r ON r.role_id = u.role_id
                 WHERE e.placement_id = ? AND c.user_id <> ?
                 ORDER BY c.created_at DESC LIMIT 1',
                [(int) $placement['placement_id'], $userId]
            );
        }

        $nextWeek = null;
        $dueDate = null;
        $progress = 0;
        $returned = [];
        $submittedCount = 0;

        if ($placement !== null) {
            $used = array_map(static fn (array $r): int => (int) $r['week_no'], $entries);
            $nextWeek = 1;
            while (in_array($nextWeek, $used, true)) {
                $nextWeek++;
            }
            if ($nextWeek > (int) $placement['total_weeks']) {
                $nextWeek = null;
            } else {
                $weekStart = strtotime($placement['start_date'] . ' +' . (($nextWeek - 1) * 7) . ' days');
                $dueDate = date('Y-m-d', $weekStart + 4 * 86400);
            }

            $submittedCount = count(array_filter($entries, static fn (array $e): bool => $e['status'] !== 'draft'));
            $progress = (int) round(min(100, ($submittedCount / max(1, (int) $placement['total_weeks'])) * 100));
            $returned = array_values(array_filter($entries, static fn (array $e): bool => $e['status'] === 'returned'));
        }

        $tasks = [];
        $hasCv = $student !== null && (int) Database::val(
            "SELECT COUNT(*) FROM document WHERE student_id = ? AND doc_type = 'cv'",
            [(int) $student['student_id']]
        ) > 0;
        $prefsSubmitted = $prefs !== [] && !in_array('draft', array_column($prefs, 'status'), true);
        if (!$hasCv) {
            $tasks[] = ['Step 1 · Upload your CV so the internship office can review you.', 'student/apply', 'Upload CV'];
        }
        if ($placement === null) {
            if ($prefs === []) {
                $tasks[] = ['Step 2 · Choose up to five host organisations and say why each one fits.', 'student/apply', 'Choose hosts'];
            } elseif (!$prefsSubmitted) {
                $tasks[] = ['Step 3 · Submit your ranked list for review.', 'student/apply', 'Submit list'];
            } else {
                $tasks[] = ['Your ranked list is with the internship office. Watch notifications for a proposed placement.', 'student/apply', 'Track application'];
            }
        } else {
            if ($returned !== []) {
                $weeks = implode(', ', array_map(static fn (array $e): string => 'week ' . $e['week_no'], $returned));
                $tasks[] = ['Rework and resubmit ' . $weeks . ' after reading the supervisor comment.', 'student/logbook', 'Open logbook'];
            }
            if ($nextWeek !== null && $dueDate !== null) {
                $tasks[] = ['Submit week ' . $nextWeek . ' by ' . date('l d M', strtotime($dueDate)) . '.', 'student/logbook/new', 'Write entry'];
            }
            $reportExists = (int) Database::val(
                'SELECT COUNT(*) FROM internship_report WHERE placement_id = ?',
                [(int) $placement['placement_id']]
            );
            $daysLeft = (int) floor((strtotime((string) $placement['end_date']) - time()) / 86400);
            if ($reportExists === 0 && $daysLeft <= 21) {
                $tasks[] = ['Upload your internship report. The placement ends in ' . max(0, $daysLeft) . ' days.', 'student/report', 'Upload report'];
            }
        }
        if ($tasks === []) {
            $tasks[] = ['Nothing is outstanding. Keep your logbook current and check notifications.', 'dashboard', 'Refresh'];
        }

        View::render('dashboard/student', [
            'title' => 'Dashboard',
            'student' => $student,
            'placement' => $placement,
            'entries' => $entries,
            'feedback' => $feedback,
            'tasks' => $tasks,
            'prefs' => $prefs,
            'progress' => $progress,
            'nextWeek' => $nextWeek,
            'dueDate' => $dueDate,
            'returned' => $returned,
            'cvOnFile' => $hasCv,
            'prefsSubmitted' => $prefsSubmitted,
        ]);
    }

    private function forSupervisor(): void
    {
        $userId = (int) Auth::id();

        $placements = Database::all(
            'SELECT p.*, s.reg_no, u.first_name, u.last_name, o.name AS org_name
             FROM placement p
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             JOIN organisation o ON o.org_id = p.org_id
             WHERE p.supervisor_id = ?
             ORDER BY p.start_date DESC',
            [$userId]
        );

        $queue = Database::all(
            "SELECT e.*, s.reg_no, u.first_name, u.last_name
             FROM logbook_entry e
             JOIN placement p ON p.placement_id = e.placement_id
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             WHERE p.supervisor_id = ? AND e.status = 'submitted'
             ORDER BY e.submitted_at",
            [$userId]
        );

        $evaluations = Database::all(
            "SELECT e.*, u.first_name, u.last_name
             FROM evaluation e
             JOIN placement p ON p.placement_id = e.placement_id
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             WHERE e.evaluator_id = ? AND e.status = 'draft'
             ORDER BY e.evaluation_id",
            [$userId]
        );

        View::render('dashboard/supervisor', [
            'title' => 'Dashboard',
            'placements' => $placements,
            'queue' => $queue,
            'evaluations' => $evaluations,
        ]);
    }

    private function forMentor(): void
    {
        $userId = (int) Auth::id();

        $placements = Database::all(
            'SELECT p.*, s.reg_no, u.first_name, u.last_name, o.name AS org_name,
                    (SELECT COUNT(*) FROM logbook_entry e WHERE e.placement_id = p.placement_id AND e.status = ?) AS pending
             FROM placement p
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             JOIN organisation o ON o.org_id = p.org_id
             WHERE p.mentor_id = ?
             ORDER BY p.start_date DESC',
            ['submitted', $userId]
        );

        $visits = Database::all(
            "SELECT v.*, u.first_name, u.last_name, o.name AS org_name
             FROM site_visit v
             JOIN placement p ON p.placement_id = v.placement_id
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             JOIN organisation o ON o.org_id = p.org_id
             WHERE v.mentor_id = ? AND v.status = 'scheduled'
             ORDER BY v.scheduled_date",
            [$userId]
        );

        $reports = Database::all(
            "SELECT r.*, u.first_name, u.last_name
             FROM internship_report r
             JOIN placement p ON p.placement_id = r.placement_id
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             WHERE p.mentor_id = ? AND r.status = 'submitted'",
            [$userId]
        );

        View::render('dashboard/mentor', [
            'title' => 'Dashboard',
            'placements' => $placements,
            'visits' => $visits,
            'reports' => $reports,
        ]);
    }

    private function forCoordinator(): void
    {
        $pendingApplications = (int) Database::val("SELECT COUNT(*) FROM application WHERE status = 'submitted'");
        $awaitingAllocation = (int) Database::val("SELECT COUNT(*) FROM placement WHERE status = 'proposed'");
        $withoutSupervisor = (int) Database::val("SELECT COUNT(*) FROM placement WHERE supervisor_id IS NULL AND status IN ('proposed','approved','active')");
        $awaitingPublication = (int) Database::val("SELECT COUNT(*) FROM mark WHERE status IN ('draft','moderated') AND weighted_total IS NOT NULL");
        $overdue = (int) Database::val(
            "SELECT COUNT(*) FROM placement p
             WHERE p.status = 'active'
               AND (SELECT COUNT(*) FROM logbook_entry e
                    WHERE e.placement_id = p.placement_id
                      AND e.status IN ('submitted','approved','mentor_reviewed'))
                   < LEAST(p.total_weeks, GREATEST(0, FLOOR(DATEDIFF(CURDATE(), p.start_date) / 7)))"
        );

        $applications = Database::all(
            'SELECT a.*, u.first_name, u.last_name, s.reg_no, o.name AS org_name
             FROM application a
             JOIN student s ON s.student_id = a.student_id
             JOIN users u ON u.user_id = s.user_id
             JOIN organisation o ON o.org_id = a.org_id
             WHERE a.status = ?
             ORDER BY a.submitted_at LIMIT 8',
            ['submitted']
        );

        $unassigned = Database::all(
            "SELECT p.*, u.first_name, u.last_name, s.reg_no, o.name AS org_name
             FROM placement p
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             JOIN organisation o ON o.org_id = p.org_id
             WHERE p.supervisor_id IS NULL AND p.status IN ('proposed','approved','active')
             ORDER BY p.start_date LIMIT 8"
        );

        View::render('dashboard/coordinator', [
            'title' => 'Dashboard',
            'pendingApplications' => $pendingApplications,
            'awaitingAllocation' => $awaitingAllocation,
            'withoutSupervisor' => $withoutSupervisor,
            'awaitingPublication' => $awaitingPublication,
            'overdue' => $overdue,
            'applications' => $applications,
            'unassigned' => $unassigned,
        ]);
    }

    private function forManagement(): void
    {
        $summary = $this->managementSummary();
        $byOrg = Database::all(
            "SELECT o.name, COUNT(p.placement_id) AS total
             FROM organisation o
             LEFT JOIN placement p ON p.org_id = o.org_id AND p.academic_year = ?
             GROUP BY o.org_id, o.name
             HAVING total > 0
             ORDER BY total DESC, o.name",
            [$this->currentYear()]
        );

        View::render('dashboard/management', [
            'title' => 'Dashboard',
            'summary' => $summary,
            'byOrg' => $byOrg,
        ]);
    }

    private function forAdmin(): void
    {
        $activeUsers = (int) Database::val("SELECT COUNT(*) FROM users WHERE status = 'active'");
        $locked = (int) Database::val('SELECT COUNT(*) FROM users WHERE locked_until IS NOT NULL AND locked_until > NOW()');
        $queued = (int) Database::val("SELECT COUNT(*) FROM email_queue WHERE status = 'queued'");
        $failed = (int) Database::val("SELECT COUNT(*) FROM email_queue WHERE status = 'failed'");
        $today = (int) Database::val('SELECT COUNT(*) FROM audit_log WHERE DATE(created_at) = CURDATE()');
        $recent = Database::all(
            'SELECT a.*, u.first_name, u.last_name
             FROM audit_log a LEFT JOIN users u ON u.user_id = a.user_id
             ORDER BY a.audit_id DESC LIMIT 8'
        );

        View::render('dashboard/admin', [
            'title' => 'Dashboard',
            'activeUsers' => $activeUsers,
            'locked' => $locked,
            'queued' => $queued,
            'failed' => $failed,
            'today' => $today,
            'recent' => $recent,
        ]);
    }

    public function managementSummary(): array
    {
        $year = $this->currentYear();
        $placements = (int) Database::val('SELECT COUNT(*) FROM placement WHERE academic_year = ?', [$year]);
        $active = (int) Database::val("SELECT COUNT(*) FROM placement WHERE academic_year = ? AND status = 'active'", [$year]);
        $completed = (int) Database::val("SELECT COUNT(*) FROM placement WHERE academic_year = ? AND status = 'completed'", [$year]);
        $average = Database::val("SELECT ROUND(AVG(weighted_total), 2) FROM mark WHERE status = 'published'");
        $overdue = (int) Database::val(
            "SELECT COUNT(*) FROM placement p
             WHERE p.status = 'active' AND p.academic_year = ?
               AND (SELECT COUNT(*) FROM logbook_entry e
                    WHERE e.placement_id = p.placement_id
                      AND e.status IN ('submitted','approved','mentor_reviewed'))
                   < LEAST(p.total_weeks, GREATEST(0, FLOOR(DATEDIFF(CURDATE(), p.start_date) / 7)))",
            [$year]
        );
        $outstandingVisits = (int) Database::val("SELECT COUNT(*) FROM site_visit WHERE status = 'scheduled'");
        $published = (int) Database::val("SELECT COUNT(*) FROM mark WHERE status = 'published'");

        $submission = Database::all(
            "SELECT e.week_no, COUNT(*) AS received
             FROM logbook_entry e
             JOIN placement p ON p.placement_id = e.placement_id
             WHERE p.academic_year = ? AND e.status <> 'draft'
             GROUP BY e.week_no ORDER BY e.week_no",
            [$year]
        );

        return [
            'year' => $year,
            'placements' => $placements,
            'active' => $active,
            'completed' => $completed,
            'completion' => $placements > 0 ? round($completed / $placements * 100, 1) : 0.0,
            'average' => $average === null ? null : (float) $average,
            'overdue' => $overdue,
            'outstandingVisits' => $outstandingVisits,
            'published' => $published,
            'submission' => $submission,
        ];
    }

    public function currentYear(): string
    {
        $row = Database::val("SELECT academic_year FROM placement ORDER BY start_date DESC LIMIT 1");
        if ($row !== null) {
            return (string) $row;
        }
        return date('Y') . '/' . (date('Y') + 1);
    }
}
