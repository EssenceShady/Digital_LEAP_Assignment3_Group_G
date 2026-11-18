<?php
declare(strict_types=1);

namespace Leap\Controllers;

use Leap\Core\Audit;
use Leap\Core\Auth;
use Leap\Core\Database;
use Leap\Core\Messenger;
use Leap\Core\Notify;
use Leap\Core\Upload;
use Leap\Core\View;
use RuntimeException;
use Throwable;

final class MessagingController
{
    private const REACTIONS = ['👍', '✅', '❓', '👏', '🙂'];
    private const EDIT_WINDOW = 900;

    public function inbox(): void
    {
        $userId = (int) Auth::id();
        $threads = Database::all(
            "SELECT c.*, o.name AS org_name, p.student_id, s.user_id AS student_user,
                    p.mentor_id, p.coordinator_id,
                    (SELECT m.body FROM message m WHERE m.conversation_id = c.conversation_id
                     ORDER BY m.message_id DESC LIMIT 1) AS last_body,
                    (SELECT m.kind FROM message m WHERE m.conversation_id = c.conversation_id
                     ORDER BY m.message_id DESC LIMIT 1) AS last_kind,
                    (SELECT m.created_at FROM message m WHERE m.conversation_id = c.conversation_id
                     ORDER BY m.message_id DESC LIMIT 1) AS last_created,
                    (SELECT u2.first_name FROM message m JOIN users u2 ON u2.user_id = m.sender_id
                     WHERE m.conversation_id = c.conversation_id ORDER BY m.message_id DESC LIMIT 1) AS last_sender
             FROM conversation c
             JOIN conversation_member cm ON cm.conversation_id = c.conversation_id
             JOIN placement p ON p.placement_id = c.placement_id
             JOIN student s ON s.student_id = p.student_id
             JOIN organisation o ON o.org_id = p.org_id
             WHERE cm.user_id = ?
             ORDER BY c.last_message_at DESC, c.conversation_id DESC",
            [$userId]
        );

        foreach ($threads as $i => $thread) {
            $threads[$i]['unread'] = Messenger::unreadFor((int) $thread['conversation_id'], $userId);
            $others = array_filter(
                Messenger::members((int) $thread['conversation_id']),
                static fn (array $m): bool => (int) $m['user_id'] !== $userId
            );
            $threads[$i]['participants'] = implode(', ', array_map(
                static fn (array $m): string => $m['first_name'] . ' ' . $m['last_name'],
                $others
            ));
            $threads[$i]['others'] = array_values($others);
        }

        View::render('messages/inbox', [
            'title' => 'Messages',
            'threads' => $threads,
            'groups' => Messenger::peerGroups($userId, Auth::role()),
            'unread' => Messenger::unreadTotal($userId),
        ]);
    }

