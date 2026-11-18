<?php
declare(strict_types=1);

/**
 * Digital LEAP configuration template.
 * Copy to config/config.php and edit only if your MySQL credentials differ.
 * Defaults match a standard XAMPP installation.
 */
return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'digital_leap',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        // Leave empty to auto-detect (works for /Digital_LEAP_Assignment3_Group_G/digital-leap/).
        'base_url' => '',
        // Set true to show exception messages on the error page while troubleshooting.
        'debug'    => false,
    ],
    'session' => [
        'absolute_hours' => 8,
    ],
    'mail' => [
        // "log" writes messages to storage/logs/mail.log; no email leaves the machine.
        'mode' => 'log',
    ],
    'upload' => [
        'max_bytes' => 5242880, // 5 MB
    ],
    'timezone' => 'Africa/Freetown',
];
