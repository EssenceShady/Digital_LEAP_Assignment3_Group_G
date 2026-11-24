<?php
declare(strict_types=1);

namespace Leap\Services;

use DateTimeImmutable;
use Leap\Core\Audit;
use Leap\Core\Database;
use Leap\Core\Notify;

/**
 * Plain-text logbook emails: one daily record per working day for the host
 * supervisor and academic mentor, and the full week when a student submits.
 *
 * The daily job records each delivery in audit_log under the entry (not the
 * day row) because editing a week rewrites logbook_day rows. The audit trail
 * therefore doubles as the "already sent" marker without a new table.
 */
final class LogbookDigest
{
    /**
     * Email every day recorded in the last two days that the recipients have
     * not already received. Safe to run more than once.
     *
     * @return array{days: int, sent: int, skipped: int, preview: string[], dry_run: bool}
     */
    public static function sendDaily(DateTimeImmutable $asOf, bool $dryRun = false): array
    {
        $since = $asOf->modify('-2 days')->format('Y-m-d 00:00:00');
        $rows = Database::all(
            'SELECT d.entry_id, d.work_date, d.tasks, d.hours,
                    e.week_no, e.status AS entry_status,
                    p.placement_id, p.start_date, p.end_date, p.total_weeks,
                    p.supervisor_id, p.mentor_id,
                    o.name AS org_name, o.district,
                    u.first_name, u.last_name
             FROM logbook_day d
             JOIN logbook_entry e ON e.entry_id = d.entry_id
             JOIN placement p ON p.placement_id = e.placement_id
             JOIN organisation o ON o.org_id = p.org_id
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             WHERE p.status = ? AND e.status = ? AND d.created_at >= ?
             ORDER BY p.placement_id, d.work_date, d.entry_id',
            ['active', 'draft', $since]
        );

        $groups = [];
        $stats = ['days' => 0, 'sent' => 0, 'skipped' => 0, 'preview' => [], 'dry_run' => $dryRun];

        foreach ($rows as $row) {
            $workDate = (string) $row['work_date'];
            if ($workDate < (string) $row['start_date'] || $workDate > (string) $row['end_date']) {
                continue;
            }
            $recipients = [];
            if ($row['supervisor_id'] !== null) {
                $recipients[] = (int) $row['supervisor_id'];
            }
            $recipients[] = (int) $row['mentor_id'];

            foreach (array_unique($recipients) as $userId) {
                if (self::alreadySent((int) $row['entry_id'], $workDate, $userId)) {
                    $stats['skipped']++;
                    continue;
                }
                $key = (int) $row['placement_id'] . ':' . $userId;
                $groups[$key]['user_id'] = $userId;
                $groups[$key]['rows'][$row['entry_id'] . '|' . $workDate] = $row;
            }
        }

        foreach ($groups as $group) {
            $groupRows = array_values($group['rows']);
            usort($groupRows, static fn (array $a, array $b): int => [$a['work_date'], $a['entry_id']] <=> [$b['work_date'], $b['entry_id']]);
            $first = $groupRows[0];
            $userId = (int) $group['user_id'];
            $isSupervisor = (int) $first['supervisor_id'] === $userId;
            $studentName = trim((string) $first['first_name'] . ' ' . (string) $first['last_name']);
            $singleEntry = count(array_unique(array_map(static fn (array $r): int => (int) $r['entry_id'], $groupRows))) === 1;

            if ($singleEntry) {
                $route = $isSupervisor ? 'supervisor/entry' : 'mentor/entry';
                $params = ['id' => (int) $first['entry_id']];
            } else {
                $route = $isSupervisor ? 'supervisor/queue' : 'mentor/logbook';
                $params = [];
            }
            $link = self::link($route, $params);
            [$subject, $body] = self::dailyMail($groupRows, $first, $studentName, $link);

            $stats['days'] += count($groupRows);
            $stats['preview'][] = sprintf(
                '%s -> %s (%s): %d day%s [%s]',
                $studentName,
                $isSupervisor ? 'supervisor' : 'mentor',
                (string) $first['org_name'],
                count($groupRows),
                count($groupRows) === 1 ? '' : 's',
                $subject
            );

            if ($dryRun) {
                continue;
            }

            $days = array_map(static fn (array $r): string => self::dateLabel((string) $r['work_date']), $groupRows);
            $short = $days[0] . (count($days) > 1 ? ' and ' . (count($days) - 1) . ' more day' . (count($days) > 2 ? 's' : '') : '')
                . ' recorded at ' . (string) $first['org_name'] . '.';
            Notify::push($userId, 'logbook', $subject, $short, $link, true, $body);
            foreach ($groupRows as $row) {
                Audit::log('logbook.day_notice', 'logbook_entry', (int) $row['entry_id'], null, [
                    'to' => $userId,
                    'work_date' => (string) $row['work_date'],
                ]);
            }
            $stats['sent']++;
        }

        return $stats;
    }

