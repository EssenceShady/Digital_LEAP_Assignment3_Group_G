<?php
declare(strict_types=1);

namespace Leap\Core;

final class Mailer
{
    /**
     * @return array{0: bool, 1: ?string} delivered flag and error message
     */
    public static function send(string $to, string $subject, string $body): array
    {
        $mode = (string) Settings::get('mail_mode', $GLOBALS['config']['mail']['mode'] ?? 'log');

        if ($mode === 'log') {
            $line = sprintf(
                "[%s] TO: %s | SUBJECT: %s | %s\n",
                date('c'),
                $to,
                $subject,
                str_replace(["\r", "\n"], ' ', $body)
            );
            $ok = @file_put_contents(STORAGE_PATH . '/logs/mail.log', $line, FILE_APPEND | LOCK_EX);
            return $ok === false
                ? [false, 'Could not write to the mail log.']
                : [true, null];
        }

        return [false, 'SMTP delivery is not configured on this installation.'];
    }
}
