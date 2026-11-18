<?php
declare(strict_types=1);

namespace Leap\Core;

use Throwable;

final class Audit
{
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null
    ): void {
        try {
            Database::insert('audit_log', [
                'user_id' => Auth::id(),
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'before_json' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
                'after_json' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE),
                'ip_address' => self::ip(),
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (Throwable $e) {
            error_log('Audit write failed: ' . $e->getMessage());
        }
    }

    private static function ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return substr((string) $ip, 0, 45);
    }
}
