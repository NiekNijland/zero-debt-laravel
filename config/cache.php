<?php

declare(strict_types=1);

return [
    'default' => (string) env('CACHE_STORE', 'database'),
    'stores' => [
        'array' => [
            'driver' => 'array',
            'serialize' => true,
        ],
        'database' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'cache',
            'lock_connection' => null,
            'lock_table' => 'cache_locks',
        ],
    ],
    'prefix' => (string) env('CACHE_PREFIX', 'zero-debt-laravel-cache-'),
];
