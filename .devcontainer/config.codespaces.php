<?php
declare(strict_types=1);

/**
 * Configuration for the disposable Codespaces demo stack.
 *
 * This is not a developer's config/config.php: it holds only the password
 * declared next to it in docker-compose.yml, for a database that exists for
 * the lifetime of one codespace. It is mounted read-only at
 * digital-leap/config/config.php so the application never runs with sample
 * defaults pointing at 127.0.0.1.
 */
return [
    'db' => [
        'host'    => 'db',
        'port'    => 3306,
        'name'    => 'digital_leap',
        'user'    => 'root',
        'pass'    => 'leap-demo',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'base_url' => '',
        'debug'    => false,
    ],
    'session' => [
        'absolute_hours' => 8,
    ],
    'mail' => [
        'mode' => 'log',
    ],
    'upload' => [
        'max_bytes' => 5242880,
    ],
    'diva' => [
        'local_model' => [
            'enabled'    => false,
            'endpoint'   => 'http://127.0.0.1:11434',
            'model'      => 'llama3.2',
            'timeout_ms' => 400,
        ],
    ],
    'timezone' => 'Africa/Freetown',
];
