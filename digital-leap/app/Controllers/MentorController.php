<?php
declare(strict_types=1);

namespace Leap\Controllers;

use Leap\Core\Audit;
use Leap\Core\Auth;
use Leap\Core\Database;
use Leap\Core\Guard;
use Leap\Core\Messenger;
use Leap\Core\Notify;
use Leap\Core\Upload;
use Leap\Core\Validator;
use Leap\Core\View;
use Leap\Services\Attendance;
use RuntimeException;

final class MentorController
{
    public function __construct()
    {
        Guard::requireRole('mentor');
    }

    public function students(): void
    {
        $search = trim((string) \qget('q', ''));
        $status = (string) \qget('status', '');
        $orgId = max(0, (int) \qget('org_id', 0));
        $where = ' WHERE p.mentor_id = ?';
        $params = [(int) Auth::id()];
        if ($search !== '') {
            $where .= " AND CONCAT_WS(' ', u.first_name, u.last_name, s.reg_no) LIKE ?";
            $params[] = '%' . $search . '%';
        }
        if ($status !== '') {
            $where .= ' AND p.status = ?';
            $params[] = $status;
        }
        if ($orgId > 0) {
            $where .= ' AND p.org_id = ?';
            $params[] = $orgId;
        }
        $placements = Database::all(
            'SELECT p.*, u.first_name, u.last_name, u.user_id AS student_user, s.reg_no, o.name AS org_name
             FROM placement p JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id JOIN organisation o ON o.org_id = p.org_id'
             . $where . ' ORDER BY p.start_date DESC', $params
        );
        $stats = [];
        foreach ($placements as $placement) {
            $pid = (int) $placement['placement_id'];
            $stats[$pid] = [
                'submitted' => (int) Database::val('SELECT COUNT(*) FROM logbook_entry WHERE placement_id = ? AND status = ?', [$pid, 'submitted']),
                'approved' => (int) Database::val('SELECT COUNT(*) FROM logbook_entry WHERE placement_id = ? AND status IN (?,?)', [$pid, 'approved', 'mentor_reviewed']),
                'visits' => (int) Database::val('SELECT COUNT(*) FROM site_visit WHERE placement_id = ? AND status = ?', [$pid, 'scheduled']),
            ];
        }

        View::render('mentor/students', [
            'title' => 'My students',
            'placements' => $placements,
            'stats' => $stats,
            'search' => $search,
            'status' => $status,
            'orgId' => $orgId,
            'orgs' => Database::all('SELECT DISTINCT o.org_id, o.name FROM organisation o JOIN placement p ON p.org_id = o.org_id WHERE p.mentor_id = ? ORDER BY o.name', [(int) Auth::id()]),
        ]);
    }

    public function logbook(): void
    {
        $placements = $this->placements();
        $ids = array_map(static fn (array $p): int => (int) $p['placement_id'], $placements);
        $entries = [];
        if ($ids !== []) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $entries = Database::all(
                "SELECT e.*, s.reg_no, u.first_name, u.last_name
                 FROM logbook_entry e
                 JOIN placement p ON p.placement_id = e.placement_id
                 JOIN student s ON s.student_id = p.student_id
                 JOIN users u ON u.user_id = s.user_id
                 WHERE e.placement_id IN ($in)
                 ORDER BY e.week_no DESC, u.last_name",
                $ids
            );
        }