    public function thread(): void
    {
        $userId = (int) Auth::id();
        $conversation = $this->conversationOrFail();
        $conversationId = (int) $conversation['conversation_id'];

        Messenger::markRead($conversationId, $userId);

        $rows = Database::all(
            'SELECT m.*, u.first_name, u.last_name, r.role_key,
                    pm.body AS parent_body, pm.deleted_at AS parent_deleted,
                    pu.first_name AS parent_first, pu.last_name AS parent_last,
                    d.original_name, d.file_size, d.doc_id
             FROM message m
             LEFT JOIN users u ON u.user_id = m.sender_id
             LEFT JOIN roles r ON r.role_id = u.role_id
             LEFT JOIN message pm ON pm.message_id = m.parent_message_id
             LEFT JOIN users pu ON pu.user_id = pm.sender_id
             LEFT JOIN document d ON d.doc_id = m.document_id
             WHERE m.conversation_id = ?
             ORDER BY m.message_id ASC
             LIMIT 300',
            [$conversationId]
        );

        $reactions = Database::all(
            'SELECT mr.message_id, mr.emoji, COUNT(*) AS total,
                    SUM(mr.user_id = ?) AS mine
             FROM message_reaction mr
             JOIN message m ON m.message_id = mr.message_id
             WHERE m.conversation_id = ?
             GROUP BY mr.message_id, mr.emoji
             ORDER BY mr.emoji',
            [$userId, $conversationId]
        );
        $reactionMap = [];
        foreach ($reactions as $r) {
            $reactionMap[(int) $r['message_id']][] = [
                'emoji' => (string) $r['emoji'],
                'total' => (int) $r['total'],
                'mine' => (int) $r['mine'] > 0,
            ];
        }

        foreach ($rows as $i => $row) {
            $rows[$i]['reactions'] = $reactionMap[(int) $row['message_id']] ?? [];
            $rows[$i]['can_edit'] = (int) ($row['sender_id'] ?? 0) === $userId
                && $row['kind'] === 'text'
                && $row['deleted_at'] === null
                && (time() - strtotime((string) $row['created_at'])) <= self::EDIT_WINDOW;
        }

        View::render('messages/thread', [
            'title' => (string) $conversation['subject'],
            'conversation' => $conversation,
            'members' => Messenger::members($conversationId),
            'messages' => $rows,
            'reactions' => self::REACTIONS,
            'lastId' => $rows === [] ? 0 : (int) end($rows)['message_id'],
        ]);
    }

