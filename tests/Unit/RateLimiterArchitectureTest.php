<?php

declare(strict_types=1);

use App\Providers\RateLimitServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\RateLimiterGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('limiters that leave the one way', [
    'a number of attempts' => ["<?php\nnamespace App\\Providers;\nuse Illuminate\\Cache\\RateLimiting\\Limit;\nuse Illuminate\\Support\\Facades\\RateLimiter;\nfinal class RateLimitServiceProvider { public function boot(): void { RateLimiter::for('uploads', static fn (): Limit => Limit::perMinute(60)->by('x')); } }\n", 'a limit with a number or a constant in the code'],
    'a number per hour' => ["<?php\nnamespace App\\Providers;\nuse Illuminate\\Cache\\RateLimiting\\Limit;\nLimit::perHour(1000);\n", 'a limit with a number or a constant in the code'],
    'a window in minutes' => ["<?php\nnamespace App\\Providers;\nuse Illuminate\\Cache\\RateLimiting\\Limit;\nuse Illuminate\\Support\\Facades\\Config;\nLimit::perMinutes(5, Config::integer('rate-limits.uploads'));\n", 'a limit with a number or a constant in the code'],
    'a decay in seconds' => ["<?php\nnamespace App\\Providers;\nuse Illuminate\\Cache\\RateLimiting\\Limit;\nuse Illuminate\\Support\\Facades\\Config;\nnew Limit('uploads', Config::integer('rate-limits.uploads_per_minute'), 60);\n", 'a limit with a number or a constant in the code'],
    'a number inside an expression' => ["<?php\nnamespace App\\Providers;\nuse Illuminate\\Cache\\RateLimiting\\Limit;\nuse Illuminate\\Support\\Facades\\Config;\nLimit::perMinute(Config::integer('rate-limits.uploads_per_minute') * 2);\n", 'a limit with a number or a constant in the code'],
    'a class constant' => ["<?php\nnamespace App\\Providers;\nuse Illuminate\\Cache\\RateLimiting\\Limit;\nfinal class RateLimitServiceProvider { private const int UPLOADS = 60; public function boot(): void { Limit::perMinute(self::UPLOADS); } }\n", 'a limit with a number or a constant in the code'],
    'a fully qualified limit' => ['<?php
namespace App\Providers;
\\' . Limit::class . '::perDay(10);
', 'a limit with a number or a constant in the code'],
    'a limiter in another provider' => ["<?php\nnamespace App\\Providers;\nuse Illuminate\\Cache\\RateLimiting\\Limit;\nuse Illuminate\\Support\\Facades\\Config;\nuse Illuminate\\Support\\Facades\\RateLimiter;\nfinal class AppServiceProvider { public function boot(): void { RateLimiter::for('uploads', static fn (): Limit => Limit::perMinute(Config::integer('rate-limits.uploads_per_minute'))); } }\n", 'a limiter defined outside App\\Providers\\RateLimitServiceProvider'],
    'a limiter in a module' => ["<?php\nnamespace App\\Modules\\Products\\Support;\nuse Illuminate\\Cache\\RateLimiting\\Limit;\nuse Illuminate\\Support\\Facades\\Config;\nuse Illuminate\\Support\\Facades\\RateLimiter;\nRateLimiter::for('uploads', static fn (): Limit => Limit::perMinute(Config::integer('rate-limits.uploads_per_minute')));\n", 'a limiter defined outside App\\Providers\\RateLimitServiceProvider'],
    'a limiter defined through the container' => ["<?php\nnamespace App\\Modules\\Products\\Support;\nuse Illuminate\\Cache\\RateLimiter;\nuse Illuminate\\Cache\\RateLimiting\\Limit;\nuse Illuminate\\Support\\Facades\\Config;\napp(RateLimiter::class)->for('uploads', static fn (): Limit => Limit::perMinute(Config::integer('rate-limits.uploads_per_minute')));\n", 'a limiter defined outside App\\Providers\\RateLimitServiceProvider'],
    'a limiter defined on an injected limiter' => ["<?php\nnamespace App\\Modules\\Products\\Support;\nuse Illuminate\\Cache\\RateLimiter;\nfinal class Boot { public function __construct(private RateLimiter \$limiter) {} public function run(): void { \$this->limiter->for('uploads', fn () => null); } }\n", 'a limiter defined outside App\\Providers\\RateLimitServiceProvider'],
    'a throttle with numbers in a route' => ["<?php\nnamespace App\\Http;\nRoute::middleware('throttle:60,1')->get('/x', fn () => 1);\n", 'a throttle with numbers in the route ("throttle:60,1")'],
    'a name in kebab case' => ["<?php\nnamespace App\\Providers;\nuse Illuminate\\Cache\\RateLimiting\\Limit;\nuse Illuminate\\Support\\Facades\\Config;\nuse Illuminate\\Support\\Facades\\RateLimiter;\nfinal class RateLimitServiceProvider { public function boot(): void { RateLimiter::for('image-uploads', static fn (): Limit => Limit::perMinute(Config::integer('rate-limits.uploads_per_minute'))); } }\n", 'the limiter "image-uploads" is not named in snake_case'],
    'a name in camel case' => ["<?php\nnamespace App\\Providers;\nuse Illuminate\\Cache\\RateLimiting\\Limit;\nuse Illuminate\\Support\\Facades\\Config;\nuse Illuminate\\Support\\Facades\\RateLimiter;\nfinal class RateLimitServiceProvider { public function boot(): void { RateLimiter::for('imageUploads', static fn (): Limit => Limit::perMinute(Config::integer('rate-limits.uploads_per_minute'))); } }\n", 'the limiter "imageUploads" is not named in snake_case'],
]);

it('detects a limit with a number in the code, a limiter outside the provider and a name that is not snake_case', function (string $source, string $reason): void {
    $violations = new RateLimiterGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'bootstrap', 'routes'], 'config/rate-limits.php', RateLimitServiceProvider::class)->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($reason);
})->with('limiters that leave the one way');

