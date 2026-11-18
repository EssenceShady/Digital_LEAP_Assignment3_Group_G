<?php
declare(strict_types=1);

namespace Leap\Controllers;

use Leap\Core\Audit;
use Leap\Core\Database;

final class ExportController
{
    public function csv(): void
    {
        $year = trim((string) \qget('year', ''));
        if ($year === '') {
            $year = $this->year();
        }
        $type = (string) \qget('type', 'placements');

        if ($type === 'grades') {
            $rows = Database::all(
                'SELECT p.academic_year, s.reg_no, s.programme, u.first_name, u.last_name,
                        o.name AS org_name, m.logbook_pct, m.supervisor_pct, m.self_pct,
                        m.practical_pct, m.report_pct, m.career_fair_pct,
                        m.weighted_total, m.status AS mark_status, g.grade_letter,
                        m.moderated_at, m.published_at
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
            $header = ['Academic year', 'Registration no', 'Student', 'Programme', 'Organisation',
                'Logbook %', 'Form D %', 'Self %', 'Practical %', 'Report %', 'Career Fair %',
                'Final %', 'Grade', 'Mark status', 'Published at'];
            $lines = [];
            foreach ($rows as $row) {
                $lines[] = [
                    $row['academic_year'],
                    $row['reg_no'],
                    $row['last_name'] . ', ' . $row['first_name'],
                    $row['programme'],
                    $row['org_name'],
                    $row['logbook_pct'],
                    $row['supervisor_pct'],
                    $row['self_pct'],
                    $row['practical_pct'],
                    $row['report_pct'],
                    $row['career_fair_pct'],
                    $row['weighted_total'],
                    $row['grade_letter'],
                    $row['mark_status'],
                    $row['published_at'],
                ];
            }
            Audit::log('export.grades', 'mark', null, null, ['academic_year' => $year, 'rows' => count($lines)]);
            $this->send('digital-leap-grade-sheet-' . str_replace('/', '-', $year) . '.csv', $header, $lines);
            return;
        }

        $rows = Database::all(
            'SELECT p.academic_year, s.reg_no, s.programme, s.level, u.first_name, u.last_name,
                    o.name AS org_name, o.district, mu.last_name AS mentor_last,
                    su.last_name AS supervisor_last, p.start_date, p.end_date, p.total_weeks, p.status
             FROM placement p
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             JOIN organisation o ON o.org_id = p.org_id
             JOIN users mu ON mu.user_id = p.mentor_id
             LEFT JOIN users su ON su.user_id = p.supervisor_id
             WHERE p.academic_year = ?
             ORDER BY o.name, u.last_name',
            [$year]
        );
        $header = ['Academic year', 'Registration no', 'Student', 'Programme', 'Year', 'Organisation',
            'District', 'Academic mentor', 'Host supervisor', 'Start date', 'End date', 'Weeks', 'Status'];
        $lines = [];
        foreach ($rows as $row) {
            $lines[] = [
                $row['academic_year'],
                $row['reg_no'],
                $row['last_name'] . ', ' . $row['first_name'],
                $row['programme'],
                $row['level'],
                $row['org_name'],
                $row['district'],
                $row['mentor_last'],
                $row['supervisor_last'],
                $row['start_date'],
                $row['end_date'],
                $row['total_weeks'],
                $row['status'],
            ];
        }
        Audit::log('export.placements', 'placement', null, null, ['academic_year' => $year, 'rows' => count($lines)]);
        $this->send('digital-leap-placements-' . str_replace('/', '-', $year) . '.csv', $header, $lines);
    }

    private function send(string $filename, array $header, array $lines): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            throw new \RuntimeException('The export stream could not be opened.');
        }
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header);
        foreach ($lines as $line) {
            fputcsv($out, $line);
        }
        fclose($out);
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