    /**
     * Send one recorded day now, from the student's entry view. The remarks
     * travel to whichever of the supervisor and mentor have not had it yet.
     *
     * @return array{ok: bool, message: string}
     */
    public static function sendDay(int $entryId, string $workDate): array
    {
        $row = Database::one(
            'SELECT d.work_date, d.tasks, d.hours, e.week_no, e.status AS entry_status,
                    p.start_date, p.end_date, p.total_weeks, p.supervisor_id, p.mentor_id,
                    o.name AS org_name, o.district, u.first_name, u.last_name
             FROM logbook_day d
             JOIN logbook_entry e ON e.entry_id = d.entry_id
             JOIN placement p ON p.placement_id = e.placement_id
             JOIN organisation o ON o.org_id = p.org_id
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             WHERE d.entry_id = ? AND d.work_date = ?',
            [$entryId, $workDate]
        );
        if ($row === null) {
            return ['ok' => false, 'message' => 'That day is not recorded on this entry.'];
        }
        if (!in_array((string) $row['entry_status'], ['draft', 'returned'], true)) {
            return ['ok' => false, 'message' => 'The week has been submitted; its full record was already emailed.'];
        }

        $studentName = trim((string) $row['first_name'] . ' ' . (string) $row['last_name']);
        $recipients = [];
        if ($row['supervisor_id'] !== null) {
            $recipients[] = (int) $row['supervisor_id'];
        }
        $recipients[] = (int) $row['mentor_id'];

        $sent = [];
        foreach (array_unique($recipients) as $userId) {
            if (self::alreadySent((int) $entryId, $workDate, $userId)) {
                continue;
            }
            $isSupervisor = (int) $row['supervisor_id'] === $userId;
            $link = self::link($isSupervisor ? 'supervisor/entry' : 'mentor/entry', ['id' => $entryId]);
            [$subject, $body] = self::dailyMail([$row], $row, $studentName, $link);
            Notify::push(
                $userId,
                'logbook',
                $subject,
                self::dateLabel($workDate) . ' recorded at ' . (string) $row['org_name'] . '.',
                $link,
                true,
                $body
            );
            Audit::log('logbook.day_notice', 'logbook_entry', $entryId, null, [
                'to' => $userId,
                'work_date' => $workDate,
            ]);
            $sent[] = $isSupervisor ? 'host supervisor' : 'academic mentor';
        }

        if ($sent === []) {
            return ['ok' => false, 'message' => 'That day has already been sent to your supervisor and mentor.'];
        }
        return ['ok' => true, 'message' => self::dateLabel($workDate) . ' sent to your ' . self::sentenceList($sent) . '.'];
    }

    /**
     * Days already delivered, for the Sent badge on the entry.
     *
     * @return array<string, int[]> work_date => recipient user ids
     */
    public static function deliveredDays(int $entryId): array
    {
        $rows = Database::all(
            'SELECT after_json FROM audit_log WHERE action = ? AND entity_type = ? AND entity_id = ?',
            ['logbook.day_notice', 'logbook_entry', $entryId]
        );
        $map = [];
        foreach ($rows as $row) {
            $data = json_decode((string) $row['after_json'], true);
            if (is_array($data) && isset($data['work_date'], $data['to'])) {
                $map[(string) $data['work_date']][] = (int) $data['to'];
            }
        }
        return $map;
    }

