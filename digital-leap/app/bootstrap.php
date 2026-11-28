<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('CONFIG_PATH', BASE_PATH . '/config');

spl_autoload_register(static function (string $class): void {
    $prefix = 'Leap\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = APP_PATH . '/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$configFile = CONFIG_PATH . '/config.php';
if (!is_file($configFile)) {
    $sample = CONFIG_PATH . '/config.sample.php';
    if (!is_file($sample) || !@copy($sample, $configFile)) {
        http_response_code(500);
        exit('Configuration missing. Copy config/config.sample.php to config/config.php.');
    }
}

$GLOBALS['config'] = require $configFile;

date_default_timezone_set($GLOBALS['config']['timezone'] ?? 'Africa/Freetown');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
@ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

foreach ([STORAGE_PATH, STORAGE_PATH . '/logs', STORAGE_PATH . '/uploads'] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$GLOBALS['base'] = $GLOBALS['config']['app']['base_url'] ?? '';
if ($GLOBALS['base'] === '') {
    $GLOBALS['base'] = $scriptDir === '' ? '' : $scriptDir;
}

require APP_PATH . '/helpers.php';

if (!headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(self), microphone=(), camera=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'");
}

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_name('LEAPSESSID');
    session_start();
}

set_exception_handler(static function (Throwable $ex): void {
    error_log(sprintf('[%s] %s in %s:%d', date('c'), $ex->getMessage(), $ex->getFile(), $ex->getLine()));
    if (!headers_sent()) {
        http_response_code(500);
    }
    $debug = (bool) ($GLOBALS['config']['app']['debug'] ?? false);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Server error</title>';
    echo '<link rel="stylesheet" href="' . htmlspecialchars(asset('css/app.css'), ENT_QUOTES, 'UTF-8') . '"></head><body>';
    echo '<main class="center-card"><h1>Something went wrong</h1>';
    echo '<p>The request could not be completed. The error has been written to the application log.</p>';
    echo '<p><a class="btn" href="' . htmlspecialchars(url(''), ENT_QUOTES, 'UTF-8') . '">Return to the home page</a></p>';
    if ($debug) {
        echo '<pre class="code-block">' . htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    }
    echo '</main></body></html>';
});

if (!$isCli) {
    if (!empty($_SESSION['uid'])) {
        $idleMinutes = 20;
        $absoluteHours = 8;
        try {
            $idleMinutes = (int) Leap\Core\Settings::get('session_idle_minutes', 20);
            $absoluteHours = (int) ($GLOBALS['config']['session']['absolute_hours'] ?? 8);
        } catch (Throwable $e) {
            // Database unavailable while checking the session: fall back to the defaults.
        }
        $now = time();
        $expired = false;
        if (isset($_SESSION['last_seen']) && ($now - (int) $_SESSION['last_seen']) > $idleMinutes * 60) {
            $expired = true;
        }
        if (isset($_SESSION['login_time']) && ($now - (int) $_SESSION['login_time']) > $absoluteHours * 3600) {
            $expired = true;
        }
        if ($expired) {
            Leap\Core\Auth::logout(true);
            flash('info', 'Your session expired. Please sign in again.');
            redirect('login');
        }
        $_SESSION['last_seen'] = $now;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !Leap\Core\Csrf::verify()) {
        Leap\Core\Audit::log('security.csrf_reject', null, null, null, ['route' => $_GET['r'] ?? '']);
        // Apache rewrites 419 when it is set through http_response_code(); an explicit status line survives.
        header('HTTP/1.1 419 Authentication Timeout', true, 419);
        Leap\Core\View::error(419);
        exit;
    }

    if (!empty($_SESSION['must_change_password'])) {
        $route = trim((string) ($_GET['r'] ?? ''), '/');
        if (!in_array($route, ['profile', 'logout'], true)) {
            flash('info', 'Choose a new password before continuing.');
            redirect('profile', [], 'security');
        }
    }

    Leap\Core\Router::dispatch(
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
        (string) ($_GET['r'] ?? '')
    );
}
