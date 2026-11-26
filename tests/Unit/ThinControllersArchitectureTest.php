<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\ThinControllerGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('controller business logic', [
    'transaction' => ["<?php\nuse Illuminate\\Support\\Facades\\DB;\nDB::transaction(fn () => null);\n", 'DB:: in a controller'],
    'fully qualified DB facade' => ['<?php
' . DB::class . '::table(\'orders\')->get();
', 'DB:: in a controller'],
    'lock' => ["<?php\n\$order = Order::query()->lockForUpdate()->find(1);\n", 'lockforupdate() in a controller'],
    'save' => ["<?php\n\$user->save();\n", 'save() in a controller'],
    'update on a model' => ["<?php\n\$coupon->update(['ends_at' => now()]);\n", 'update() in a controller'],
    'static create' => ["<?php\nCoupon::create([]);\n", 'create() in a controller'],
    'create on a query' => ["<?php\nCoupon::query()->create([]);\n", 'create() in a controller'],
    'delete' => ["<?php\n\$order->delete();\n", 'delete() in a controller'],
    'force delete' => ["<?php\n\$order->forceDelete();\n", 'forcedelete() in a controller'],
    'restore' => ["<?php\n\$order->restore();\n", 'restore() in a controller'],
    'attach' => ["<?php\n\$user->roles()->attach(1);\n", 'attach() in a controller'],
    'sync' => ["<?php\n\$user->roles()->sync([1]);\n", 'sync() in a controller'],
    'detach' => ["<?php\n\$user->roles()->detach(1);\n", 'detach() in a controller'],
    'insert' => ["<?php\nCoupon::query()->insert([]);\n", 'insert() in a controller'],
    'upsert' => ["<?php\nCoupon::query()->upsert([], ['id']);\n", 'upsert() in a controller'],
    'increment' => ["<?php\n\$order->increment('count');\n", 'increment() in a controller'],
    'decrement' => ["<?php\n\$order->decrement('count');\n", 'decrement() in a controller'],
    'update or create' => ["<?php\nTaxRate::query()->updateOrCreate([], []);\n", 'updateorcreate() in a controller'],
    'nullsafe delete' => ["<?php\n\$order?->delete();\n", 'delete() in a controller'],
    'policy method named like a write' => ["<?php\n\$policy->update(\$user, \$product);\n", 'update() in a controller'],
    'method injected service' => ["<?php\nfinal class C { public function __invoke(\\App\\Modules\\Products\\Services\\ProductService \$service): void { \$service->delete(1); } }\n", 'delete() in a controller'],
    'property that is not an action or a service' => ["<?php\nfinal class C { public function __construct(private \\App\\Models\\Tenant\\Order \$order) {} public function __invoke(): void { \$this->order->delete(); } }\n", 'delete() in a controller'],
]);

it('detects business logic in a controller', function (string $source, string $words): void {
    $violations = new ThinControllerGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Http/Controllers', 40, ['\Actions\\', '\Services\\'])->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('line ')
        ->and($violations[0])->toContain($words, 'into an action of the module');
})->with('controller business logic');

it('detects an __invoke that is too long', function (): void {
    $guard = new ThinControllerGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Http/Controllers', 40, ['\Actions\\', '\Services\\']);
    $long = "<?php\nfinal class C\n{\n    public function __invoke(): void\n    {\n" . str_repeat("        \$a = 1;\n", 38) . "    }\n}\n";
    $shorter = "<?php\nfinal class C\n{\n    public function __invoke(): void\n    {\n" . str_repeat("        \$a = 1;\n", 37) . "    }\n}\n";

    expect($guard->inSource($long))->toHaveCount(1)
        ->and($guard->inSource($long)[0])->toContain('__invoke has 41 lines, the limit is 40', 'move the logic into an action')
        ->and($guard->inSource($shorter))->toBe([]);
});

dataset('controllers that call an action or a service they got through the constructor', [
    'a promoted action' => ["<?php\nnamespace App\\Http\\Controllers\\Order;\nuse App\\Modules\\Orders\\Actions\\DeleteOrder;\nfinal readonly class DestroyController { public function __construct(private DeleteOrder \$deleteOrder) {} public function __invoke(\$order): void { \$this->deleteOrder->handle(\$order); } }\n"],
    'an action called with a write name' => ["<?php\nnamespace App\\Http\\Controllers\\Order;\nuse App\\Modules\\Orders\\Actions\\UpdateOrder;\nfinal readonly class UpdateController { public function __construct(private UpdateOrder \$update) {} public function __invoke(\$order): void { \$this->update->update(\$order); } }\n"],
    'a service with a write name' => ["<?php\nnamespace App\\Http\\Controllers\\Order;\nuse App\\Modules\\Products\\Services\\ProductService;\nfinal readonly class ProductController { public function __construct(private ProductService \$products) {} public function __invoke(\$order): void { \$this->products->delete(\$order->product); } }\n"],
    'a nullsafe call on a service' => ["<?php\nnamespace App\\Http\\Controllers\\Order;\nuse App\\Modules\\Products\\Services\\ProductService;\nfinal readonly class ProductController { public function __construct(private ProductService \$products) {} public function __invoke(\$order): void { \$this->products?->update(\$order->product); } }\n"],
    'an action kept in a typed property' => ["<?php\nnamespace App\\Http\\Controllers\\Order;\nuse App\\Modules\\Users\\Actions\\CreateUser;\nfinal class StoreController { private CreateUser \$create; public function __construct(CreateUser \$create) { \$this->create = \$create; } public function __invoke(\$order): void { \$this->create->create(\$order); } }\n"],
    'reads and a redirect' => ["<?php\nnamespace App\\Http\\Controllers\\Order;\nfinal class ListController { public function __invoke(): void { \$orders = Order::query()->where('id', 1)->get(); Inertia::flash('success', 'done'); back(); } }\n"],
]);

it('accepts a controller that calls an action or a service it gets through its constructor', function (string $source): void {
    expect(new ThinControllerGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Http/Controllers', 40, ['\Actions\\', '\Services\\'])->inSource($source))->toBe([]);
})->with('controllers that call an action or a service they got through the constructor');

it('reads a tree under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create([
        'app/Http/Controllers/BadController.php' => "<?php\nfinal class BadController { public function __invoke(\$o): void { \$o->delete(); } }\n",
        'app/Http/Controllers/GoodController.php' => "<?php\nfinal class GoodController { public function __invoke(\$o): void { back(); } }\n",
    ], prefix: '.checkout-');

    try {
        $violations = new ThinControllerGuard(new PhpFileInspector(), $root, 'app/Http/Controllers', 40, ['\Actions\\', '\Services\\'])->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Http/Controllers/BadController.php:line 2: delete() in a controller');
});

it('keeps controllers thin: no database writes, locks, transactions or long __invoke methods', function (): void {
    $guard = new ThinControllerGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Http/Controllers', 40, ['\Actions\\', '\Services\\']);

    expect($guard->violations())->toBe([]);
});