it('accepts limits that read config, a limit copied from another and a limiter named after a config key', function (): void {
    $source = <<<'PHP'
        <?php
        namespace App\Providers;
        use Illuminate\Cache\RateLimiting\Limit;
        use Illuminate\Support\Facades\Config;
        use Illuminate\Support\Facades\RateLimiter;
        final class RateLimitServiceProvider
        {
            public function boot(): void
            {
                RateLimiter::for('checkout', static fn (Request $request): array => [
                    Limit::perMinute(Config::integer('rate-limits.checkout_per_minute'))->by('user:' . $request->user()?->id),
                    Limit::perHour(Config::integer('rate-limits.checkout_per_hour'))->by('ip:' . $request->ip()),
                ]);
                RateLimiter::for('app', static fn (Request $request): Limit => Limit::perMinute(self::actionLimit($request))->by((string) ($request->user()?->id ?? $request->ip())));
                foreach (Config::array('rate-limits.actions') as $action => $limit) {
                    RateLimiter::for($action, static fn (): Limit => Limit::perMinute(self::actionLimit($action)));
                }
            }

            private static function forGuests(string $name, Limit $limit): Limit
            {
                return new Limit($name . ':guest', $limit->maxAttempts, $limit->decaySeconds);
            }
        }
        final class StockLimit { public static function perMinute(int $items): int { return $items * 60; } }
        StockLimit::perMinute(5);
        PHP;

    expect(new RateLimiterGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'bootstrap', 'routes'], 'config/rate-limits.php', RateLimitServiceProvider::class)->inSource($source))->toBe([]);
});

it('reads a tree under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create([
        'app/Bad.php' => "<?php\nnamespace App;\nuse Illuminate\\Cache\\RateLimiting\\Limit;\nLimit::perMinute(60);\n",
        'app/Good.php' => "<?php\nnamespace App;\nuse Illuminate\\Cache\\RateLimiting\\Limit;\nuse Illuminate\\Support\\Facades\\Config;\nLimit::perMinute(Config::integer('rate-limits.read'));\n",
    ], prefix: '.checkout-');

    try {
        $violations = new RateLimiterGuard(new PhpFileInspector(), $root, ['app'], 'config/rate-limits.php', RateLimitServiceProvider::class)->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Bad.php: 4: a limit with a number or a constant in the code')
        ->and($violations[0])->toContain('config/rate-limits.php');
});

it('keeps every limiter in the rate limit provider, named in snake_case, with its number in config', function (): void {
    expect(new RateLimiterGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'bootstrap', 'routes'], 'config/rate-limits.php', RateLimitServiceProvider::class)->violations())->toBe([]);
});
