<?php
declare(strict_types=1);

namespace Leap\Controllers;

use Leap\Core\Auth;
use Leap\Core\Audit;
use Leap\Core\Database;
use Leap\Core\View;

final class NotificationController
{
    public function index(): void
    {
        $userId = (int) Auth::id();
        $filter = (string) \qget('filter', 'all');
        $where = 'user_id = ?';
        $params = [$userId];
        if ($filter === 'unread') {
            $where .= ' AND is_read = 0';
        } elseif ($filter === 'read') {
            $where .= ' AND is_read = 1';
        }

        $items = Database::all(
            'SELECT * FROM notification WHERE ' . $where . ' ORDER BY created_at DESC LIMIT 100',
            $params
        );
        $unread = (int) Database::val('SELECT COUNT(*) FROM notification WHERE user_id = ? AND is_read = 0', [$userId]);

        View::render('shared/notifications', [
            'title' => 'Notifications',
            'items' => $items,
            'filter' => $filter,
            'unread' => $unread,
        ]);
    }

    public function read(): void
    {
        $id = (int) \post('notification_id', 0);
        $row = Database::one(
            'SELECT notification_id, link_url FROM notification WHERE notification_id = ? AND user_id = ?',
            [$id, (int) Auth::id()]
        );
        if ($row === null) {
            \flash('error', 'That notification does not belong to your account.');
            \redirect('notifications');
        }

        Database::update('notification', [
            'is_read' => 1,
            'read_at' => date('Y-m-d H:i:s'),
        ], 'notification_id = ? AND user_id = ?', [$id, (int) Auth::id()]);

        $link = (string) $row['link_url'];
        if ($link !== '' && str_starts_with($link, 'index.php')) {
            header('Location: ' . ($GLOBALS['base'] ?? '') . '/' . $link);
            exit;
        }
        \redirect('notifications');
    }

    public function readAll(): void
    {
        $count = Database::update('notification', [
            'is_read' => 1,
            'read_at' => date('Y-m-d H:i:s'),
        ], 'user_id = ? AND is_read = 0', [(int) Auth::id()]);

        Audit::log('notification.read_all', 'notification', null, null, ['marked' => $count]);
        \flash('success', 'All notifications marked as read.');
        \redirect('notifications');
    }
}
