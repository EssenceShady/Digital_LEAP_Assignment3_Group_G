<?php
declare(strict_types=1);

namespace Leap\Services;

use Leap\Core\Auth;
use Leap\Core\Database;
use Leap\Core\Settings;

/**
 * Student attendance check-ins. A check-in is an append-only audit event on
 * the placement, so the record is immutable and needs no new table; the
 * organisation's pin lives in the setting table under geo.org.<id>.
 *
 * Location is captured only when the student presses check in or out.
 * Tracking happens for students at their own placement; supervisors,
 * mentors and staff are not tracked.
 */
final class Attendance
{
    public const EARTH_RADIUS_M = 6371000;

    /**
     * @param array<string, mixed> $placement placement row with placement_id and org_id
     * @return array{ok: bool, message: string, event?: array<string, mixed>}
     */
    public static function record(int $userId, array $placement, string $type, ?float $lat, ?float $lng, ?float $accuracy): array
    {
        if (!in_array($type, ['check_in', 'check_out'], true)) {
            return ['ok' => false, 'message' => 'Unknown check-in type.'];
        }
        if (($lat === null) !== ($lng === null)) {
            return ['ok' => false, 'message' => 'Send both latitude and longitude, or neither.'];
        }
        if ($lat !== null && ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180)) {
            return ['ok' => false, 'message' => 'The location received is not on the map.'];
        }
        if ($accuracy !== null && ($accuracy < 0 || $accuracy > 10000)) {
            $accuracy = null;
        }

        $placementId = (int) $placement['placement_id'];
        $last = self::today($placementId, $userId);
        if ($type === 'check_in' && $last !== null && $last['type'] === 'check_in') {
            return ['ok' => false, 'message' => 'You checked in at ' . $last['time'] . '. Check out first.'];
        }
        if ($type === 'check_out' && ($last === null || $last['type'] === 'check_out')) {
            return ['ok' => false, 'message' => 'There is no open check-in for today.'];
        }

        $org = self::orgPoint((int) $placement['org_id']);
        $distance = ($lat !== null && $lng !== null && $org !== null)
            ? self::distance($lat, $lng, $org['lat'], $org['lng'])
            : null;
        $source = ($lat === null) ? 'attested' : 'device';

        $now = date('Y-m-d H:i:s');
        $payload = [
            'type' => $type,
            'lat' => $lat === null ? null : round($lat, 6),
            'lng' => $lng === null ? null : round($lng, 6),
            'accuracy_m' => $accuracy === null ? null : (int) round($accuracy),
            'distance_m' => $distance,
            'org_lat' => $org['lat'] ?? null,
            'org_lng' => $org['lng'] ?? null,
            'source' => $source,
        ];
        Database::insert('audit_log', [
            'user_id' => $userId,
            'action' => 'attendance.' . $type,
            'entity_type' => 'placement',
            'entity_id' => $placementId,
            'before_json' => null,
            'after_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);

        $time = date('H:i');
        $message = ($type === 'check_in' ? 'Checked in' : 'Checked out') . ' at ' . $time . '.';
        if ($source === 'attested') {
            $message .= ' Your location was not shared, so the supervisor sees this as an attested check-in.';
        } elseif ($distance === null) {
            $message .= ' Location recorded; no site pin is set for this organisation.';
        } elseif ($distance > 500) {
            $message .= ' You are ' . fmt_distance($distance) . ' from the site pin — your supervisor may ask about this.';
        }

        return ['ok' => true, 'message' => $message, 'event' => [
            'type' => $type,
            'time' => $time,
            'distance_m' => $distance,
            'source' => $source,
            'at' => $now,
        ]];
    }

    /** @return array{type: string, time: string, at: string, distance_m: ?int, source: string}|null */
    public static function today(int $placementId, int $userId): ?array
    {
        $row = Database::one(
            "SELECT action, after_json, created_at FROM audit_log
             WHERE user_id = ? AND entity_type = ? AND entity_id = ? AND action LIKE 'attendance.%' AND created_at >= ?
             ORDER BY audit_id DESC LIMIT 1",
            [$userId, 'placement', $placementId, date('Y-m-d 00:00:00')]
        );
        return $row === null ? null : self::decorate($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function recent(int $placementId, int $limit = 10, ?int $userId = null): array
    {
        $limit = max(1, min(50, $limit));
        $sql = "SELECT a.action, a.after_json, a.created_at, a.user_id, u.first_name, u.last_name
                FROM audit_log a LEFT JOIN users u ON u.user_id = a.user_id
                WHERE a.entity_type = ? AND a.entity_id = ? AND a.action LIKE 'attendance.%'";
        $params = ['placement', $placementId];
        if ($userId !== null) {
            $sql .= ' AND a.user_id = ?';
            $params[] = $userId;
        }
        $sql .= ' ORDER BY a.audit_id DESC LIMIT ' . $limit;

        $rows = Database::all($sql, $params);
        $out = [];
        foreach ($rows as $row) {
            $out[] = self::decorate($row) + [
                'user_id' => (int) $row['user_id'],
                'name' => trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')),
            ];
        }
        return $out;
    }

    /** @return array{lat: float, lng: float}|null */
    public static function orgPoint(int $orgId): ?array
    {
        $raw = (string) Database::val('SELECT setting_value FROM setting WHERE setting_key = ?', ['geo.org.' . $orgId]);
        if ($raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['lat'], $data['lng'])) {
            return null;
        }
        return ['lat' => (float) $data['lat'], 'lng' => (float) $data['lng']];
    }

    public static function setOrgPoint(int $orgId, ?float $lat, ?float $lng): void
    {
        $value = ($lat === null || $lng === null)
            ? ''
            : json_encode(['lat' => round($lat, 6), 'lng' => round($lng, 6)], JSON_UNESCAPED_UNICODE);
        Settings::set('geo.org.' . $orgId, $value, (int) Auth::id() ?: null);
        \Leap\Core\Audit::log('organisation.geo_updated', 'organisation', $orgId, null, [
            'lat' => $lat,
            'lng' => $lng,
        ]);
    }

    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return (int) round(self::EARTH_RADIUS_M * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /** @param array<string, mixed> $row */
    private static function decorate(array $row): array
    {
        $data = json_decode((string) ($row['after_json'] ?? ''), true);
        if (!is_array($data)) {
            $data = [];
        }
        $type = (string) ($data['type'] ?? str_replace('attendance.', '', (string) $row['action']));
        return [
            'type' => $type === 'check_out' ? 'check_out' : 'check_in',
            'time' => date('H:i', strtotime((string) $row['created_at'])),
            'at' => (string) $row['created_at'],
            'distance_m' => isset($data['distance_m']) ? (int) $data['distance_m'] : null,
            'source' => (string) ($data['source'] ?? 'attested'),
            'accuracy_m' => isset($data['accuracy_m']) ? (int) $data['accuracy_m'] : null,
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
        ];
    }
}
