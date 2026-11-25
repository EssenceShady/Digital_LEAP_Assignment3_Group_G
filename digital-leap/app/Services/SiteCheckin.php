<?php
declare(strict_types=1);

namespace Leap\Services;

use Leap\Core\Database;

/**
 * GPS-verified staff site visits. Like student attendance, a visit is an
 * append-only audit event on the placement, so no new table is needed. The
 * distance is measured against the organisation pin the coordinator records.
 *
 * A visit is proof of presence at one moment: staff press the button while
 * on site. Nothing tracks them continuously.
 */
final class SiteCheckin
{
    public const ACTION = 'sitevisit.checkin';
    public const MAX_DISTANCE_M = 500;

    /**
     * @param array<string, mixed> $placement placement row with placement_id and org_id
     * @return array{ok: bool, message: string, event?: array<string, mixed>}
     */
    public static function record(int $userId, string $role, array $placement, ?int $visitId, ?float $lat, ?float $lng, ?float $accuracy, string $purpose = ''): array
    {
        if ($lat === null || $lng === null) {
            return ['ok' => false, 'message' => 'Enable location sharing to record a site visit. A visit cannot be verified without it.'];
        }
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return ['ok' => false, 'message' => 'The location received is not on the map.'];
        }
        if ($accuracy !== null && ($accuracy < 0 || $accuracy > 10000)) {
            $accuracy = null;
        }
        $purpose = trim($purpose);
        if (mb_strlen($purpose) > 160) {
            $purpose = mb_substr($purpose, 0, 160);
        }

        $placementId = (int) $placement['placement_id'];
        if ($visitId !== null && self::forVisit($placementId, $visitId, $userId) !== null) {
            return ['ok' => false, 'message' => 'You have already checked in for this visit.'];
        }

        $org = Attendance::orgPoint((int) $placement['org_id']);
        $distance = $org !== null ? Attendance::distance($lat, $lng, $org['lat'], $org['lng']) : null;
        if ($distance !== null && $distance > self::MAX_DISTANCE_M) {
            return [
                'ok' => false,
                'message' => 'You are ' . fmt_distance($distance) . ' from the site pin. A site visit can only be recorded within '
                    . self::MAX_DISTANCE_M . ' m. Move closer, or ask the coordinator to correct the pin.',
            ];
        }

        $now = date('Y-m-d H:i:s');
        Database::insert('audit_log', [
            'user_id' => $userId,
            'action' => self::ACTION,
            'entity_type' => 'placement',
            'entity_id' => $placementId,
            'before_json' => null,
            'after_json' => json_encode([
                'type' => 'site_visit',
                'visit_id' => $visitId,
                'role' => $role,
                'purpose' => $purpose,
                'lat' => round($lat, 6),
                'lng' => round($lng, 6),
                'accuracy_m' => $accuracy === null ? null : (int) round($accuracy),
                'distance_m' => $distance,
                'org_lat' => $org['lat'] ?? null,
                'org_lng' => $org['lng'] ?? null,
                'source' => 'device',
            ], JSON_UNESCAPED_UNICODE),
            'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'created_at' => $now,
        ]);

        $message = $distance === null
            ? 'Site visit recorded. No site pin is set for this organisation, so the distance could not be checked.'
            : 'Site visit recorded ' . fmt_distance($distance) . ' from the site pin.';

        return ['ok' => true, 'message' => $message, 'event' => [
            'at' => $now,
            'distance_m' => $distance,
            'purpose' => $purpose,
        ]];
    }

    /** @return array<int, array<string, mixed>> */
    public static function recent(int $placementId, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $rows = Database::all(
            'SELECT a.audit_id, a.user_id, a.entity_id, a.after_json, a.created_at,
                    u.first_name, u.last_name
             FROM audit_log a LEFT JOIN users u ON u.user_id = a.user_id
             WHERE a.action = ? AND a.entity_type = ? AND a.entity_id = ?
             ORDER BY a.audit_id DESC LIMIT ' . $limit,
            [self::ACTION, 'placement', $placementId]
        );

        $out = [];
        foreach ($rows as $row) {
            $out[] = self::decorate($row);
        }
        return $out;
    }

    /**
     * Latest check-in per visit for the placements on screen.
     *
     * @param array<int, int> $placementIds
     * @return array<int, array<string, mixed>> keyed by visit_id
     */
    public static function proofsFor(array $placementIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $placementIds))));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $rows = Database::all(
            'SELECT a.audit_id, a.user_id, a.entity_id, a.after_json, a.created_at,
                    u.first_name, u.last_name
             FROM audit_log a LEFT JOIN users u ON u.user_id = a.user_id
             WHERE a.action = ? AND a.entity_type = ? AND a.entity_id IN (' . $placeholders . ')
             ORDER BY a.audit_id DESC',
            array_merge([self::ACTION, 'placement'], $ids)
        );

        $out = [];
        foreach ($rows as $row) {
            $event = self::decorate($row);
            if ($event['visit_id'] !== null && !isset($out[$event['visit_id']])) {
                $out[$event['visit_id']] = $event;
            }
        }
        return $out;
    }

    /** @return array<string, mixed>|null */
    public static function forVisit(int $placementId, int $visitId, int $userId): ?array
    {
        foreach (self::recent($placementId, 50) as $event) {
            if ($event['visit_id'] === $visitId && $event['user_id'] === $userId) {
                return $event;
            }
        }
        return null;
    }

    /** @return array<int, array<string, mixed>> */
    public static function recentCoordinator(int $limit = 12): array
    {
        $limit = max(1, min(50, $limit));
        $rows = Database::all(
            'SELECT a.audit_id, a.user_id, a.entity_id, a.after_json, a.created_at,
                    u.first_name, u.last_name, s.reg_no, o.name AS org_name
             FROM audit_log a
             JOIN users u ON u.user_id = a.user_id
             JOIN placement p ON p.placement_id = a.entity_id
             JOIN student s ON s.student_id = p.student_id
             JOIN organisation o ON o.org_id = p.org_id
             WHERE a.action = ? AND a.entity_type = ?
             ORDER BY a.audit_id DESC LIMIT 200',
            [self::ACTION, 'placement']
        );

        $out = [];
        foreach ($rows as $row) {
            $event = self::decorate($row);
            if ($event['role'] !== 'coordinator') {
                continue;
            }
            $out[] = $event + [
                'reg_no' => (string) $row['reg_no'],
                'org_name' => (string) $row['org_name'],
            ];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /** @param array<string, mixed> $row */
    private static function decorate(array $row): array
    {
        $data = json_decode((string) ($row['after_json'] ?? ''), true);
        if (!is_array($data)) {
            $data = [];
        }
        return [
            'checkin_id' => (int) $row['audit_id'],
            'placement_id' => (int) ($row['entity_id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'name' => trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')),
            'role' => (string) ($data['role'] ?? ''),
            'visit_id' => isset($data['visit_id']) ? (int) $data['visit_id'] : null,
            'purpose' => (string) ($data['purpose'] ?? ''),
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
            'accuracy_m' => isset($data['accuracy_m']) ? (int) $data['accuracy_m'] : null,
            'distance_m' => isset($data['distance_m']) ? (int) $data['distance_m'] : null,
            'source' => (string) ($data['source'] ?? 'device'),
            'at' => (string) $row['created_at'],
        ];
    }
}