        View::render('mentor/logbook', [
            'title' => 'Logbook review',
            'placements' => $placements,
            'entries' => $entries,
        ]);
    }

    public function entry(): void
    {
        $id = (int) \qget('id', 0);
        $entry = $this->entryFor($id);
        if ($entry === null) {
            Audit::log('logbook.denied', 'logbook_entry', $id);
            http_response_code(403);
            View::error(403);
            return;
        }

        View::render('mentor/entry', [
            'title' => 'Week ' . $entry['week_no'] . ' review',
            'entry' => $entry,
            'days' => Database::all(
                'SELECT work_date, tasks, hours FROM logbook_day WHERE entry_id = ? ORDER BY work_date',
                [$id]
            ),
            'comments' => $this->comments($id),
            'checkIns' => Attendance::recent((int) $entry['placement_id'], 8, (int) $entry['student_user']),
            'checkInsPin' => Attendance::orgPoint((int) Database::val('SELECT org_id FROM placement WHERE placement_id = ?', [(int) $entry['placement_id']])),
            'attachments' => Database::all(
                'SELECT d.*, rv.first_name AS reviewer_first, rv.last_name AS reviewer_last
                 FROM document d LEFT JOIN users rv ON rv.user_id = d.reviewed_by
                 WHERE d.entry_id = ? ORDER BY d.uploaded_at',
                [$id]
            ),
        ]);
    }

    public function comment(): void
    {
        $id = (int) \post('entry_id', 0);
        $entry = $this->entryFor($id);
        if ($entry === null) {
            Audit::log('comment.denied', 'logbook_entry', $id);
            http_response_code(403);
            View::error(403);
            return;
        }
        $body = trim((string) \post('body', ''));
        if (!Validator::length($body, 2, 1000)) {
            \flash('error', 'Write a comment of at least two characters.');
            \redirect('mentor/entry', ['id' => $id]);
        }
        $parentId = (int) \post('parent_id', 0);
        if ($parentId > 0) {
            $exists = Database::val('SELECT COUNT(*) FROM logbook_comment WHERE comment_id = ? AND entry_id = ?', [$parentId, $id]);
            if ((int) $exists === 0) {
                $parentId = 0;
            }
        }

        $commentId = Database::tx(static function () use ($id, $body, $parentId, $entry): int {
            $commentId = Database::insert('logbook_comment', [
                'entry_id' => $id,
                'user_id' => (int) Auth::id(),
                'parent_id' => $parentId > 0 ? $parentId : null,
                'body' => $body,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $markReviewed = $entry['status'] === 'approved';
            if ($markReviewed) {
                Database::update('logbook_entry', [
                    'status' => 'mentor_reviewed',
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'entry_id = ?', [$id]);
            }
            Audit::log('logbook.comment_added', 'logbook_comment', $commentId, null, [
                'entry_id' => $id,
                'mentor_reviewed' => $markReviewed,
            ]);
            Notify::push(
                (int) $entry['student_user'],
                'comment',
                'Mentor comment on week ' . $entry['week_no'],
                Auth::name() . ' reviewed week ' . $entry['week_no'] . ': ' . $body,
                'index.php?r=student/logbook/view&id=' . $id
            );
            if ($entry['supervisor_id'] !== null) {
                Notify::push(
                    (int) $entry['supervisor_id'],
                    'comment',
                    'Mentor reviewed week ' . $entry['week_no'],
                    'The academic mentor added a comment to week ' . $entry['week_no'] . '.',
                    'index.php?r=supervisor/entry&id=' . $id
                );
            }

            return (int) $commentId;
        });

        \flash('success', 'Comment posted.');
        \redirect('mentor/entry', ['id' => $id], 'comment-' . $commentId);
    }

    public function visits(): void
    {
        $status = (string) \qget('status', '');
        $search = trim((string) \qget('q', ''));
        $from = (string) \qget('from', '');
        $to = (string) \qget('to', '');
        $where = ' WHERE v.mentor_id = ?';
        $params = [(int) Auth::id()];
        if ($status !== '') {
            $where .= ' AND v.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where .= " AND CONCAT_WS(' ', u.first_name, u.last_name, s.reg_no, o.name) LIKE ?";
            $params[] = '%' . $search . '%';
        }
        if (Validator::date($from)) {
            $where .= ' AND v.scheduled_date >= ?';
            $params[] = $from;
        }
        if (Validator::date($to)) {
            $where .= ' AND v.scheduled_date <= ?';
            $params[] = $to;
        }
        $visits = Database::all(
            'SELECT v.*, u.first_name, u.last_name, s.reg_no, o.name AS org_name, p.placement_id,
                    (SELECT d.doc_id FROM document d WHERE d.visit_id = v.visit_id ORDER BY d.doc_id DESC LIMIT 1) AS report_doc
             FROM site_visit v
             JOIN placement p ON p.placement_id = v.placement_id
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             JOIN organisation o ON o.org_id = p.org_id
             ' . $where . ' ORDER BY v.scheduled_date DESC',
            $params
        );
        $today = date('Y-m-d');
        $overdue = array_values(array_filter(
            $visits,
            static fn (array $v): bool => $v['status'] === 'scheduled' && $v['scheduled_date'] < $today
        ));

        View::render('mentor/visits', [
            'title' => 'Site visits',
            'status' => $status,
            'search' => $search,
            'from' => $from,
            'to' => $to,
            'visits' => $visits,
            'overdue' => $overdue,
            'placements' => $this->placements(),
        ]);
    }

    public function visitForm(): void
    {
        $placementId = (int) \qget('placement_id', 0);
        $placements = $this->placements();
        $placement = $placementId > 0 ? $this->ownPlacement($placementId) : ($placements[0] ?? null);
        if ($placement === null) {
            \flash('error', 'You have no assigned placement to visit.');
            \redirect('mentor/visits');
        }

        View::render('mentor/visit_form', [
            'title' => 'Schedule a site visit',
            'placements' => $placements,
            'placement' => $placement,
        ]);
    }

    public function visitSave(): void
    {
        $placementId = (int) \post('placement_id', 0);
        $placement = $this->ownPlacement($placementId);
        if ($placement === null) {
            Audit::log('visit.denied', 'placement', $placementId);
            http_response_code(403);
            View::error(403);
            return;
        }

        $date = (string) \post('scheduled_date', '');
        $mode = (string) \post('mode', 'physical');
        if (!Validator::date($date) || $date < date('Y-m-d')) {
            \flash('error', 'Choose a visit date today or later.');
            \redirect('mentor/visits/form', ['placement_id' => $placementId]);
        }
        if (!in_array($mode, ['physical', 'virtual', 'phone'], true)) {
            $mode = 'physical';
        }

        Database::tx(static function () use ($placement, $placementId, $date, $mode): void {
            $visitId = Database::insert('site_visit', [
                'placement_id' => $placementId,
                'mentor_id' => (int) Auth::id(),
                'scheduled_date' => $date,
                'mode' => $mode,
                'status' => 'scheduled',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            Audit::log('visit.scheduled', 'site_visit', $visitId, null, [
                'placement_id' => $placementId,
                'date' => $date,
                'mode' => $mode,
            ]);
            Notify::push(
                (int) $placement['student_user'],
                'visit',
                'Site visit scheduled',
                'Your academic mentor will visit on ' . date('d M Y', strtotime($date)) . ' (' . $mode . ').',
                'index.php?r=student/placement'
            );
            if ($placement['supervisor_id'] !== null) {
                Notify::push(
                    (int) $placement['supervisor_id'],
                    'visit',
                    'Site visit scheduled',
                    'An academic site visit is scheduled for ' . date('d M Y', strtotime($date)) . '.',
                    'index.php?r=supervisor/queue'
                );
            }
        });

        \flash('success', 'Site visit scheduled.');
        \redirect('mentor/visits');
    }

    public function visitComplete(): void
    {
        $visitId = (int) \post('visit_id', 0);
        $visit = Database::one(
            'SELECT v.*, p.student_id, s.user_id AS student_user, u.first_name, u.last_name
             FROM site_visit v
             JOIN placement p ON p.placement_id = v.placement_id
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             WHERE v.visit_id = ? AND v.mentor_id = ?',
            [$visitId, (int) Auth::id()]
        );
        if ($visit === null) {
            Audit::log('visit.denied', 'site_visit', $visitId);
            http_response_code(403);
            View::error(403);
            return;
        }
        if ($visit['status'] === 'completed') {
            \flash('error', 'That visit is already completed.');
            \redirect('mentor/visits');
        }

        $actual = (string) \post('actual_date', '');
        $findings = trim((string) \post('findings', ''));
        $recommendations = trim((string) \post('recommendations', ''));
        if (!Validator::date($actual)) {
            \flash('error', 'Enter the date the visit actually took place.');
            \redirect('mentor/visits');
        }
        if (!Validator::length($findings, 20, 2000) || !Validator::length($recommendations, 10, 2000)) {
            \flash('error', 'Record findings (at least 20 characters) and recommendations (at least 10).');
            \redirect('mentor/visits');
        }

        try {
            Database::tx(static function () use ($visitId, $visit, $actual, $findings, $recommendations): void {
                Database::update('site_visit', [
                    'actual_date' => $actual,
                    'findings' => $findings,
                    'recommendations' => $recommendations,
                    'status' => 'completed',
                    'completed_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'visit_id = ?', [$visitId]);

                if (!empty($_FILES['report']['name'])) {
                    $docId = Upload::store($_FILES['report'], 'visit_report', ['visit_id' => $visitId]);
                    Audit::log('document.uploaded', 'document', $docId, null, ['visit_id' => $visitId]);
                }

                Audit::log('visit.completed', 'site_visit', $visitId, ['status' => $visit['status']], [
                    'status' => 'completed',
                    'actual_date' => $actual,
                ]);
                Notify::push(
                    (int) $visit['student_user'],
                    'visit',
                    'Site visit completed',
                    'Your mentor recorded a site visit on ' . date('d M Y', strtotime($actual)) . '.',
                    'index.php?r=student/placement'
                );
                Notify::toRole(
                    'coordinator',
                    'visit',
                    'Site visit completed',
                    'A site visit for ' . $visit['first_name'] . ' ' . $visit['last_name'] . ' was completed.',
                    'index.php?r=coordinator/visits'
                );
            });
            \flash('success', 'Visit completed and recorded.');
        } catch (RuntimeException $e) {
            \flash('error', $e->getMessage());
        }
        \redirect('mentor/visits');
    }

    public function evaluation(): void
    {
        $placements = $this->placements();
        $placementId = (int) \qget('placement_id', (int) ($placements[0]['placement_id'] ?? 0));
        $placement = $this->ownPlacement($placementId);
        if ($placement === null) {
            \flash('error', 'Choose one of your assigned students.');
            \redirect('mentor/students');
        }

        $criteria = Database::all(
            'SELECT * FROM criterion WHERE evaluation_type = ? AND active = 1 ORDER BY sort_order',
            ['mentor']
        );
        $evaluation = Database::one(
            'SELECT * FROM evaluation WHERE placement_id = ? AND evaluator_id = ? AND evaluation_type = ?',
            [$placementId, (int) Auth::id(), 'mentor']
        );
        $scores = [];
        if ($evaluation !== null) {
            foreach (Database::all('SELECT * FROM evaluation_score WHERE evaluation_id = ?', [(int) $evaluation['evaluation_id']]) as $row) {
                $scores[(int) $row['criterion_id']] = $row;
            }
        }

        View::render('mentor/evaluation', [
            'title' => 'Mentor evaluation',
            'placements' => $placements,
            'placement' => $placement,
            'criteria' => $criteria,
            'evaluation' => $evaluation,
            'scores' => $scores,
            'weightTotal' => \weight_total_for('mentor'),
        ]);
    }

    public function saveEvaluation(): void
    {
        $placementId = (int) \post('placement_id', 0);
        $placement = $this->ownPlacement($placementId);
        if ($placement === null) {
            Audit::log('evaluation.denied', 'placement', $placementId);
            http_response_code(403);
            View::error(403);
            return;
        }

        $criteria = Database::all(
            'SELECT * FROM criterion WHERE evaluation_type = ? AND active = 1 ORDER BY sort_order',
            ['mentor']
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
            \redirect('mentor/evaluation', ['placement_id' => $placementId]);
        }
        if ($submit && abs(\weight_total_for('mentor') - 100) > 0.01) {
            \flash('error', 'Criterion weights must total 100 before an evaluation can be submitted.');
            \redirect('mentor/evaluation', ['placement_id' => $placementId]);
        }

        Database::tx(static function () use ($placementId, $criteria, $clean, $comments, $general, $submit): void {
            $existing = Database::one(
                'SELECT * FROM evaluation WHERE placement_id = ? AND evaluator_id = ? AND evaluation_type = ?',
                [$placementId, (int) Auth::id(), 'mentor']
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
                    'evaluation_type' => 'mentor',
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
                $row = Database::one('SELECT score_id FROM evaluation_score WHERE evaluation_id = ? AND criterion_id = ?', [$evaluationId, $cid]);
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

            Audit::log($submit ? 'evaluation.submitted' : 'evaluation.draft_saved', 'evaluation', $evaluationId, null, [
                'type' => 'mentor',
                'placement_id' => $placementId,
                'total' => $submit ? round($total, 2) : null,
            ]);
            if ($submit) {
                Notify::toRole(
                    'coordinator',
                    'evaluation',
                    'Mentor evaluation submitted',
                    'An academic mentor submitted the evaluation for placement #' . $placementId . '.',
                    'index.php?r=coordinator/grades/view&placement_id=' . $placementId
                );
            }
        });

        \flash('success', $submit ? 'Evaluation submitted.' : 'Draft saved.');
        \redirect('mentor/evaluation', ['placement_id' => $placementId]);
    }

    public function reports(): void
    {
        $search = trim((string) \qget('q', ''));
        $reviewFilter = (string) \qget('review', '');
        $where = ' AND p.mentor_id = ?';
        $params = [(int) Auth::id()];
        if ($search !== '') {
            $where .= " AND CONCAT_WS(' ', u.first_name, u.last_name, s.reg_no, d.original_name) LIKE ?";
            $params[] = '%' . $search . '%';
        }
        if ($reviewFilter === 'reviewed') {
            $where .= ' AND d.reviewed_at IS NOT NULL';
        } elseif ($reviewFilter === 'unreviewed') {
            $where .= ' AND d.reviewed_at IS NULL';
        }
        $rows = Database::all(
            'SELECT r.*, d.original_name, d.reviewed_at,
                    rv.first_name AS reviewer_first, rv.last_name AS reviewer_last,
                    p.placement_id, u.first_name, u.last_name, s.reg_no, o.name AS org_name
             FROM internship_report r
             JOIN document d ON d.doc_id = r.document_id
             LEFT JOIN users rv ON rv.user_id = d.reviewed_by
             JOIN placement p ON p.placement_id = r.placement_id
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             JOIN organisation o ON o.org_id = p.org_id
             WHERE 1 = 1' . $where . ' ORDER BY r.submitted_at DESC',
            $params
        );

        $careerFairs = Database::all(
            'SELECT d.*, rv.first_name AS reviewer_first, rv.last_name AS reviewer_last,
                    u.first_name, u.last_name, s.reg_no
             FROM document d
             JOIN placement p ON p.placement_id = d.placement_id
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             LEFT JOIN users rv ON rv.user_id = d.reviewed_by
             WHERE d.doc_type = ?' . $where . ' ORDER BY d.uploaded_at DESC',
            ['career_fair_report', ...$params]
        );

        View::render('mentor/reports', [
            'title' => 'Report assessment',
            'search' => $search,
            'reviewFilter' => $reviewFilter,
            'reports' => $rows,
            'careerFairs' => $careerFairs,
            'careerFairDeadline' => \career_fair_deadline(),
        ]);
    }

    public function assessReport(): void
    {
        $reportId = (int) \post('report_id', 0);
        $report = Database::one(
            'SELECT r.*, p.student_id, s.user_id AS student_user, u.first_name, u.last_name
             FROM internship_report r
             JOIN placement p ON p.placement_id = r.placement_id
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             WHERE r.report_id = ? AND p.mentor_id = ?',
            [$reportId, (int) Auth::id()]
        );
        if ($report === null) {
            Audit::log('report.denied', 'internship_report', $reportId);
            http_response_code(403);
            View::error(403);
            return;
        }

        $score = (string) \post('score', '');
        $comments = trim((string) \post('comments', ''));
        if (!Validator::decimal($score, 0, 100)) {
            \flash('error', 'Enter a report score between 0 and 100.');
            \redirect('mentor/reports');
        }
        if (!Validator::length($comments, 10, 2000)) {
            \flash('error', 'Write assessment feedback of at least ten characters.');
            \redirect('mentor/reports');
        }

        Database::tx(static function () use ($report, $reportId, $score, $comments): void {
            Database::update('internship_report', [
                'status' => 'assessed',
                'assessed_by' => (int) Auth::id(),
                'assessed_at' => date('Y-m-d H:i:s'),
                'score' => round((float) $score, 2),
                'comments' => $comments,
            ], 'report_id = ?', [$reportId]);
            Audit::log('report.assessed', 'internship_report', $reportId, ['status' => $report['status']], [
                'status' => 'assessed',
                'score' => round((float) $score, 2),
            ]);
            Notify::push(
                (int) $report['student_user'],
                'report',
                'Internship report assessed',
                'Your report scored ' . number_format((float) $score, 1) . '%. Feedback is available on the report page.',
                'index.php?r=student/report'
            );
            Notify::toRole(
                'coordinator',
                'report',
                'Report assessed',
                $report['first_name'] . ' ' . $report['last_name'] . "'s report was assessed at " . number_format((float) $score, 1) . '%.',
                'index.php?r=coordinator/grades&placement_id=' . (int) $report['placement_id']
            );
            Messenger::systemForPlacement((int) $report['placement_id'], 'Internship report assessed by the academic mentor. The score and feedback are available on the report page.');
        });

        \flash('success', 'Report assessed.');
        \redirect('mentor/reports');
    }

    private function placements(): array
    {
        return Database::all(
            'SELECT p.*, u.first_name, u.last_name, u.user_id AS student_user, s.reg_no, s.student_id AS sid, o.name AS org_name
             FROM placement p
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             JOIN organisation o ON o.org_id = p.org_id
             WHERE p.mentor_id = ?
             ORDER BY p.start_date DESC',
            [(int) Auth::id()]
        );
    }

    private function ownPlacement(int $placementId): ?array
    {
        if ($placementId <= 0) {
            return null;
        }
        return Database::one(
            'SELECT p.*, u.first_name, u.last_name, s.reg_no, s.user_id AS student_user, o.name AS org_name
             FROM placement p
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             JOIN organisation o ON o.org_id = p.org_id
             WHERE p.placement_id = ? AND p.mentor_id = ?',
            [$placementId, (int) Auth::id()]
        );
    }

    private function entryFor(int $entryId): ?array
    {
        return Database::one(
            'SELECT e.*, p.placement_id, p.mentor_id, p.supervisor_id, s.user_id AS student_user
             FROM logbook_entry e
             JOIN placement p ON p.placement_id = e.placement_id
             JOIN student s ON s.student_id = p.student_id
             WHERE e.entry_id = ? AND p.mentor_id = ?',
            [$entryId, (int) Auth::id()]
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
             WHERE c.entry_id = ? ORDER BY c.created_at, c.comment_id',
            [$entryId]
        );
    }
}
