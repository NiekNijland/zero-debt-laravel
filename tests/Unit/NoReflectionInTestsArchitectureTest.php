<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\ReflectionInTestsGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('reflection that reaches into a class', [
    'reflection method' => ["<?php\nnew ReflectionMethod(\$job, 'handle')->invoke(\$job);\n"],
    'imported reflection property' => ["<?php\nuse ReflectionProperty;\n\$p = new ReflectionProperty(Foo::class, 'state');\n"],
    'fully qualified reflection object' => ["<?php\n\$r = new \\ReflectionObject(\$job);\n"],
    'private constant' => ["<?php\nnew ReflectionClassConstant(Foo::class, 'LIMIT');\n"],
    'reflection class kept in a variable' => ["<?php\nit('x', function (): void {\n    \$r = new ReflectionClass(\$job);\n    \$r->getShortName();\n    \$r->getProperty('state');\n});\n"],
    'a method of a reflection class' => ["<?php\nnew ReflectionClass(\$job)->getMethod('run')->invoke(\$job);\n"],
    'default properties' => ["<?php\nnew ReflectionClass(Foo::class)->getDefaultProperties();\n"],
    'every method' => ["<?php\nnew ReflectionClass(Foo::class)->getMethods();\n"],
    'every property' => ["<?php\nnew ReflectionClass(Foo::class)->getProperties();\n"],
    'set accessible' => ["<?php\n\$method->setAccessible(true);\n"],
    'closure bind' => ['<?php
' . Closure::class . '::bind(fn () => $this->secret, $job, Job::class)();
'],
    'bind to' => ["<?php\n(fn () => \$this->secret)->bindTo(\$job, \$job)();\n"],
]);

it('detects reflection that reaches a private member', function (string $source): void {
    $violations = new ReflectionInTestsGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'tests')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('private member through reflection', 'public behavior', 'public seam');
})->with('reflection that reaches into a class');

dataset('reflection that reads the public structure', [
    'attributes' => ["<?php\nnew ReflectionClass(Job::class)->getAttributes(Middleware::class);\n"],
    'an interface check' => ["<?php\nnew ReflectionClass(Job::class)->implementsInterface(ShouldQueue::class);\n"],
    'public methods' => ['<?php
new \ReflectionClass(Job::class)->getMethods(' . ReflectionMethod::class . '::IS_PUBLIC);
'],
    'public properties' => ["<?php\nnew ReflectionClass(Job::class)->getProperties(ReflectionProperty::IS_PUBLIC);\n"],
    'a function reflection' => ["<?php\nnew ReflectionFunction(\$closure)->getParameters();\n"],
    'a method named getMethod on another receiver' => ["<?php\n\$request->getMethod();\n"],
    'a class kept in a variable that only reads structure' => ["<?php\nit('reads a class', function (): void {\n    \$class = new ReflectionClass(Job::class);\n    expect(\$class->getShortName())->toBe('Job')->and(\$class->isFinal())->toBeTrue();\n});\n"],
]);

it('accepts reading the public structure of a class', function (string $source): void {
    expect(new ReflectionInTestsGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'tests')->inSource($source))->toBe([]);
})->with('reflection that reads the public structure');

it('reads a tree under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create([
        'tests/Unit/BadTest.php' => "<?php\n\$p = new ReflectionProperty(Foo::class, 'state');\n",
        'tests/Unit/GoodTest.php' => "<?php\nnew ReflectionClass(Foo::class)->isFinal();\n",
    ], prefix: '.checkout-');

    try {
        $violations = new ReflectionInTestsGuard(new PhpFileInspector(), $root, 'tests')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('tests/Unit/BadTest.php:2: new ReflectionProperty: a test reaches a private member');
});

it('lets no test reach a private member through reflection', function (): void {
    expect(new ReflectionInTestsGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'tests')->violations())->toBe([]);
});
