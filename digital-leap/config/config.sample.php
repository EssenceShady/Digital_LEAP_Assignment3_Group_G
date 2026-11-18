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
    // Optional third tier for DIVA. Off by default: the assistant works from
    // rules and the guidebook alone. If you run a local model (for example
    // Ollama) on this same machine, set enabled to true. Only loopback
    // addresses are accepted; the request is abandoned after timeout_ms.
    'diva' => [
        'local_model' => [
            'enabled' => false,
            'endpoint' => 'http://127.0.0.1:11434',
            'model' => 'llama3.2',
            'timeout_ms' => 400,
        ],
    ],
    'timezone' => 'Africa/Freetown',
];
