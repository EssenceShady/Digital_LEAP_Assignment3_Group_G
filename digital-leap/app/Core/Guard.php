<?php
declare(strict_types=1);

namespace Leap\Core;

final class Guard
{
    public static function requirePermission(?string $permission): void
    {
        if ($permission === null) {
            return;
        }

        if (!Auth::check()) {
            \flash('info', 'Please sign in to continue.');
            \redirect('login');
        }

        if ($permission === 'auth') {
            return;
        }

        if (!Auth::can($permission)) {
            Audit::log('security.access_denied', null, null, null, [
                'permission' => $permission,
                'route' => (string) ($_GET['r'] ?? ''),
            ]);
            http_response_code(403);
            View::error(403);
            exit;
        }
    }

    public static function requireRole(string ...$roles): void
    {
        if (!Auth::check()) {
            \flash('info', 'Please sign in to continue.');
            \redirect('login');
        }

        if (!in_array(Auth::role(), $roles, true)) {
            Audit::log('security.access_denied', null, null, null, [
                'role' => Auth::role(),
                'route' => (string) ($_GET['r'] ?? ''),
            ]);
            http_response_code(403);
            View::error(403);
            exit;
        }
    }
}
