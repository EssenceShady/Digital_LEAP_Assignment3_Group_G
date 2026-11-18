<?php
declare(strict_types=1);

namespace Leap\Core;

use RuntimeException;

final class View
{
    public static function render(string $template, array $data = [], string $layout = 'layout'): void
    {
        $file = APP_PATH . '/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('View not found: ' . $template);
        }

        $title = $data['title'] ?? 'Digital LEAP';
        extract($data, EXTR_SKIP);

        ob_start();
        require $file;
        $content = ob_get_clean();

        if ($layout === '') {
            echo $content;
            return;
        }

        require APP_PATH . '/Views/' . $layout . '.php';
    }

    public static function error(int $code): void
    {
        $messages = [
            403 => ['Access denied', 'You do not have permission to perform this action.'],
            404 => ['Page not found', 'The page you requested does not exist.'],
            419 => ['Session expired', 'Your form session expired. Please return to the previous page and try again.'],
            500 => ['Server error', 'The request could not be completed.'],
        ];
        [$heading, $body] = $messages[$code] ?? $messages[500];

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . e($heading) . '</title>';
        echo '<link rel="stylesheet" href="' . e(asset('css/app.css')) . '"></head><body>';
        echo '<main class="center-card"><p class="error-code">' . $code . '</p>';
        echo '<h1>' . e($heading) . '</h1><p>' . e($body) . '</p>';
        echo '<p><a class="btn" href="' . e(url('dashboard')) . '">Back to the dashboard</a></p>';
        echo '</main></body></html>';
    }
}
