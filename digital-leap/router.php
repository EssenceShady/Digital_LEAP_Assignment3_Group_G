<?php
declare(strict_types=1);

/**
 * Router for the PHP built-in server:
 *   php -S 127.0.0.1:8080 router.php
 * Blocks source, configuration and stored uploads, then falls back to the
 * front controller. Apache uses .htaccess instead.
 */

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = '/' . ltrim($path, '/');

foreach (['/app/', '/bin/', '/config/', '/storage/', '/database/'] as $private) {
    if (strpos($path, $private) === 0) {
        http_response_code(404);
        exit('Not found');
    }
}

if ($path === '/index.php' || $path === '/') {
    require __DIR__ . '/index.php';
    return true;
}

if (preg_match('/\.php$/i', $path)) {
    http_response_code(404);
    exit('Not found');
}

// The built-in server does not know the PWA manifest type by default.
$mimeByExtension = ['webmanifest' => 'application/manifest+json'];
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if (isset($mimeByExtension[$extension]) && is_file(__DIR__ . $path)) {
    header('Content-Type: ' . $mimeByExtension[$extension] . '; charset=utf-8');
    header('Cache-Control: no-cache');
    readfile(__DIR__ . $path);
    return true;
}

if (is_file(__DIR__ . $path)) {
    return false;
}

$_GET['r'] = trim($path, '/');
require __DIR__ . '/index.php';
return true;
