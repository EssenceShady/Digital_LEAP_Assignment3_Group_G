<?php
declare(strict_types=1);

/**
 * Deliver queued mail. Run from a terminal or cron:
 *   php bin/send_mail_queue.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use Leap\Core\Database;
use Leap\Core\Mailer;

$queue = Database::all(
    'SELECT * FROM email_queue WHERE status = ? AND scheduled_at <= ? ORDER BY queue_id LIMIT 100',
    ['queued', date('Y-m-d H:i:s')]
);

$sent = 0;
$failed = 0;
foreach ($queue as $mail) {
    [$ok, $error] = Mailer::send((string) $mail['to_email'], (string) $mail['subject'], (string) $mail['body']);
    Database::update('email_queue', [
        'attempts' => (int) $mail['attempts'] + 1,
        'status' => $ok ? 'sent' : 'failed',
        'last_error' => $ok ? null : (string) $error,
        'sent_at' => $ok ? date('Y-m-d H:i:s') : null,
    ], 'queue_id = ?', [(int) $mail['queue_id']]);
    if ($ok) {
        $sent++;
    } else {
        $failed++;
    }
}

printf("Queued: %d | Sent: %d | Failed: %d\n", count($queue), $sent, $failed);
exit($failed > 0 ? 1 : 0);
