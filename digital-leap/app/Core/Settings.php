<?php
declare(strict_types=1);

namespace Leap\Core;

final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Database::all('SELECT * FROM setting ORDER BY setting_key') as $row) {
                self::$cache[$row['setting_key']] = $row;
            }
        }
        return self::$cache;
    }

    public static function get(string $key, $default = null)
    {
        $row = self::all()[$key] ?? null;
        if ($row === null) {
            return $default;
        }
        return self::cast($row['setting_value'], $row['data_type']);
    }

    public static function set(string $key, string $value, ?int $userId = null): void
    {
        $exists = Database::val('SELECT COUNT(*) FROM setting WHERE setting_key = ?', [$key]);
        if ((int) $exists > 0) {
            Database::update('setting', [
                'setting_value' => $value,
                'updated_by' => $userId,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'setting_key = ?', [$key]);
        } else {
            Database::insert('setting', [
                'setting_key' => $key,
                'setting_value' => $value,
                'data_type' => 'string',
                'updated_by' => $userId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        self::$cache = null;
    }

    private static function cast(string $value, string $type)
    {
        return match ($type) {
            'int' => (int) $value,
            'decimal' => (float) $value,
            'bool' => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
