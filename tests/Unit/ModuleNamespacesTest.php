<?php

declare(strict_types=1);

use Tests\Support\ModuleNamespaces;
use Tests\Support\SourceSandbox;

uses()->group('arch');

it('names the namespace of a folder in every module that has it, and none when no module has it', function (): void {
    $root = SourceSandbox::create([
        'app/Modules/Orders/Actions/PlaceOrder.php' => '<?php',
        'app/Modules/Billing/Actions/Charge.php' => '<?php',
        'app/Modules/Billing/Data/InvoiceData.php' => '<?php',
        'app/Modules/Stray.php' => '<?php',
    ], prefix: '.checkout-');

    try {
        expect(ModuleNamespaces::of('Actions', $root))->toBe(['App\Modules\Billing\Actions', 'App\Modules\Orders\Actions'])
            ->and(ModuleNamespaces::of('Data', $root))->toBe(['App\Modules\Billing\Data'])
            ->and(ModuleNamespaces::of('Jobs', $root))->toBe([]);
    } finally {
        SourceSandbox::remove($root);
    }
});

it('reads the modules of the application', function (): void {
    expect(ModuleNamespaces::of('Enums'))->toContain('App\Modules\Localization\Enums');
});
