<?php

declare(strict_types=1);

use App\Modules\Localization\Enums\Locale;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

it('tests on the production database engine', function (): void {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(Config::string('database.connections.pgsql.driver'))->toBe('pgsql');
});

it('defaults to a supported locale with its fallback', function (): void {
    expect(Locale::tryFrom(Config::string('app.locale')))->toBeInstanceOf(Locale::class)
        ->and(Locale::tryFrom(Config::string('app.fallback_locale')))->toBeInstanceOf(Locale::class);
});

it('runs in utc', function (): void {
    expect(Config::string('app.timezone'))->toBe('UTC');
});

it('holds typed numbers for the limits', function (): void {
    expect(Config::integer('rate-limits.read'))->toBeGreaterThan(0)
        ->and(Config::integer('rate-limits.write'))->toBeGreaterThan(0)
        ->and(Config::integer('notes.max_per_page'))->toBeGreaterThanOrEqual(Config::integer('notes.per_page'))
        ->and(Config::integer('sanctum.expiration'))->toBeGreaterThan(0);
});

it('authenticates by token only', function (): void {
    expect(Config::string('auth.defaults.guard'))->toBe('sanctum')
        ->and(Config::array('sanctum.stateful'))->toBe([])
        ->and(Config::array('sanctum.guard'))->toBe([]);
});
