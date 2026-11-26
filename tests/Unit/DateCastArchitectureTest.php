<?php

declare(strict_types=1);

use Tests\Support\Architecture\DateCastGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('mutable date casts', [
    'datetime cast' => ["<?php return ['at' => 'datetime'];"],
    'date cast with a format' => ["<?php return ['at' => 'date:Y-m-d'];"],
]);

dataset('date casts that pass', [
    'immutable cast' => ["<?php return ['at' => 'immutable_datetime'];"],
    'immutable date with a format' => ["<?php return ['at' => 'immutable_date:Y-m-d'];"],
    'a rule list that names date' => ["<?php return ['required', 'date'];"],
    'a rule string' => ["<?php return ['at' => 'date|required'];"],
]);

it('detects a mutable date cast', function (string $source): void {
    $violations = new DateCastGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Models')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('cast it as immutable_datetime or immutable_date');
})->with('mutable date casts');

it('accepts the immutable casts and a validation rule', function (string $source): void {
    expect(new DateCastGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Models')->inSource($source))->toBe([]);
})->with('date casts that pass');

it('reads the model folder below a checkout under a dot directory', function (): void {
    $root = SourceSandbox::create(['app/Models/A.php' => "<?php\nreturn ['at' => 'datetime'];\n", 'app/Models/B.php' => "<?php\nreturn ['at' => 'immutable_datetime'];\n"], prefix: '.checkout-');

    try {
        $violations = new DateCastGuard(new PhpFileInspector(), $root, 'app/Models')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Models/A.php: a date is cast as datetime or date');
});

it('casts every model date as immutable', function (): void {
    expect(new DateCastGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Models')->violations())->toBe([]);
});
