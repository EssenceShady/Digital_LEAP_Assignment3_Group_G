<?php
declare(strict_types=1);

namespace Leap\Controllers;

use Leap\Core\Audit;
use Leap\Core\Auth;
use Leap\Core\Database;
use Leap\Core\View;

final class ExportPdfController
{
    public function letter(): void
    {
        $id = (int) \qget('id', 0);
        $placement = $this->placement($id);
        if ($placement === null) {
            \flash('error', 'Placement not found.');
            \redirect('coordinator/placements');
        }
        $this->renderLetter($placement, 'coordinator/placements/view');
    }

    public function studentLetter(): void
    {
        $id = (int) \qget('id', 0);
        $placement = $this->placement($id);
        $studentId = (int) Database::val(
            'SELECT student_id FROM student WHERE user_id = ?',
            [(int) Auth::id()]
        );
        if ($placement === null || (int) $placement['student_id'] !== $studentId) {
            http_response_code(403);
            View::error(403);
            return;
        }
        $this->renderLetter($placement, 'student/placement');
    }

    public function gradeSheet(): void
    {
        $year = trim((string) \qget('year', ''));
        if ($year === '') {
            $year = (string) Database::val(
                'SELECT academic_year FROM placement WHERE status IN (?,?) GROUP BY academic_year ORDER BY academic_year DESC LIMIT 1',
                ['active', 'approved']
            );
        }
        $rows = Database::all(
            'SELECT s.reg_no, s.programme, u.first_name, u.last_name, o.name AS org_name,
                    m.logbook_pct, m.supervisor_pct, m.self_pct, m.practical_pct,
                    m.report_pct, m.career_fair_pct,
                    m.weighted_total, m.status AS mark_status, g.grade_letter
             FROM placement p
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             JOIN organisation o ON o.org_id = p.org_id
             LEFT JOIN mark m ON m.placement_id = p.placement_id
             LEFT JOIN grade_scale g ON g.grade_id = m.grade_id
             WHERE p.academic_year = ?
             ORDER BY u.last_name, u.first_name',
            [$year]
        );

        Audit::log('export.grade_sheet', 'mark', null, null, ['academic_year' => $year, 'rows' => count($rows)]);

        View::render('pdf/grade_sheet', [
            'title' => 'Grade sheet ' . $year,
            'year' => $year,
            'rows' => $rows,
            'weights' => [
                'logbook' => (float) \Leap\Core\Settings::get('weight_logbook', 50),
                'supervisor' => (float) \Leap\Core\Settings::get('weight_supervisor', 40),
                'self' => (float) \Leap\Core\Settings::get('weight_self', 10),
                'report' => (float) \Leap\Core\Settings::get('weight_report', 75),
                'career_fair' => (float) \Leap\Core\Settings::get('weight_career_fair', 25),
            ],
        ], '');
    }

    private function renderLetter(array $placement, string $backRoute): void
    {
        if ($placement['status'] === 'proposed') {
            \flash('error', 'The letter becomes available once the placement is approved.');
            \redirect($backRoute, ['id' => (int) $placement['placement_id']]);
        }

        Audit::log('letter.rendered', 'placement', (int) $placement['placement_id'], null, [
            'academic_year' => $placement['academic_year'],
        ]);

        View::render('pdf/letter', [
            'title' => 'Placement letter ' . $placement['reg_no'],
            'placement' => $placement,
            'back' => \url($backRoute, ['id' => (int) $placement['placement_id']]),
            'reference' => sprintf('DL/PL/%s/%04d', substr($placement['academic_year'], 0, 4), (int) $placement['placement_id']),
        ], '');
    }

    private function placement(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return Database::one(
            'SELECT p.*, s.student_id, s.reg_no, s.programme, s.level, s.semester,
                    u.first_name, u.last_name, u.email,
                    o.name AS org_name, o.address AS org_address, o.district AS org_district,
                    o.contact_person, o.contact_email,
                    m.first_name AS mentor_first, m.last_name AS mentor_last,
                    sup.first_name AS sup_first, sup.last_name AS sup_last,
                    c.first_name AS coord_first, c.last_name AS coord_last
             FROM placement p
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             JOIN organisation o ON o.org_id = p.org_id
             JOIN users m ON m.user_id = p.mentor_id
             JOIN users c ON c.user_id = p.coordinator_id
             LEFT JOIN users sup ON sup.user_id = p.supervisor_id
             WHERE p.placement_id = ?',
            [$id]
        );
    }
}
