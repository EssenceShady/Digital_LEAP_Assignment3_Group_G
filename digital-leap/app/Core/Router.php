<?php
declare(strict_types=1);

namespace Leap\Core;

use Throwable;

final class Router
{
    public static function dispatch(string $method, string $route): void
    {
        $routes = require APP_PATH . '/routes.php';
        $route = trim($route, '/');
        $key = strtoupper($method) . ' ' . $route;

        if (!isset($routes[$key])) {
            http_response_code(404);
            View::error(404);
            return;
        }

        [$class, $action, $permission] = $routes[$key];

        try {
            Guard::requirePermission($permission);
            $controller = new $class();
            $controller->$action();
        } catch (Throwable $e) {
            throw $e;
        }
    }
}
