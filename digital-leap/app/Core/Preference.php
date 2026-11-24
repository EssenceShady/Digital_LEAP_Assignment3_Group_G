<?php
declare(strict_types=1);

namespace Leap\Core;

/**
 * Per-account settings held in user_preference. Values are strings so one
 * table serves checkboxes, radio groups and future keys without a migration
 * for each new preference.
 */
final class Preference
{
    public const TEXT_SIZES = ['normal', 'large'];

    /** Notification families a member may mute for email; the in-app bell always records. */
    public const FAMILIES = [
        'logbook' => 'Log book weeks and comments',
        'placement' => 'Placements, applications and site visits',
        'message' => 'Placement messages',
        'mark' => 'Marks and published results',
    ];

    /** @var array<int, array<string, string>> */
    private static array $cache = [];

    /** @return array<string, string> */
    public static function forUser(int $userId): array
    {
        if (!isset(self::$cache[$userId])) {
            $rows = Database::all('SELECT pref_key, pref_value FROM user_preference WHERE user_id = ?', [$userId]);
            $map = [];
            foreach ($rows as $row) {
                $map[(string) $row['pref_key']] = (string) $row['pref_value'];
            }
            self::$cache[$userId] = $map;
        }
        return self::$cache[$userId];
    }

    public static function get(int $userId, string $key, string $default = ''): string
    {
        return self::forUser($userId)[$key] ?? $default;
    }

    public static function set(int $userId, string $key, string $value): void
    {
        Database::q(
            'INSERT INTO user_preference (user_id, pref_key, pref_value, updated_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value), updated_at = VALUES(updated_at)',
            [$userId, $key, $value, date('Y-m-d H:i:s')]
        );
        unset(self::$cache[$userId]);
    }

    /** The notification type "logbook.returned" belongs to the logbook family. */
    public static function family(string $type): string
    {
        $prefix = explode('.', $type)[0];
        return isset(self::FAMILIES[$prefix]) ? $prefix : 'placement';
    }

    public static function emailMuted(int $userId, string $type): bool
    {
        return self::get($userId, 'notify.email.' . self::family($type), 'on') === 'off';
    }

    public static function textSize(int $userId): string
    {
        $size = self::get($userId, 'ui.text_size', 'normal');
        return in_array($size, self::TEXT_SIZES, true) ? $size : 'normal';
    }

    public static function reduceMotion(int $userId): bool
    {
        return self::get($userId, 'ui.reduce_motion', 'off') === 'on';
    }
}
