<?php
declare(strict_types=1);

/**
 * Queue weekly logbook reminders for active placements. Run daily:
 *   php bin/remind_logbooks.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use Leap\Core\Database;
use Leap\Core\Notify;
use Leap\Core\Settings;

$dueDay = (string) Settings::get('logbook_due_day', 'Friday');
$leadDays = (int) Settings::get('reminder_lead_days', 2);
$dayIndex = ['Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6][$dueDay] ?? 5;
$today = new DateTimeImmutable('today');

$placements = Database::all(
    'SELECT p.placement_id, p.start_date, p.total_weeks, s.user_id AS student_user, o.name AS org_name
     FROM placement p
     JOIN student s ON s.student_id = p.student_id
     JOIN organisation o ON o.org_id = p.org_id
     WHERE p.status = ?',
    ['active']
);

$queued = 0;
foreach ($placements as $placement) {
    $start = new DateTimeImmutable((string) $placement['start_date']);
    if ($today < $start) {
        continue;
    }
    $week = (int) floor(((int) $start->diff($today)->format('%a')) / 7) + 1;
    $totalWeeks = (int) $placement['total_weeks'];
    if ($week > $totalWeeks) {
        continue;
    }

    $weekStart = $start->modify('+' . (($week - 1) * 7) . ' days');
    $due = $weekStart;
    while ((int) $due->format('w') !== $dayIndex) {
        $due = $due->modify('+1 day');
    }
    if ($today < $due->modify('-' . $leadDays . ' days') || $today > $due) {
        continue;
    }

    $submitted = (int) Database::val(
        'SELECT COUNT(*) FROM logbook_entry WHERE placement_id = ? AND week_no = ? AND status <> ?',
        [(int) $placement['placement_id'], $week, 'draft']
    );
    if ($submitted > 0) {
        continue;
    }

    $already = (int) Database::val(
        'SELECT COUNT(*) FROM notification WHERE user_id = ? AND type = ? AND title LIKE ? AND created_at >= ?',
        [(int) $placement['student_user'], 'reminder', '%week ' . $week . '%', $today->modify('-6 days')->format('Y-m-d') . ' 00:00:00']
    );
    if ($already > 0) {
        continue;
    }

    Notify::push(
        (int) $placement['student_user'],
        'reminder',
        'Logbook week ' . $week . ' is due',
        'Submit week ' . $week . ' of your logbook for ' . $placement['org_name'] . ' by '
            . $due->format('l j F Y') . '. Draft days are emailed to your supervisor each evening, but the week only enters formal review when you submit it.',
        'index.php?r=student/logbook'
    );
    $queued++;
}

printf("Active placements checked: %d | Reminders queued: %d\n", count($placements), $queued);
