<?php

declare(strict_types=1);

return [
    'default' => (string) env('DB_CONNECTION', 'pgsql'),
    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => (string) env('DB_HOST', '127.0.0.1'),
            'port' => (int) env('DB_PORT', 5432),
            'database' => (string) env('DB_DATABASE', 'laravel'),
            'username' => (string) env('DB_USERNAME', 'laravel'),
            'password' => (string) env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => (string) env('DB_SSLMODE', 'prefer'),
        ],
    ],
    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],
];