    public function poll(): void
    {
        $userId = (int) Auth::id();
        $conversation = $this->conversationOrFail();
        $after = (int) \qget('after', 0);

        $rows = Database::all(
            "SELECT m.message_id, m.kind, m.body, m.created_at, m.deleted_at,
                    u.first_name, u.last_name
             FROM message m
             LEFT JOIN users u ON u.user_id = m.sender_id
             WHERE m.conversation_id = ? AND m.message_id > ?
             ORDER BY m.message_id ASC
             LIMIT 50",
            [(int) $conversation['conversation_id'], $after]
        );

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'conversation_id' => (int) $conversation['conversation_id'],
            'messages' => array_map(static function (array $row): array {
                return [
                    'id' => (int) $row['message_id'],
                    'kind' => (string) $row['kind'],
                    'sender' => trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')),
                    'body' => $row['deleted_at'] !== null
                        ? 'This message was deleted'
                        : mb_strimwidth((string) $row['body'], 0, 200, '…'),
                    'created_at' => (string) $row['created_at'],
                ];
            }, $rows),
            'unread' => Messenger::unreadFor((int) $conversation['conversation_id'], $userId),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function start(): void
    {
        $userId = (int) Auth::id();
        $placementId = (int) \post('placement_id', 0);
        $peerId = (int) \post('peer_user_id', 0);
        $topic = (string) \post('topic', 'general');
        $topics = ['general', 'logbook', 'report', 'visit', 'career_fair'];

        if (!in_array($topic, $topics, true)) {
            $topic = 'general';
        }
        if (!Messenger::allowedPeer($placementId, $userId, $peerId)) {
            Audit::log('message.denied', 'conversation', null, null, ['placement_id' => $placementId, 'peer' => $peerId]);
            http_response_code(403);
            View::error(403);
            return;
        }

        $existing = Messenger::findThread($placementId, [$userId, $peerId]);
        if ($existing !== null) {
            \flash('info', 'You already have a conversation about this placement.');
            redirect('messages/thread', ['id' => $existing]);
        }

        $subjects = [
            'general' => 'Placement supervision',
            'logbook' => 'Logbook question',
            'report' => 'Internship report question',
            'visit' => 'Site visit',
            'career_fair' => 'Career Fair 2026',
        ];

        $id = Messenger::startThread($placementId, $userId, [$peerId], $subjects[$topic], $topic);
        \flash('success', 'Conversation opened.');
        redirect('messages/thread', ['id' => $id]);
    }

    public function send(): void
    {
        $userId = (int) Auth::id();
        $conversation = $this->conversationOrFail();
        $conversationId = (int) $conversation['conversation_id'];

        $body = trim((string) \post('body', ''));
        $parentId = (int) \post('parent_id', 0);
        $token = trim((string) \post('client_token', ''));
        if ($token === '' || !preg_match('/^[a-f0-9-]{16,36}$/i', $token)) {
            $token = bin2hex(random_bytes(16));
        }

        if (mb_strlen($body) > 4000) {
            \flash('error', 'Messages are limited to 4,000 characters.');
            redirect('messages/thread', ['id' => $conversationId]);
        }

        $existing = Database::one(
            'SELECT message_id FROM message WHERE conversation_id = ? AND client_token = ?',
            [$conversationId, $token]
        );
        if ($existing !== null) {
            redirect('messages/thread', ['id' => $conversationId], 'm-' . (int) $existing['message_id']);
        }

        if ($parentId > 0) {
            $parent = Database::one(
                'SELECT message_id FROM message WHERE message_id = ? AND conversation_id = ?',
                [$parentId, $conversationId]
            );
            if ($parent === null) {
                $parentId = 0;
            }
        }
        $parentId = $parentId > 0 ? $parentId : null;

        $hasFile = isset($_FILES['attachment']) && (int) ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($body === '' && !$hasFile) {
            \flash('error', 'Write a message or attach a file before sending.');
            redirect('messages/thread', ['id' => $conversationId]);
        }

        try {
            $messageId = Database::tx(function () use ($conversation, $conversationId, $userId, $body, $parentId, $token, $hasFile): int {
                $docId = null;
                if ($hasFile) {
                    $docId = Upload::store(
                        $_FILES['attachment'] ?? [],
                        'message_attachment',
                        ['placement_id' => (int) $conversation['placement_id']]
                    );
                }
                $kind = $body === '' ? 'document' : 'text';
                if ($hasFile && $body !== '') {
                    $kind = 'document';
                }
                $id = Messenger::post($conversationId, $userId, $kind, $body === '' ? null : $body, $parentId, $docId, $token);
                Audit::log('message.sent', 'message', $id, null, [
                    'conversation_id' => $conversationId,
                    'has_attachment' => $hasFile,
                    'reply_to' => $parentId,
                ]);
                return $id;
            });
        } catch (RuntimeException $e) {
            \flash('error', $e->getMessage());
            redirect('messages/thread', ['id' => $conversationId]);
        } catch (Throwable $e) {
            error_log('Message send failed: ' . $e->getMessage());
            \flash('error', 'The message could not be sent. Please try again.');
            redirect('messages/thread', ['id' => $conversationId]);
        }

        $sender = Auth::name();
        foreach (Messenger::members($conversationId) as $member) {
            if ((int) $member['user_id'] === $userId) {
                continue;
            }
            Notify::push(
                (int) $member['user_id'],
                'message',
                'New message from ' . $sender,
                mb_strimwidth($body !== '' ? $body : 'Sent a file in a placement conversation.', 0, 160, '…'),
                'index.php?r=messages/thread&id=' . $conversationId,
                false
            );
        }

        redirect('messages/thread', ['id' => $conversationId], 'm-' . $messageId);
    }

    public function edit(): void
    {
        $userId = (int) Auth::id();
        $conversation = $this->conversationOrFail();
        $messageId = (int) \post('message_id', 0);
        $body = trim((string) \post('body', ''));

        $message = Database::one(
            'SELECT * FROM message WHERE message_id = ? AND conversation_id = ?',
            [$messageId, (int) $conversation['conversation_id']]
        );

        if ($message === null || (int) $message['sender_id'] !== $userId || $message['kind'] !== 'text') {
            \flash('error', 'You can only edit your own text messages.');
            redirect('messages/thread', ['id' => (int) $conversation['conversation_id']]);
        }
        if ($message['deleted_at'] !== null) {
            \flash('error', 'A deleted message cannot be edited.');
            redirect('messages/thread', ['id' => (int) $conversation['conversation_id']]);
        }
        if ((time() - strtotime((string) $message['created_at'])) > self::EDIT_WINDOW) {
            \flash('error', 'Messages can be edited for 15 minutes after sending.');
            redirect('messages/thread', ['id' => (int) $conversation['conversation_id']]);
        }
        if ($body === '' || mb_strlen($body) > 4000) {
            \flash('error', 'Write the corrected message before saving.');
            redirect('messages/thread', ['id' => (int) $conversation['conversation_id']]);
        }

        Database::update('message', [
            'body' => $body,
            'edited_at' => date('Y-m-d H:i:s'),
        ], 'message_id = ?', [$messageId]);

        Audit::log('message.edited', 'message', $messageId, ['body' => $message['body']], ['body' => $body]);
        \flash('success', 'Message updated.');
        redirect('messages/thread', ['id' => (int) $conversation['conversation_id']], 'm-' . $messageId);
    }

    public function remove(): void
    {
        $userId = (int) Auth::id();
        $conversation = $this->conversationOrFail();
        $messageId = (int) \post('message_id', 0);

        $message = Database::one(
            'SELECT * FROM message WHERE message_id = ? AND conversation_id = ?',
            [$messageId, (int) $conversation['conversation_id']]
        );

        if ($message === null || (int) $message['sender_id'] !== $userId || $message['deleted_at'] !== null) {
            \flash('error', 'That message cannot be deleted from your account.');
            redirect('messages/thread', ['id' => (int) $conversation['conversation_id']]);
        }

        Database::update('message', [
            'deleted_at' => date('Y-m-d H:i:s'),
        ], 'message_id = ?', [$messageId]);

        Audit::log('message.deleted', 'message', $messageId, ['body' => $message['body']]);
        \flash('success', 'Message deleted. Replies keep their place in the thread.');
        redirect('messages/thread', ['id' => (int) $conversation['conversation_id']]);
    }

    public function react(): void
    {
        $userId = (int) Auth::id();
        $conversation = $this->conversationOrFail();
        $messageId = (int) \post('message_id', 0);
        $emoji = (string) \post('emoji', '');

        if (!in_array($emoji, self::REACTIONS, true)) {
            \flash('error', 'That reaction is not available.');
            redirect('messages/thread', ['id' => (int) $conversation['conversation_id']]);
        }

        $exists = Database::one(
            'SELECT 1 AS found FROM message WHERE message_id = ? AND conversation_id = ?',
            [$messageId, (int) $conversation['conversation_id']]
        );
        if ($exists === null) {
            redirect('messages/thread', ['id' => (int) $conversation['conversation_id']]);
        }

        $mine = Database::one(
            'SELECT 1 AS found FROM message_reaction WHERE message_id = ? AND user_id = ? AND emoji = ?',
            [$messageId, $userId, $emoji]
        );
        if ($mine !== null) {
            Database::q(
                'DELETE FROM message_reaction WHERE message_id = ? AND user_id = ? AND emoji = ?',
                [$messageId, $userId, $emoji]
            );
        } else {
            Database::insert('message_reaction', [
                'message_id' => $messageId,
                'user_id' => $userId,
                'emoji' => $emoji,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        redirect('messages/thread', ['id' => (int) $conversation['conversation_id']], 'm-' . $messageId);
    }

    private function conversationOrFail(): array
    {
        $id = (int) \qget('id', \post('conversation_id', 0));
        $conversation = $id > 0 ? Messenger::conversation($id) : null;

        if ($conversation === null) {
            http_response_code(404);
            View::error(404);
            exit;
        }

        $userId = (int) Auth::id();
        if (!Messenger::mayAccess($conversation, $userId, Auth::role())) {
            Audit::log('message.denied', 'conversation', $id, null, ['user_id' => $userId]);
            http_response_code(403);
            View::error(403);
            exit;
        }

        if (!Messenger::isMember($id, $userId) && Auth::role() === 'coordinator') {
            Messenger::joinAsObserver($conversation, $userId);
        }

        return $conversation;
    }
}
