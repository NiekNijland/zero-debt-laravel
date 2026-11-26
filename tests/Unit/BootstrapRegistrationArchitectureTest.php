<?php

declare(strict_types=1);

use Tests\Support\Architecture\BootstrapRegistrationGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('registrations that fail', [
    'a listener imported in a provider' => ['provider', "<?php\nuse App\\Modules\\Orders\\Listeners\\SendMail;\n", 'listeners are found by discovery'],
    'a listener of another module imported in a provider' => ['provider', "<?php\nnamespace App\\Providers;\nuse App\\Modules\\Billing\\Listeners\\Charge;\n", 'a provider imports a listener of a module'],
    'a bootstrap file without discovery' => ['bootstrap', "<?php\n\$middleware->append(A::class);\n", 'the discovery of listeners is gone'],
]);

it('detects a listener registered by hand and a bootstrap file that lost the discovery', function (string $kind, string $source, string $words): void {
    $guard = new BootstrapRegistrationGuard(dirname(__DIR__, 2), 'app/Providers', 'bootstrap/app.php', 'App\\\\Modules\\\\\\w+\\\\Listeners\\\\', '->withEvents(discover: [x])');
    $violations = $kind === 'provider' ? $guard->providerInSource($source) : $guard->bootstrapInSource($source);

    expect($violations)->not->toBe([])
        ->and(implode("\n", $violations))->toContain($words);
})->with('registrations that fail');

it('accepts a provider that imports no listener and a bootstrap file with discovery', function (): void {
    $guard = new BootstrapRegistrationGuard(dirname(__DIR__, 2), 'app/Providers', 'bootstrap/app.php', 'App\\\\Modules\\\\\\w+\\\\Listeners\\\\', '->withEvents(discover: [x])');

    expect($guard->providerInSource("<?php\nuse App\\Modules\\Orders\\Models\\Order;\nuse App\\Listeners\\Other;\n"))->toBe([])
        ->and($guard->bootstrapInSource("<?php\n->withEvents(discover: [x])\n"))->toBe([]);
});

it('reads the providers and the bootstrap file below a checkout under a dot directory', function (): void {
    $root = SourceSandbox::create(['providers/P.php' => "<?php\nuse App\\Modules\\Orders\\Listeners\\L;\n", 'boot/app.php' => "<?php\n"], prefix: '.checkout-');

    try {
        $violations = new BootstrapRegistrationGuard($root, 'providers', 'boot/app.php', 'App\\\\Modules\\\\\\w+\\\\Listeners\\\\', 'D')->violations();
        $missing = new BootstrapRegistrationGuard($root, 'providers', 'boot/none.php', 'X', 'D')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations[0])->toStartWith('providers/P.php: a provider imports a listener of a module')
        ->and($violations)->toContain('boot/app.php: the discovery of listeners is gone: add D, or a listener is never registered')
        ->and($missing[count($missing) - 1])->toContain('the bootstrap file does not exist');
});

it('finds listeners by discovery of the module folders', function (): void {
    $guard = new BootstrapRegistrationGuard(dirname(__DIR__, 2), 'app/Providers', 'bootstrap/app.php', 'App\\\\Modules\\\\\\w+\\\\Listeners\\\\', "->withEvents(discover: [__DIR__ . '/../app/Modules/*/Listeners'])");

    expect($guard->violations())->toBe([]);
});
