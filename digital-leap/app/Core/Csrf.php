<?php
declare(strict_types=1);

namespace Leap\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function verify(): bool
    {
        $sent = $_POST['_csrf'] ?? '';
        if (!is_string($sent) || $sent === '' || empty($_SESSION['_csrf'])) {
            return false;
        }
        return hash_equals($_SESSION['_csrf'], $sent);
    }

    public static function rotate(): void
    {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
}
