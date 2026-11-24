<?php
declare(strict_types=1);

namespace Leap\Core;

final class Notify
{
    /**
     * $email = false keeps fast-moving items such as chat messages out of the
     * mail queue; the in-app notification and its unread badge still appear.
     * A member who muted that notification family in Settings is treated the
     * same way: the bell keeps the record, the mail queue does not.
     * $emailBody lets a full digest travel by mail while the bell keeps a
     * one-line summary.
     */
    public static function push(int $userId, string $type, string $title, string $body, string $link, bool $email = true, ?string $emailBody = null): int
    {
        $id = Database::insert('notification', [
            'user_id' => $userId,
            'type' => $type,
            'title' => substr($title, 0, 120),
            'body' => substr($body, 0, 500),
            'link_url' => substr($link, 0, 200),
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $wantsMail = $email && !Preference::emailMuted($userId, $type);
        $address = $wantsMail ? Database::val('SELECT email FROM users WHERE user_id = ?', [$userId]) : null;
        if ($address !== null) {
            Database::insert('email_queue', [
                'user_id' => $userId,
                'notification_id' => $id,
                'to_email' => (string) $address,
                'subject' => substr($title, 0, 180),
                'body' => $emailBody ?? $body,
                'status' => 'queued',
                'attempts' => 0,
                'scheduled_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $id;
    }

    public static function toRole(string $roleKey, string $type, string $title, string $body, string $link): void
    {
        $rows = Database::all(
            'SELECT u.user_id FROM users u JOIN roles r ON r.role_id = u.role_id
             WHERE r.role_key = ? AND u.status = ?',
            [$roleKey, 'active']
        );
        foreach ($rows as $row) {
            self::push((int) $row['user_id'], $type, $title, $body, $link);
        }
    }
}
