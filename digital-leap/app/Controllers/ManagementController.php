<?php
declare(strict_types=1);

namespace Leap\Controllers;

use Leap\Core\Database;
use Leap\Core\View;

final class ManagementController
{
    public function kpis(): void
    {
        $year = $this->year();
        $data = $this->summary($year);

        View::render('management/kpis', [
            'title' => 'Quality indicators',
            'year' => $year,
            'summary' => $data['summary'],
            'byOrg' => $data['byOrg'],
            'submission' => $data['submission'],
            'grades' => $data['grades'],
            'visits' => $data['visits'],
        ]);
    }

    public function printView(): void
    {
        $year = $this->year();
        $data = $this->summary($year);

        View::render('management/print_report', [
            'title' => 'Management report ' . $year,
            'year' => $year,
            'summary' => $data['summary'],
            'byOrg' => $data['byOrg'],
            'submission' => $data['submission'],
            'grades' => $data['grades'],
            'visits' => $data['visits'],
        ], '');
    }

    private function summary(string $year): array
    {
        $placements = (int) Database::val('SELECT COUNT(*) FROM placement WHERE academic_year = ?', [$year]);
        $active = (int) Database::val("SELECT COUNT(*) FROM placement WHERE academic_year = ? AND status = 'active'", [$year]);
        $completed = (int) Database::val("SELECT COUNT(*) FROM placement WHERE academic_year = ? AND status = 'completed'", [$year]);
        $average = Database::val(
            "SELECT ROUND(AVG(m.weighted_total), 2) FROM mark m JOIN placement p ON p.placement_id = m.placement_id WHERE m.status = 'published' AND p.academic_year = ?",
            [$year]
        );
        $overdue = (int) Database::val(
            "SELECT COUNT(*) FROM placement p
             WHERE p.status = 'active' AND p.academic_year = ?
               AND (SELECT COUNT(*) FROM logbook_entry e
                    WHERE e.placement_id = p.placement_id AND e.status <> 'draft')
                   < LEAST(p.total_weeks, GREATEST(0, FLOOR(DATEDIFF(CURDATE(), p.start_date) / 7)))",
            [$year]
        );
        $outstandingVisits = (int) Database::val("SELECT COUNT(*) FROM site_visit WHERE status = 'scheduled'");
        $published = (int) Database::val(
            "SELECT COUNT(*) FROM mark m JOIN placement p ON p.placement_id = m.placement_id WHERE m.status = 'published' AND p.academic_year = ?",
            [$year]
        );
        $submittedEntries = (int) Database::val(
            "SELECT COUNT(*) FROM logbook_entry e JOIN placement p ON p.placement_id = e.placement_id WHERE p.academic_year = ? AND e.status <> 'draft'",
            [$year]
        );

        $byOrg = Database::all(
            'SELECT o.name, o.district, COUNT(p.placement_id) AS total,
                    SUM(CASE WHEN p.status = ? THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN p.status = ? THEN 1 ELSE 0 END) AS active
             FROM organisation o
             JOIN placement p ON p.org_id = o.org_id AND p.academic_year = ?
             GROUP BY o.org_id, o.name, o.district
             ORDER BY total DESC, o.name',
            ['completed', 'active', $year]
        );

        $submission = Database::all(
            'SELECT e.week_no, COUNT(DISTINCT e.placement_id) AS students, COUNT(*) AS entries
             FROM logbook_entry e JOIN placement p ON p.placement_id = e.placement_id
             WHERE p.academic_year = ? AND e.status <> ?
             GROUP BY e.week_no ORDER BY e.week_no',
            [$year, 'draft']
        );

        $grades = Database::all(
            'SELECT g.grade_letter, g.classification, COUNT(x.mark_id) AS total
             FROM grade_scale g
             LEFT JOIN (
                 SELECT m.mark_id, m.grade_id
                 FROM mark m JOIN placement p ON p.placement_id = m.placement_id
                 WHERE m.status = ? AND p.academic_year = ?
             ) x ON x.grade_id = g.grade_id
             GROUP BY g.grade_id, g.grade_letter, g.classification
             ORDER BY g.min_score DESC',
            ['published', $year]
        );

        $visits = Database::all(
            "SELECT status, COUNT(*) AS total FROM site_visit GROUP BY status ORDER BY status"
        );

        return [
            'summary' => [
                'year' => $year,
                'placements' => $placements,
                'active' => $active,
                'completed' => $completed,
                'completion' => $placements > 0 ? round($completed / $placements * 100, 1) : 0.0,
                'average' => $average === null ? null : (float) $average,
                'overdue' => $overdue,
                'outstandingVisits' => $outstandingVisits,
                'published' => $published,
                'entries' => $submittedEntries,
            ],
            'byOrg' => $byOrg,
            'submission' => $submission,
            'grades' => $grades,
            'visits' => $visits,
        ];
    }

    private function year(): string
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
