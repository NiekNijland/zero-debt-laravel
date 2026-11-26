<?php

declare(strict_types=1);

return [
    'name' => (string) env('APP_NAME', 'zero-debt-laravel'),
    'env' => (string) env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => (string) env('APP_URL', 'http://localhost'),
    'timezone' => 'UTC',
    'locale' => (string) env('APP_LOCALE', 'en'),
    'fallback_locale' => (string) env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => (string) env('APP_FAKER_LOCALE', 'en_US'),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(explode(',', (string) env('APP_PREVIOUS_KEYS', '')), static fn (string $key): bool => $key !== ''),
    ],
    'maintenance' => [
        'driver' => 'file',
        'store' => 'database',
    ],
];