    /**
     * Full weekly record sent to the supervisor and mentor on submission.
     *
     * @param array<string, mixed> $entry week_no, date_from, date_to, hours, activities,
     *                                    technical_skills, challenges, meeting_notes, reflection, submitted_at
     * @param array<int, array<string, mixed>> $days rows with work_date, tasks, hours
     * @param array<string, mixed> $placement org_name, district, total_weeks, supervisor_id
     * @param array<string, mixed> $student first_name, last_name
     * @return array{0: string, 1: string} subject and body
     */
    public static function weeklyMail(array $entry, array $days, array $placement, array $student, string $route, array $params = []): array
    {
        $studentName = trim((string) $student['first_name'] . ' ' . (string) $student['last_name']);
        $verb = str_starts_with($route, 'supervisor') ? 'awaiting review' : 'submitted';
        $subject = 'Week ' . (int) $entry['week_no'] . ' logbook ' . $verb . ' — ' . $studentName;

        $lines = [
            'Digital LEAP · weekly logbook record',
            '',
            'Student: ' . $studentName,
            'Placement: ' . (string) $placement['org_name'] . self::place($placement['district'] ?? null),
            'Week ' . (int) $entry['week_no'] . ' of ' . (int) $placement['total_weeks'] . ' · '
                . self::dateLabel((string) $entry['date_from']) . ' to ' . self::dateLabel((string) $entry['date_to'])
                . ' · ' . self::hoursLabel($entry['hours'] ?? null),
            '',
            'Daily record',
        ];
        if ($days === []) {
            $lines[] = '  No daily rows were saved for this week.';
        }
        foreach ($days as $day) {
            $lines[] = '• ' . self::dateLabel((string) $day['work_date']) . ' · ' . self::hoursLabel($day['hours'] ?? null);
            foreach (preg_split('/\R/', trim((string) $day['tasks'])) ?: [] as $taskLine) {
                if (trim($taskLine) !== '') {
                    $lines[] = '    ' . trim($taskLine);
                }
            }
        }
        $lines[] = '';
        $lines[] = 'Weekly summary';
        $lines[] = 'What I did: ' . trim((string) $entry['activities']);

        $sections = [
            'Technical skills' => $entry['technical_skills'] ?? '',
            'Challenges' => $entry['challenges'] ?? '',
            'Meeting notes' => $entry['meeting_notes'] ?? '',
            'Reflection' => $entry['reflection'] ?? '',
        ];
        foreach ($sections as $label => $text) {
            if (trim((string) $text) !== '') {
                $lines[] = $label . ': ' . trim((string) $text);
            }
        }

        $lines[] = '';
        $lines[] = 'Total hours: ' . self::hoursLabel($entry['hours'] ?? null) . '.';
        if (!empty($entry['submitted_at'])) {
            $lines[] = 'Submitted for review on ' . fmt_dt((string) $entry['submitted_at']) . '.';
        }
        $lines[] = 'Open the entry: ' . self::link($route, $params);
        $lines[] = '';
        $lines[] = 'You receive this because you supervise or mentor this placement. Email notifications can be turned off in Settings.';

        return [$subject, implode("\n", $lines)];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private static function dailyMail(array $rows, array $first, string $studentName, string $link): array
    {
        $labels = array_map(static fn (array $r): string => self::dateLabel((string) $r['work_date']), $rows);
        if (count($labels) === 1) {
            $range = $labels[0];
        } else {
            $range = $labels[0] . ' to ' . $labels[count($labels) - 1];
        }
        $subject = 'Daily record — ' . $studentName . ' — ' . $range;

        $lines = [
            'Digital LEAP · daily logbook record',
            '',
            'Student: ' . $studentName,
            'Placement: ' . (string) $first['org_name'] . self::place($first['district'] ?? null),
            '',
        ];

        $anyDraft = false;
        foreach ($rows as $row) {
            if ((string) $row['entry_status'] === 'draft') {
                $anyDraft = true;
            }
            $lines[] = 'Week ' . (int) $row['week_no'] . ' · ' . self::dateLabel((string) $row['work_date'])
                . ' · ' . self::hoursLabel($row['hours'] ?? null)
                . ' · ' . ((string) $row['entry_status'] === 'draft' ? 'draft' : 'submitted');
            foreach (preg_split('/\R/', trim((string) $row['tasks'])) ?: [] as $taskLine) {
                if (trim($taskLine) !== '') {
                    $lines[] = '    ' . trim($taskLine);
                }
            }
            $lines[] = '';
        }

        if ($anyDraft) {
            $firstName = trim((string) $first['first_name']);
            $lines[] = 'Draft days are shared so you can follow progress. The week starts formal review when '
                . ($firstName !== '' ? $firstName : 'the student') . ' submits it.';
            $lines[] = '';
        }

        $lines[] = 'Open the logbook: ' . $link;
        $lines[] = '';
        $lines[] = 'You receive this because you supervise or mentor this placement. Email notifications can be turned off in Settings.';

        return [$subject, implode("\n", $lines)];
    }

    private static function alreadySent(int $entryId, string $workDate, int $userId): bool
    {
        return (int) Database::val(
            "SELECT COUNT(*) FROM audit_log
             WHERE action = ? AND entity_type = ? AND entity_id = ?
               AND JSON_UNQUOTE(JSON_EXTRACT(after_json, '$.to')) = ?
               AND JSON_UNQUOTE(JSON_EXTRACT(after_json, '$.work_date')) = ?",
            ['logbook.day_notice', 'logbook_entry', $entryId, (string) $userId, $workDate]
        ) > 0;
    }

    /** @param string[] $parts */
    private static function sentenceList(array $parts): string
    {
        $parts = array_values(array_unique($parts));
        if (count($parts) <= 1) {
            return (string) ($parts[0] ?? '');
        }
        $last = array_pop($parts);
        return implode(', ', $parts) . ' and ' . $last;
    }

    private static function link(string $route, array $params = []): string
    {
        $base = rtrim((string) ($GLOBALS['base'] ?? ''), '/');
        if (PHP_SAPI === 'cli') {
            // The CLI script name would otherwise leak a "bin" prefix into the link.
            $base = rtrim((string) ($GLOBALS['config']['app']['base_url'] ?? ''), '/');
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host !== '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $base = $scheme . '://' . $host . $base;
        }
        return $base . '/index.php?' . http_build_query(['r' => $route] + $params);
    }

    private static function dateLabel(string $ymd): string
    {
        $ts = strtotime($ymd);
        return $ts !== false ? date('D j M Y', $ts) : $ymd;
    }

    private static function hoursLabel($hours): string
    {
        if ($hours === null || $hours === '') {
            return 'hours not recorded';
        }
        return fmt_hours($hours) . ' h';
    }

    private static function place($district): string
    {
        $district = trim((string) ($district ?? ''));
        return $district !== '' ? ', ' . $district : '';
    }
}
