<?php
declare(strict_types=1);

namespace Leap\Core;

/**
 * Placement messaging.
 *
 * A conversation always belongs to a placement, so access is decided by the
 * academic relationships already stored on that placement rather than by a
 * free-form contact list. Only students, mentors and coordinators can message.
 */
final class Messenger
{
    public static function conversation(int $conversationId): ?array
    {
        return Database::one(
            'SELECT c.*, p.mentor_id, p.coordinator_id, p.status AS placement_status,
                    p.end_date, s.user_id AS student_user, s.student_id, s.reg_no,
                    su.first_name AS student_first, su.last_name AS student_last,
                    o.name AS org_name
             FROM conversation c
             JOIN placement p ON p.placement_id = c.placement_id
             JOIN student s ON s.student_id = p.student_id
             JOIN users su ON su.user_id = s.user_id
             JOIN organisation o ON o.org_id = p.org_id
             WHERE c.conversation_id = ?',
            [$conversationId]
        );
    }

    public static function isMember(int $conversationId, int $userId): bool
    {
        return (int) Database::val(
            'SELECT COUNT(*) FROM conversation_member WHERE conversation_id = ? AND user_id = ?',
            [$conversationId, $userId]
        ) > 0;
    }

    /**
     * Students and mentors reach only the placements they belong to. A
     * coordinator may open a thread on a placement they coordinate; that
     * joins them as a visible participant rather than reading silently.
     */
    public static function mayAccess(array $conversation, int $userId, string $role): bool
    {
        if (self::isMember((int) $conversation['conversation_id'], $userId)) {
            return true;
        }
        if ($role === 'coordinator' && (int) $conversation['coordinator_id'] === $userId) {
            return true;
        }
        return false;
    }

    public static function joinAsObserver(array $conversation, int $userId): void
    {
        if (self::isMember((int) $conversation['conversation_id'], $userId)) {
            return;
        }
        Database::insert('conversation_member', [
            'conversation_id' => (int) $conversation['conversation_id'],
            'user_id' => $userId,
            'member_role' => 'member',
            'last_read_message_id' => (int) Database::val(
                'SELECT COALESCE(MAX(message_id),0) FROM message WHERE conversation_id = ?',
                [(int) $conversation['conversation_id']]
            ),
            'joined_at' => date('Y-m-d H:i:s'),
        ]);
        self::post((int) $conversation['conversation_id'], null, 'system', 'The coordinator joined this conversation.', null, null);
        Audit::log('message.observer_joined', 'conversation', (int) $conversation['conversation_id']);
    }

