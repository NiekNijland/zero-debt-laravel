<?php

declare(strict_types=1);

use Tests\Support\Architecture\ModelMagicStaticCallGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

it('detects a magic static call on a model and accepts the near misses', function (): void {
    $guard = new ModelMagicStaticCallGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app']);
    $source = '<?php namespace App\Models; use App\Models\Note as Memo; final class Probe extends User { public function a() { return [Memo::where("a", 1), User::find(1), parent::where("a", 1), self::query()->where("a", 1), Note::query()->where("a", 1), Note::withTrashed(), Note::factory(), parent::boot()]; } }';
    $violations = $guard->inSource($source);

    expect($violations)->toHaveCount(4)
        ->and($violations[0])->toContain('where() is a magic static call on a model: start the query at Note::query()');
});

it('accepts a query that starts at query() and a static call on a class that is no model', function (): void {
    $source = '<?php namespace App\\Models; final class Probe extends User { public function a() { return [Note::query()->where("a", 1), self::query(), Collection::make([]), Str::of("a")]; } }';

    expect(new ModelMagicStaticCallGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'])->inSource($source))->toBe([]);
});

it('starts every query at Model::query()', function (): void {
    expect(new ModelMagicStaticCallGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'config', 'database', 'routes'])->violations())->toBe([]);
});

it('reads the folders below a checkout under a dot directory and names the file relative to the root', function (): void {
    $source = "<?php\nuse App\\Models\\Note;\nNote::where('title', 'a');\n";
    $root = SourceSandbox::create(['app/Bad.php' => $source, 'app/Good.php' => "<?php\nuse App\\Models\\Note;\nNote::query();\n"], prefix: '.checkout-');

    try {
        $violations = new ModelMagicStaticCallGuard(new PhpFileInspector(), $root, ['app'])->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Bad.php: line 3: where() is a magic static call on a model');
});
