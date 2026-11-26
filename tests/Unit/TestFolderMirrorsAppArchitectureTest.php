<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\TestLocationGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

$app = [
    'app/Http/Controllers/Order/ShowController.php' => "<?php\n",
    'app/Http/Controllers/Order/UpdateController.php' => "<?php\n",
    'app/Modules/Orders/Actions/CancelOrder.php' => "<?php\n",
    'app/Modules/Orders/Support/OrderNumber.php' => "<?php\n",
    'app/Models/Tenant/Order.php' => "<?php\n",
];

$homes = ['Bootstrap' => 'bootstrap', 'Config' => 'config', 'Database' => 'database', 'Lang' => 'lang', 'Routing' => 'routes'];

dataset('tests that sit away from the code they cover', [
    'a folder that is not in app' => ['tests/Feature/Orders/FlowTest.php', 'tests/Feature/Orders/FlowTest.php: app/Orders does not exist, the test belongs in the folder of the code it covers'],
    'a nested folder that is not in app' => ['tests/Feature/Http/Controllers/Order/Nested/FlowTest.php', 'app/Http/Controllers/Order/Nested does not exist, the test belongs in the folder of the code it covers'],
    'a class in another folder of the same tier' => ['tests/Feature/Modules/Orders/CancelOrderTest.php', 'the class CancelOrder lives in app/Modules/Orders/Actions, the test belongs in the same folder of tests/Feature'],
    'a class under a shorter path' => ['tests/Feature/Models/OrderTest.php', 'the class Order lives in app/Models/Tenant, the test belongs in the same folder of tests/Feature'],
    'a class at the root of the tier' => ['tests/Feature/UpdateControllerTest.php', 'the class UpdateController lives in app/Http/Controllers/Order'],
    'a folder of the unit tier that is not in app' => ['tests/Unit/Checkout/CheckoutEndToEndTest.php', 'app/Checkout does not exist, the test belongs in the folder of the code it covers'],
]);

it('reports a test that sits away from the class it covers or from any folder of app', function (string $test, string $words) use ($app, $homes): void {
    $root = SourceSandbox::create([...$app, $test => "<?php\n"], prefix: '.checkout-');

    try {
        $violations = new TestLocationGuard(new PhpFileInspector(), $root, 'app', 'tests', ['Unit', 'Feature'], $homes)->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith($test . ': ')
        ->and($violations[0])->toContain($words);
})->with('tests that sit away from the code they cover');

dataset('tests that sit where the code sits', [
    'a controller test in its folder' => ['tests/Feature/Http/Controllers/Order/ShowControllerTest.php'],
    'a flow test in a folder of app' => ['tests/Feature/Http/Controllers/Order/OrderFlowTest.php'],
    'an action test in its folder' => ['tests/Feature/Modules/Orders/Actions/CancelOrderTest.php'],
    'a model test in its folder' => ['tests/Feature/Models/Tenant/OrderTest.php'],
    'a migration test in the database home' => ['tests/Feature/Database/Migrations/AddOrdersMigrationTest.php'],
    'a config test in the config home' => ['tests/Feature/Config/QueueConfigTest.php'],
    'a route test in the routing home' => ['tests/Feature/Routing/RouteFilesTest.php'],
    'a flow test at the root of the tier' => ['tests/Feature/ExampleTest.php'],
    'an architecture test at the root of a tier' => ['tests/Unit/ArchHttpTest.php'],
    'a unit test of a support class' => ['tests/Unit/Modules/Orders/Support/OrderNumberTest.php'],
    'a file that is not a test' => ['tests/Unit/Modules/Orders/Support/Helper.php'],
    'a file outside the tiers' => ['tests/Concerns/Isolation.php'],
]);

it('accepts a test beside its class, in a folder of app or in a home folder, and files that are not tests', function (string $test) use ($app, $homes): void {
    $root = SourceSandbox::create([...$app, $test => "<?php\n"], prefix: '.checkout-');

    try {
        $violations = new TestLocationGuard(new PhpFileInspector(), $root, 'app', 'tests', ['Unit', 'Feature'], $homes)->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toBe([]);
})->with('tests that sit where the code sits');

it('lets every test sit in the folder of the class it covers, or in a folder of app', function () use ($homes): void {
    $guard = new TestLocationGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'tests', ['Unit', 'Feature'], $homes);

    expect($guard->violations())->toBe([]);
});