    public static function members(int $conversationId): array
    {
        return Database::all(
            'SELECT u.user_id, u.first_name, u.last_name, r.role_key, r.role_name, cm.member_role
             FROM conversation_member cm
             JOIN users u ON u.user_id = cm.user_id
             JOIN roles r ON r.role_id = u.role_id
             WHERE cm.conversation_id = ?
             ORDER BY (cm.member_role = \'owner\') DESC, u.first_name',
            [$conversationId]
        );
    }

    public static function unreadTotal(int $userId): int
    {
        return (int) Database::val(
            "SELECT COUNT(*) FROM message m
             JOIN conversation_member cm ON cm.conversation_id = m.conversation_id AND cm.user_id = ?
             WHERE m.message_id > cm.last_read_message_id
               AND m.sender_id <> ?
               AND m.deleted_at IS NULL
               AND m.kind = 'text'",
            [$userId, $userId]
        );
    }

    public static function unreadFor(int $conversationId, int $userId): int
    {
        return (int) Database::val(
            "SELECT COUNT(*) FROM message m
             JOIN conversation_member cm ON cm.conversation_id = m.conversation_id AND cm.user_id = ?
             WHERE m.conversation_id = ?
               AND m.message_id > cm.last_read_message_id
               AND m.sender_id <> ?
               AND m.deleted_at IS NULL
               AND m.kind = 'text'",
            [$userId, $conversationId, $userId]
        );
    }

    public static function markRead(int $conversationId, int $userId): void
    {
        $latest = (int) Database::val(
            'SELECT COALESCE(MAX(message_id),0) FROM message WHERE conversation_id = ?',
            [$conversationId]
        );
        Database::q(
            'UPDATE conversation_member SET last_read_message_id = ?
             WHERE conversation_id = ? AND user_id = ? AND last_read_message_id < ?',
            [$latest, $conversationId, $userId, $latest]
        );
    }

    public static function post(
        int $conversationId,
        ?int $senderId,
        string $kind,
        ?string $body,
        ?int $parentId,
        ?int $documentId,
        ?string $clientToken = null
    ): int {
        $id = Database::insert('message', [
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'parent_message_id' => $parentId,
            'document_id' => $documentId,
            'kind' => $kind,
            'body' => $body,
            'client_token' => $clientToken,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        self::bump($conversationId);
        return $id;
    }

    public static function bump(int $conversationId): void
    {
        Database::update('conversation', [
            'last_message_at' => date('Y-m-d H:i:s'),
        ], 'conversation_id = ?', [$conversationId]);
    }

    /**
     * Append a system line to every thread on a placement. Called from the
     * events that change a record, so the thread reads as a placement log.
     */
    public static function systemForPlacement(int $placementId, string $body): void
    {
        $ids = Database::all(
            'SELECT conversation_id FROM conversation WHERE placement_id = ?',
            [$placementId]
        );
        foreach ($ids as $row) {
            self::post((int) $row['conversation_id'], null, 'system', $body, null, null);
        }
    }

    /**
     * People this user may start a conversation with, grouped by placement.
     *
     * @return list<array{placement_id:int, org_name:string, student:string, peers:list<array>}>
     */
    public static function peerGroups(int $userId, string $role): array
    {
        $where = match ($role) {
            'student' => 's.user_id = ?',
            'mentor' => "p.mentor_id = ? AND p.status IN ('approved','active','completed')",
            'coordinator' => 'p.coordinator_id = ?',
            default => '1 = 0',
        };

        $placements = Database::all(
            'SELECT p.placement_id, p.mentor_id, p.coordinator_id, s.user_id AS student_user,
                    su.first_name AS student_first, su.last_name AS student_last,
                    mu.first_name AS mentor_first, mu.last_name AS mentor_last,
                    cu.first_name AS co_first, cu.last_name AS co_last,
                    o.name AS org_name
             FROM placement p
             JOIN student s ON s.student_id = p.student_id
             JOIN users su ON su.user_id = s.user_id
             JOIN users mu ON mu.user_id = p.mentor_id
             JOIN users cu ON cu.user_id = p.coordinator_id
             JOIN organisation o ON o.org_id = p.org_id
             WHERE ' . $where . '
             ORDER BY o.name',
            [$userId]
        );

        $groups = [];
        foreach ($placements as $p) {
            $peers = [];
            if ($role === 'student') {
                $peers[] = ['user_id' => (int) $p['mentor_id'], 'name' => trim($p['mentor_first'] . ' ' . $p['mentor_last']), 'role' => 'Academic mentor'];
                $peers[] = ['user_id' => (int) $p['coordinator_id'], 'name' => trim($p['co_first'] . ' ' . $p['co_last']), 'role' => 'Internship coordinator'];
            } elseif ($role === 'mentor') {
                $peers[] = ['user_id' => (int) $p['student_user'], 'name' => trim($p['student_first'] . ' ' . $p['student_last']), 'role' => 'Student'];
                $peers[] = ['user_id' => (int) $p['coordinator_id'], 'name' => trim($p['co_first'] . ' ' . $p['co_last']), 'role' => 'Internship coordinator'];
            } else {
                $peers[] = ['user_id' => (int) $p['student_user'], 'name' => trim($p['student_first'] . ' ' . $p['student_last']), 'role' => 'Student'];
                $peers[] = ['user_id' => (int) $p['mentor_id'], 'name' => trim($p['mentor_first'] . ' ' . $p['mentor_last']), 'role' => 'Academic mentor'];
            }
            $peers = array_values(array_filter($peers, static fn (array $peer): bool => $peer['user_id'] !== $userId));

            $groups[] = [
                'placement_id' => (int) $p['placement_id'],
                'org_name' => (string) $p['org_name'],
                'student' => trim($p['student_first'] . ' ' . $p['student_last']),
                'peers' => $peers,
            ];
        }
        return $groups;
    }

    public static function allowedPeer(int $placementId, int $userId, int $peerId): bool
    {
        foreach (self::peerGroups($userId, Auth::role()) as $group) {
            if ($group['placement_id'] !== $placementId) {
                continue;
            }
            foreach ($group['peers'] as $peer) {
                if ($peer['user_id'] === $peerId) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function findThread(int $placementId, array $userIds): ?int
    {
        sort($userIds);
        $rows = Database::all(
            'SELECT c.conversation_id,
                    GROUP_CONCAT(cm.user_id ORDER BY cm.user_id) AS member_ids
             FROM conversation c
             JOIN conversation_member cm ON cm.conversation_id = c.conversation_id
             WHERE c.placement_id = ?
             GROUP BY c.conversation_id',
            [$placementId]
        );
        foreach ($rows as $row) {
            $ids = array_map('intval', explode(',', (string) $row['member_ids']));
            if ($ids === $userIds) {
                return (int) $row['conversation_id'];
            }
        }
        return null;
    }

    public static function startThread(
        int $placementId,
        int $creatorId,
        array $userIds,
        string $subject,
        string $topic
    ): int {
        return Database::tx(static function () use ($placementId, $creatorId, $userIds, $subject, $topic): int {
            $id = Database::insert('conversation', [
                'placement_id' => $placementId,
                'subject' => substr($subject, 0, 120),
                'topic' => $topic,
                'created_by' => $creatorId,
                'last_message_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            foreach (array_unique(array_merge([$creatorId], $userIds)) as $memberId) {
                Database::insert('conversation_member', [
                    'conversation_id' => $id,
                    'user_id' => $memberId,
                    'member_role' => $memberId === $creatorId ? 'owner' : 'member',
                    'last_read_message_id' => 0,
                    'joined_at' => date('Y-m-d H:i:s'),
                ]);
            }

            self::post(
                $id,
                null,
                'system',
                'Conversation opened. Career Fair 2026 report deadline: Wednesday 11 November 2026, 12:00.',
                null,
                null
            );
            Audit::log('message.thread_started', 'conversation', $id, null, [
                'placement_id' => $placementId,
                'members' => array_values(array_unique(array_merge([$creatorId], $userIds))),
            ]);
            return $id;
        });
    }
}
