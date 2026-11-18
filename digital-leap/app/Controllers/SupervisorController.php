<?php
declare(strict_types=1);

namespace Leap\Controllers;

use Leap\Core\Audit;
use Leap\Core\Auth;
use Leap\Core\Database;
use Leap\Core\Guard;
use Leap\Core\Notify;
use Leap\Core\Validator;
use Leap\Core\View;

final class SupervisorController
{
    public function __construct()
    {
        Guard::requireRole('supervisor');
    }

    public function queue(): void
    {
        $placements = $this->placements();
        $status = (string) \qget('status', 'submitted');
        if (!in_array($status, ['', 'submitted', 'approved', 'returned', 'mentor_reviewed'], true)) {
            $status = 'submitted';
        }
        $week = max(0, (int) \qget('week', 0));
        $where = " WHERE p.supervisor_id = ? AND e.status <> 'draft'";
        $params = [(int) Auth::id()];
        if ($status !== '') {
            $where .= ' AND e.status = ?';
            $params[] = $status;
        }
        if ($week > 0) {
            $where .= ' AND e.week_no = ?';
            $params[] = $week;
        }
        $entries = Database::all(
            'SELECT e.*, s.reg_no, u.first_name, u.last_name, o.name AS org_name
             FROM logbook_entry e
             JOIN placement p ON p.placement_id = e.placement_id
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             JOIN organisation o ON o.org_id = p.org_id' . $where . '
             ORDER BY e.submitted_at DESC, e.entry_id DESC',
            $params
        );

        $evaluations = [];
        foreach ($placements as $placement) {
            $evaluations[(int) $placement['placement_id']] = Database::one(
                'SELECT e.* FROM evaluation e WHERE e.placement_id = ? AND e.evaluator_id = ? AND e.evaluation_type = ?',
                [(int) $placement['placement_id'], (int) Auth::id(), 'supervisor']
            );
        }

        View::render('supervisor/queue', [
            'title' => 'Logbook queue',
            'placements' => $placements,
            'entries' => $entries,
            'evaluations' => $evaluations,
            'status' => $status,
            'week' => $week,
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

        View::render('supervisor/entry', [
            'title' => 'Week ' . $entry['week_no'] . ' entry',
            'entry' => $entry,
            'comments' => $this->comments($id),
            'attachments' => Database::all(
                'SELECT d.*, rv.first_name AS reviewer_first, rv.last_name AS reviewer_last
                 FROM document d LEFT JOIN users rv ON rv.user_id = d.reviewed_by
                 WHERE d.entry_id = ? ORDER BY d.uploaded_at',
                [$id]
            ),
        ]);
    }

    public function approve(): void
    {
        $id = (int) \post('entry_id', 0);
        $entry = $this->entryFor($id);
        if ($entry === null) {
            Audit::log('logbook.denied', 'logbook_entry', $id);
            http_response_code(403);
            View::error(403);
            return;
        }
        if ($entry['status'] !== 'submitted') {
            \flash('error', 'Only submitted entries can be approved.');
            \redirect('supervisor/queue');
        }

        $comment = trim((string) \post('comment', ''));
        if (!Validator::length($comment, 0, 1000)) {
            \flash('error', 'Keep the comment under 1000 characters.');
            \redirect('supervisor/entry', ['id' => $id]);
        }

        Database::tx(static function () use ($entry, $id, $comment): void {
            Database::update('logbook_entry', [
                'status' => 'approved',
                'reviewed_by' => (int) Auth::id(),
                'reviewed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'entry_id = ?', [$id]);
            if ($comment !== '') {
                Database::insert('logbook_comment', [
                    'entry_id' => $id,
                    'user_id' => (int) Auth::id(),
                    'body' => $comment,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
            Audit::log('logbook.approved', 'logbook_entry', $id, ['status' => $entry['status']], ['status' => 'approved', 'comment' => $comment !== '']);
            Notify::push(
                (int) $entry['student_user'],
                'logbook',
                'Week ' . $entry['week_no'] . ' approved',
                'Your host supervisor approved week ' . $entry['week_no'] . '.' . ($comment !== '' ? ' Comment: ' . $comment : ''),
                'index.php?r=student/logbook/view&id=' . $id
            );
            Notify::push(
                (int) $entry['mentor_id'],
                'logbook',
                'Week ' . $entry['week_no'] . ' approved by supervisor',
                $entry['first_name'] . ' ' . $entry['last_name'] . ' had week ' . $entry['week_no'] . ' approved.',
                'index.php?r=mentor/entry&id=' . $id
            );
        });

        \flash('success', 'Week ' . $entry['week_no'] . ' approved.');
        \redirect('supervisor/queue');
    }

    public function returnEntry(): void
    {
        $id = (int) \post('entry_id', 0);
        $entry = $this->entryFor($id);
        if ($entry === null) {
            Audit::log('logbook.denied', 'logbook_entry', $id);
            http_response_code(403);
            View::error(403);
            return;
        }
        if ($entry['status'] !== 'submitted') {
            \flash('error', 'Only submitted entries can be returned.');
            \redirect('supervisor/queue');
        }

        $comment = trim((string) \post('comment', ''));
        if (!Validator::length($comment, 10, 1000)) {
            \flash('error', 'Explain what must change: at least ten characters.');
            \redirect('supervisor/entry', ['id' => $id]);
        }

        Database::tx(static function () use ($entry, $id, $comment): void {
            Database::update('logbook_entry', [
                'status' => 'returned',
                'reviewed_by' => (int) Auth::id(),
                'reviewed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'entry_id = ?', [$id]);
            Database::insert('logbook_comment', [
                'entry_id' => $id,
                'user_id' => (int) Auth::id(),
                'body' => $comment,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            Audit::log('logbook.returned', 'logbook_entry', $id, ['status' => $entry['status']], ['status' => 'returned', 'reason' => $comment]);
            Notify::push(
                (int) $entry['student_user'],
                'logbook',
                'Week ' . $entry['week_no'] . ' returned',
                'Your host supervisor returned week ' . $entry['week_no'] . ': ' . $comment,
                'index.php?r=student/logbook/view&id=' . $id
            );
            Notify::push(
                (int) $entry['mentor_id'],
                'logbook',
                'Week ' . $entry['week_no'] . ' returned',
                $entry['first_name'] . ' ' . $entry['last_name'] . ' must rework week ' . $entry['week_no'] . '.',
                'index.php?r=mentor/entry&id=' . $id
            );
        });

        \flash('success', 'Entry returned with your comment.');
        \redirect('supervisor/queue');
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
            \redirect('supervisor/entry', ['id' => $id]);
        }
        $parentId = (int) \post('parent_id', 0);
        if ($parentId > 0) {
            $exists = (int) Database::val('SELECT COUNT(*) FROM logbook_comment WHERE comment_id = ? AND entry_id = ?', [$parentId, $id]);
            if ($exists === 0) {
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
            Audit::log('logbook.comment_added', 'logbook_comment', $commentId, null, [
                'entry_id' => $id,
                'by' => 'supervisor',
                'reply_to' => $parentId > 0 ? $parentId : null,
            ]);
            Notify::push(
                (int) $entry['student_user'],
                'comment',
                'Supervisor comment on week ' . $entry['week_no'],
                Auth::name() . ' commented on week ' . $entry['week_no'] . ': ' . $body,
                'index.php?r=student/logbook/view&id=' . $id
            );
            if ((int) $entry['mentor_id'] > 0) {
                Notify::push(
                    (int) $entry['mentor_id'],
                    'comment',
                    'Supervisor comment on week ' . $entry['week_no'],
                    Auth::name() . ' responded in the week ' . $entry['week_no'] . ' thread.',
                    'index.php?r=mentor/entry&id=' . $id
                );
            }

            return (int) $commentId;
        });

        \flash('success', 'Comment posted.');
        \redirect('supervisor/entry', ['id' => $id], 'comment-' . $commentId);
    }

    public function evaluation(): void
    {
        $placements = $this->placements();
        $placementId = (int) \qget('placement_id', (int) ($placements[0]['placement_id'] ?? 0));
        $placement = $this->ownPlacement($placementId);
        if ($placement === null) {
            \flash('error', 'Choose one of your assigned students.');
            \redirect('supervisor/queue');
        }

        $criteria = Database::all(
            'SELECT * FROM criterion WHERE evaluation_type = ? AND active = 1 ORDER BY sort_order',
            ['supervisor']
        );
        $evaluation = Database::one(
            'SELECT * FROM evaluation WHERE placement_id = ? AND evaluator_id = ? AND evaluation_type = ?',
            [$placementId, (int) Auth::id(), 'supervisor']
        );
        $scores = [];
        if ($evaluation !== null) {
            $rows = Database::all('SELECT * FROM evaluation_score WHERE evaluation_id = ?', [(int) $evaluation['evaluation_id']]);
            foreach ($rows as $row) {
                $scores[(int) $row['criterion_id']] = $row;
            }
        }

        View::render('supervisor/evaluation', [
            'title' => 'Supervisor evaluation',
            'placements' => $placements,
            'placement' => $placement,
            'criteria' => $criteria,
            'evaluation' => $evaluation,
            'scores' => $scores,
            'weightTotal' => \weight_total_for('supervisor'),
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
            ['supervisor']
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
            \redirect('supervisor/evaluation', ['placement_id' => $placementId]);
        }
        if ($submit && abs(\weight_total_for('supervisor') - 100) > 0.01) {
            \flash('error', 'Criterion weights must total 100 before an evaluation can be submitted.');
            \redirect('supervisor/evaluation', ['placement_id' => $placementId]);
        }

        Database::tx(static function () use ($placementId, $placement, $criteria, $clean, $comments, $general, $submit): void {
            $existing = Database::one(
                'SELECT * FROM evaluation WHERE placement_id = ? AND evaluator_id = ? AND evaluation_type = ?',
                [$placementId, (int) Auth::id(), 'supervisor']
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
                    'evaluation_type' => 'supervisor',
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
                ['type' => 'supervisor', 'placement_id' => $placementId, 'total' => $submit ? round($total, 2) : null]
            );

            if ($submit) {
                Notify::toRole(
                    'coordinator',
                    'evaluation',
                    'Supervisor evaluation submitted',
                    'A host supervisor submitted the evaluation for ' . $placement['first_name'] . ' ' . $placement['last_name'] . '.',
                    'index.php?r=coordinator/grades/view&placement_id=' . $placementId
                );
            }
        });

        \flash('success', $submit ? 'Evaluation submitted. Thank you.' : 'Draft saved.');
        \redirect('supervisor/evaluation', ['placement_id' => $placementId]);
    }

    private function placements(): array
    {
        return Database::all(
            'SELECT p.*, u.first_name, u.last_name, s.reg_no, o.name AS org_name
             FROM placement p
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             JOIN organisation o ON o.org_id = p.org_id
             WHERE p.supervisor_id = ?
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
            'SELECT p.*, u.first_name, u.last_name, s.reg_no, s.student_id AS sid, o.name AS org_name
             FROM placement p
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             JOIN organisation o ON o.org_id = p.org_id
             WHERE p.placement_id = ? AND p.supervisor_id = ?',
            [$placementId, (int) Auth::id()]
        );
    }

    private function entryFor(int $entryId): ?array
    {
        return Database::one(
            'SELECT e.*, p.mentor_id, p.supervisor_id, s.user_id AS student_user,
                    u.first_name, u.last_name
             FROM logbook_entry e
             JOIN placement p ON p.placement_id = e.placement_id
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             WHERE e.entry_id = ? AND p.supervisor_id = ?',
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
