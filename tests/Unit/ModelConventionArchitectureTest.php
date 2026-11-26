<?php

declare(strict_types=1);

use Tests\Support\Architecture\EloquentBuilderGuard;
use Tests\Support\Architecture\ModelConventionGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('model conventions that fail', [
    'a casts property' => ["<?php\nclass A { protected \$casts = []; }\n", 'a casts property: declare the casts in the casts() method'],
    'a guarded property' => ["<?php\nclass A { protected \$guarded = []; }\n", 'list what is fillable'],
    'a get accessor' => ["<?php\nclass A { public function getNameAttribute() {} }\n", 'a method that returns an Attribute'],
    'a set mutator' => ["<?php\nclass A { public function setNameAttribute(\$v) {} }\n", 'a method that returns an Attribute'],
    'an Attribute constructor' => ["<?php\nclass A { public function name() { return new Attribute(get: fn () => 1); } }\n", 'Attribute::make'],
    'a boot method' => ["<?php\nclass A { protected static function boot() { parent::boot(); } }\n", 'booted()'],
]);

it('detects a model that declares casts, fillable, accessors or boot hooks the old way', function (string $source, string $words): void {
    $violations = new ModelConventionGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Models')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($words);
})->with('model conventions that fail');

it('accepts the casts method, a fillable list, the booted hook and a method that only starts like a boot method', function (): void {
    $source = "<?php\nclass A { protected \$fillable = []; protected function casts(): array { return []; } protected static function booted(): void {} public function getName() {} public static function bootstrap() {} }\n";

    expect(new ModelConventionGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Models')->inSource($source))->toBe([]);
});

it('reads the model folder below a checkout under a dot directory for the conventions and the builders', function (): void {
    $root = SourceSandbox::create(['app/Models/A.php' => "<?php\nclass A { protected \$casts = []; public function newEloquentBuilder(\$q) {} }\n"], prefix: '.checkout-');

    try {
        $conventions = new ModelConventionGuard(new PhpFileInspector(), $root, 'app/Models')->violations();
        $builders = new EloquentBuilderGuard(new PhpFileInspector(), $root, 'app', 'Spatie\QueryBuilder')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($conventions)->toBe(['app/Models/A.php: a casts property: declare the casts in the casts() method'])
        ->and($builders)->toHaveCount(1)
        ->and($builders[0])->toStartWith('app/Models/A.php: a custom Eloquent builder');
});

it('declares casts, fillable, accessors and boot hooks one way in every model', function (): void {
    expect(new ModelConventionGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Models')->violations())->toBe([]);
});

dataset('builders and query-string packages', [
    'a custom builder method' => ['public function newEloquentBuilder($query)'],
    'the query-string package' => ['use Spatie\QueryBuilder\QueryBuilder;'],
    'the builder attribute' => ['#[UseEloquentBuilder(XBuilder::class)]'],
]);

it('detects a custom Eloquent builder and the query-string package', function (string $source): void {
    $violations = new EloquentBuilderGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'Spatie\QueryBuilder')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('write the query as a scope of the model');
})->with('builders and query-string packages');

it('accepts a scope and a package with a similar name', function (): void {
    $guard = new EloquentBuilderGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'Spatie\QueryBuilder');

    expect($guard->inSource('public function scopeActive($query) {} use Spatie\QueryBuilderHelper\X;'))->toBe([]);
});

it('writes queries as scopes of the model, with no custom builder and no query-string builder', function (): void {
    expect(new EloquentBuilderGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'Spatie\QueryBuilder')->violations())->toBe([]);
});
