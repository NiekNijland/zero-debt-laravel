<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Support\Architecture\PhpCallReader;
use Tests\Support\Architecture\TemporarySource;

uses()->group('arch');

it('reads the static calls of a file with self, static and parent resolved to their classes', function (): void {
    $source = "<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\DB as Database;\nfinal class A extends B { public function f(): void { Database::table('x'); self::g(); static::h(); parent::i(); \\Foo\\Bar::j(); \$x::k(); } }\n";
    $calls = TemporarySource::with($source, PhpCallReader::staticCalls(...));

    expect(array_map(static fn (array $call): string => $call['class'] . '::' . $call['method'], $calls))->toBe([
        DB::class . '::table',
        'App\A::g',
        'App\A::h',
        'App\B::i',
        'Foo\Bar::j',
    ]);
});

it('reads the controllers named by class of the routes that are not a plain get, and not an array callable', function (): void {
    $source = "<?php\nuse Illuminate\\Support\\Facades\\Route;\nRoute::get('a', A::class);\nRoute::post('b', B::class);\nRoute::match(['get', 'head'], 'c', C::class);\nRoute::match(['put'], 'd', D::class);\nRoute::delete('e', [E::class, 'f']);\nRoute::any('g', G::class);\n";
    $found = TemporarySource::with($source, PhpCallReader::nonGetRouteControllers(...));

    expect(array_map(static fn (array $route): string => $route['verb'] . ' ' . $route['controller'], $found))->toBe(['post B', 'match D', 'any G']);
});

it('reads every named instantiation, and an exception that throw_if builds from its class', function (): void {
    $source = "<?php\nnamespace App;\nuse Symfony\\Component\\HttpKernel\\Exception\\HttpException as Stop;\n\$a = new Stop(403, 'no');\n\$b = new \\RuntimeException('x');\n\$c = new \$class();\nthrow_if(\$d, Stop::class, 'late');\nthrow_unless(\$e, \$f);\n";
    $found = PhpCallReader::instantiations($source);

    expect(array_map(static fn (array $new): string => $new['class'] . '|' . $new['written'] . '|' . implode(',', $new['arguments']), $found))->toBe([
        'Symfony\Component\HttpKernel\Exception\HttpException|Stop|403,\'no\'',
        'RuntimeException|\RuntimeException|\'x\'',
        'Symfony\Component\HttpKernel\Exception\HttpException|Stop|\'late\'',
    ]);
});
