<?php
declare(strict_types=1);

namespace Leap\Core;

final class Auth
{
    private static ?array $cache = null;

    public static function attempt(string $email, string $password): string
    {
        $user = Database::one(
            'SELECT u.*, r.role_key, r.role_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.email = ?',
            [strtolower(trim($email))]
        );

        $generic = 'Invalid credentials, or the account is unavailable.';

        if ($user === null) {
            Audit::log('login.failed', 'user', null, null, ['email_hash' => substr(hash('sha256', $email), 0, 16)]);
            return $generic;
        }

        if ($user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time()) {
            Audit::log('login.blocked_lockout', 'user', (int) $user['user_id']);
            return 'This account is temporarily locked. Try again later.';
        }

        if ($user['status'] !== 'active' || $user['password_hash'] === null) {
            Audit::log('login.blocked_status', 'user', (int) $user['user_id']);
            return $generic;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            $max = (int) Settings::get('max_login_attempts', 5);
            $lockMinutes = (int) Settings::get('lockout_minutes', 15);
            $attempts = (int) $user['failed_attempts'] + 1;
            $data = ['failed_attempts' => $attempts];
            if ($attempts >= $max) {
                $data['locked_until'] = date('Y-m-d H:i:s', time() + $lockMinutes * 60);
                $data['failed_attempts'] = 0;
                Audit::log('account.locked', 'user', (int) $user['user_id'], null, ['attempts' => $attempts]);
            }
            Database::update('users', $data, 'user_id = ?', [(int) $user['user_id']]);
            Audit::log('login.failed', 'user', (int) $user['user_id'], null, ['attempts' => $attempts]);
            return $generic;
        }

        Database::update('users', [
            'failed_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => date('Y-m-d H:i:s'),
        ], 'user_id = ?', [(int) $user['user_id']]);

        self::establish($user);
        Audit::log('login.success', 'user', (int) $user['user_id']);

        return '';
    }

    public static function establish(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $user['user_id'];
        $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['role'] = $user['role_key'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_seen'] = time();
        $_SESSION['must_change_password'] = (int) ($user['must_change_password'] ?? 0);

        $rows = Database::all(
            'SELECT p.perm_key FROM role_permissions rp
             JOIN permissions p ON p.permission_id = rp.permission_id
             WHERE rp.role_id = ?',
            [(int) $user['role_id']]
        );
        $_SESSION['perms'] = array_column($rows, 'perm_key');
        Csrf::rotate();
        self::$cache = $user;
    }

    public static function user(): ?array
    {
        if (empty($_SESSION['uid'])) {
            return null;
        }
        if (self::$cache !== null) {
            return self::$cache;
        }

        $user = Database::one(
            'SELECT u.*, r.role_key, r.role_name FROM users u
             JOIN roles r ON r.role_id = u.role_id
             WHERE u.user_id = ?',
            [(int) $_SESSION['uid']]
        );

        if ($user === null || $user['status'] !== 'active') {
            self::logout();
            \redirect('login');
        }

        self::$cache = $user;
        return $user;
    }

    public static function id(): ?int
    {
        return empty($_SESSION['uid']) ? null : (int) $_SESSION['uid'];
    }

    public static function check(): bool
    {
        return !empty($_SESSION['uid']);
    }

    public static function role(): string
    {
        return (string) ($_SESSION['role'] ?? '');
    }

    public static function name(): string
    {
        return (string) ($_SESSION['name'] ?? '');
    }

    public static function can(string $permission): bool
    {
        return in_array($permission, $_SESSION['perms'] ?? [], true);
    }

    public static function logout(bool $expired = false): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) ($params['secure'] ?? false), (bool) ($params['httponly'] ?? true));
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        self::$cache = null;
    }
}
