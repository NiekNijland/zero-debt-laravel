<?php

declare(strict_types=1);

use Tests\Support\Architecture\ModuleOverlapGuard;
use Tests\Support\Narrow;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('modules that differ by a plural or a prefix', [
    'a prefix' => [['Products', 'ProductPrices', 'ProductImages'], ['ProductPrices overlaps Products', 'ProductImages overlaps Products']],
    'a plural' => [['Order', 'Orders'], ['Orders overlaps Order']],
    'a plural of another word' => [['Invoice', 'Invoices'], ['Invoices overlaps Invoice']],
    'a prefix of a singular' => [['Stock', 'StockLevels'], ['StockLevels overlaps Stock']],
    'a prefix of a plural' => [['Users', 'UserRoles'], ['UserRoles overlaps Users']],
    'a y that becomes ies' => [['Category', 'Categories'], ['Categories overlaps Category']],
]);

it('detects two modules that differ only by a plural or a prefix', function (array $modules, array $start): void {
    $violations = new ModuleOverlapGuard(dirname(__DIR__, 2), 'app/Modules')->inModules(Narrow::strings($modules));

    expect($violations)->toHaveCount(count($start));

    foreach ($start as $index => $text) {
        expect($violations[$index])->toStartWith(Narrow::string($text) . ': one module per domain')
            ->and($violations[$index])->toContain('fold ', 'or name it for a different domain');
    }
})->with('modules that differ by a plural or a prefix');

dataset('modules that are different domains', [
    'a word that only starts the same' => [['Rate', 'Ratings', 'Returns', 'TaxReturns', 'Stock', 'Stockists']],
    'unrelated modules' => [['Tenants', 'Users', 'Roles']],
    'a single module' => [['Orders']],
]);

it('accepts modules that are different domains and the near misses of a shared start', function (array $modules): void {
    expect(new ModuleOverlapGuard(dirname(__DIR__, 2), 'app/Modules')->inModules(Narrow::strings($modules)))->toBe([]);
})->with('modules that are different domains');

it('reads the module folders of a tree under a dot directory', function (): void {
    $root = SourceSandbox::create([
        'app/Modules/Order/.gitkeep' => '',
        'app/Modules/Orders/.gitkeep' => '',
        'app/Modules/Users/.gitkeep' => '',
    ], prefix: '.checkout-');

    try {
        $guard = new ModuleOverlapGuard($root, 'app/Modules');
        $modules = $guard->modules();
        $violations = $guard->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($modules)->toContain('Order', 'Orders', 'Users')
        ->and($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('Orders overlaps Order: one module per domain');
});

it('has one module per domain, never two that differ only by a plural or a prefix', function (): void {
    $guard = new ModuleOverlapGuard(dirname(__DIR__, 2), 'app/Modules');

    expect($guard->modules())->toContain('Notes', 'Localization')
        ->and($guard->violations())->toBe([]);
});
