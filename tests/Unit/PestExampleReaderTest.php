<?php

declare(strict_types=1);

use Tests\Support\Architecture\PestExampleReader;
use Tests\Support\Architecture\TemporarySource;

uses()->group('arch');

dataset('pest test sources', [
    'nothing' => ["<?php\n", ['group' => false, 'failing' => false, 'passing' => false, 'realTree' => false]],
    'a group on the file' => ["<?php\nuses()->group('arch');\n", ['group' => true, 'failing' => false, 'passing' => false, 'realTree' => false]],
    'a group through pest()' => ["<?php\npest()->group('slow')->group('arch');\n", ['group' => true, 'failing' => false, 'passing' => false, 'realTree' => false]],
    'another group' => ["<?php\nuses()->group('slow');\n", ['group' => false, 'failing' => false, 'passing' => false, 'realTree' => false]],
    'a group on every test' => ["<?php\nit('a', function (): void { expect(1)->toBe(1); })->group('arch');\nit('b', function (): void { expect(1)->toBe(1); })->with([1])->group('arch');\n", ['group' => true, 'failing' => true, 'passing' => false, 'realTree' => false]],
    'a group on one test of two' => ["<?php\nit('a', function (): void { expect(1)->toBeTrue(); })->group('arch');\nit('b', function (): void { expect(1)->toBeTrue(); });\n", ['group' => false, 'failing' => false, 'passing' => true, 'realTree' => false]],
    'a message matcher' => ["<?php\nit('a', function (): void { expect(\$m)->toContain('x'); });\n", ['group' => false, 'failing' => true, 'passing' => false, 'realTree' => false]],
    'an exact non-empty result' => ["<?php\nit('a', function (): void { expect(\$m)->toBe(['f.php' => ['x']]); });\n", ['group' => false, 'failing' => true, 'passing' => false, 'realTree' => false]],
    'an empty result' => ["<?php\nit('a', function (): void { expect(\$m)->toBe([]); });\n", ['group' => false, 'failing' => false, 'passing' => true, 'realTree' => false]],
    'an empty result through toBeEmpty' => ["<?php\nit('a', function (): void { expect(\$m)->toBeEmpty(); });\n", ['group' => false, 'failing' => false, 'passing' => true, 'realTree' => false]],
    'an empty result over the own root with an in-source method' => ["<?php\nit('a', function (): void { expect((new G(dirname(__DIR__, 2)))->inSource('x'))->toBe([]); });\n", ['group' => false, 'failing' => false, 'passing' => true, 'realTree' => false]],
    'the real tree' => ["<?php\nit('a', function (): void { expect((new G(dirname(__DIR__, 2)))->violations())->toBe([]); });\n", ['group' => false, 'failing' => false, 'passing' => false, 'realTree' => true]],
    'the real tree as findings' => ["<?php\nit('a', function (): void { expect((new G(dirname(__DIR__, 2)))->findings())->toBeEmpty(); });\n", ['group' => false, 'failing' => false, 'passing' => false, 'realTree' => true]],
    'the real tree through a static detector' => ["<?php\nit('a', function (): void { expect(G::violations(new I(), dirname(__DIR__, 2), 'app'))->toBe([]); });\n", ['group' => false, 'failing' => false, 'passing' => false, 'realTree' => true]],
    'a real tree case that has a dataset' => ["<?php\nit('a', function (): void { expect((new G(dirname(__DIR__, 2)))->violations())->toBe([]); })->with([1]);\n", ['group' => false, 'failing' => false, 'passing' => true, 'realTree' => false]],
    'a root that is not the own file location' => ["<?php\nit('a', function (): void { expect((new G(\$root))->violations())->toBe([]); });\n", ['group' => false, 'failing' => false, 'passing' => true, 'realTree' => false]],
]);

it('reads the parts of a guard test', function (string $source, array $expected): void {
    expect(TemporarySource::with($source, static fn (string $path): array => PestExampleReader::parts($path, ['arch'])))->toBe($expected);
})->with('pest test sources');
