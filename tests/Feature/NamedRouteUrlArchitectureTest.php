<?php

declare(strict_types=1);

use Tests\Support\Architecture\NamedRouteUrlGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Feature\NamedRouteUrlArchitectureHelpers;
use Tests\Support\SourceSandbox;

dataset('hard-coded urls of named routes', [
    'the URL of a route without parameters' => ['tests/Feature/FooTest.php', "<?php\n\$this->get('/login')->assertOk();\n", "write route('login')"],
    'a post to the URL of a route' => ['tests/Feature/FooTest.php', "<?php\n\$this->actingAs(\$user)->post('/logout');\n", "write route('logout')"],
    'a JSON request' => ['tests/Support/Foo.php', "<?php\ntest()->getJson('/login');\n", "write route('login')"],
    'a request with the method as an argument' => ['tests/Feature/FooTest.php', "<?php\n\$this->json('POST', '/logout', []);\n", "write route('logout')"],
    'a call with the method as an argument' => ['tests/Feature/FooTest.php', "<?php\n\$this->call('GET', '/login');\n", "write route('login')"],
    'a URL that ends in a concatenated parameter' => ['tests/Feature/FooTest.php', "<?php\n\$this->get('/orders/' . \$order->getRouteKey());\n", "write route('orders.show')"],
    'a URL that ends in an interpolated parameter' => ['tests/Feature/FooTest.php', "<?php\n\$this->get(\"/orders/{\$order->getRouteKey()}\");\n", "write route('orders.show')"],
    'a URL with a query string' => ['tests/Feature/FooTest.php', "<?php\n\$this->get('/invoices/export?' . \$query);\n", "write route('invoices.export')"],
    'a URL with a literal query string' => ['tests/Feature/FooTest.php', "<?php\n\$this->get('/login?next=x');\n", "write route('login')"],
    'a Pest function' => ['tests/Feature/FooTest.php', "<?php\nuse function Pest\\Laravel\\get;\nget('/login')->assertOk();\n", "write route('login')"],
    'a Pest JSON function' => ['tests/Feature/FooTest.php', "<?php\nuse function Pest\\Laravel\\{actingAs, postJson};\npostJson('/logout', []);\n", "write route('logout')"],
    'a Pest function under an alias' => ['tests/Support/Foo.php', "<?php\nnamespace Tests\\Support;\nuse function Pest\\Laravel\\get as visit;\nvisit('/orders/' . \$order->getRouteKey());\n", "write route('orders.show')"],
    'a Pest function by its full name' => ['tests/Feature/FooTest.php', "<?php\n\\Pest\\Laravel\\call('GET', '/invoices/export?' . \$query);\n", "write route('invoices.export')"],
]);

it('finds a request to the hard-coded URL of a named route', function (string $path, string $source, string $advice): void {
    $root = SourceSandbox::create([$path => $source]);

    try {
        $violations = new NamedRouteUrlGuard(new PhpFileInspector(), $root, 'tests', NamedRouteUrlArchitectureHelpers::sampleRoutes())->violations();

        expect($violations)->toHaveKey($path)
            ->and($violations[$path])->toHaveCount(1)
            ->and($violations[$path][0])->toContain($advice);
    } finally {
        SourceSandbox::remove($root);
    }
})->with('hard-coded urls of named routes');

dataset('urls a test may write out', [
    'a request through route()' => ['tests/Feature/FooTest.php', "<?php\n\$this->get(route('login'))->assertOk();\n\$this->get(route('orders.show', \$order));\n"],
    'a URL no named route has' => ['tests/Feature/FooTest.php', "<?php\n\$this->get('/_probe/first');\n\$this->get('/does-not-exist')->assertNotFound();\n"],
    'a URL of another method than the route' => ['tests/Feature/FooTest.php', "<?php\n\$this->post('/login');\n\$this->get('/logout');\n"],
    'a parameter written out by hand' => ['tests/Feature/FooTest.php', "<?php\n\$this->get('/orders/..%2Fetc%2Fpasswd');\n"],
    'a parameter in the middle of the URL' => ['tests/Feature/FooTest.php', "<?php\n\$this->put('/admin/suppliers/' . \$supplier->getRouteKey() . '/contacts');\n"],
    'a URL on another host' => ['tests/Feature/FooTest.php', "<?php\n\$this->get('http://platform.shop.test/login');\n"],
    'the page a request comes from' => ['tests/Feature/FooTest.php', "<?php\n\$this->from('/login')->post(route('logout'));\n"],
    'a static get and a plain string' => ['tests/Feature/FooTest.php', "<?php\nCache::get('/login');\n\$url = '/login';\n"],
    'a Pest function through route(), to a URL no named route has and from a page' => ['tests/Feature/FooTest.php', "<?php\nuse function Pest\\Laravel\\{from, get, post};\nget(route('login'))->assertOk();\nget('/_probe/first');\npost('/login');\nfrom('/login')->post(route('logout'));\n"],
    'a function named like a request that is not of Pest' => ['tests/Feature/FooTest.php', "<?php\nuse function App\\Support\\get;\nget('/login');\npost('/logout');\n"],
    'a function of the namespace named like a request' => ['tests/Support/Foo.php', "<?php\nnamespace Tests\\Support;\nget('/login');\n"],
    'a URL built from a variable alone' => ['tests/Feature/FooTest.php', "<?php\n\$this->get(\$url);\n\$this->get(\$prefix . '/login');\n"],
]);

it('allows a URL that no named route has, or that is written out on purpose', function (string $path, string $source): void {
    $root = SourceSandbox::create([$path => $source]);

    try {
        expect(new NamedRouteUrlGuard(new PhpFileInspector(), $root, 'tests', NamedRouteUrlArchitectureHelpers::sampleRoutes())->violations())->toBe([]);
    } finally {
        SourceSandbox::remove($root);
    }
})->with('urls a test may write out');

it('reads the named routes of the application', function (): void {
    expect(collect(NamedRouteUrlArchitectureHelpers::namedRoutes())->firstWhere('name', 'notes.show'))->toBe(['methods' => ['GET', 'HEAD'], 'uri' => 'api/v1/notes/{note}', 'name' => 'notes.show']);
});

it('sends every request to a named route through route()', function (): void {
    expect(new NamedRouteUrlGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'tests', NamedRouteUrlArchitectureHelpers::namedRoutes())->violations())->toBe([]);
});
