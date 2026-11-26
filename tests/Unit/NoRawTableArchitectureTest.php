<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\RawTableGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('ways to reach a table or a statement without a model', [
    'the facade, imported' => ["<?php\nuse Illuminate\\Support\\Facades\\DB;\nDB::table('category_product')->pluck('product_id');", 3],
    'the facade, fully qualified' => ["<?php\n\\DB::select('select 1');", 2],
    'the facade, aliased' => ["<?php\nuse Illuminate\\Support\\Facades\\DB as Database;\nDatabase::table('orders')->count();", 3],
    'four statements of the facade' => ["<?php\nuse Illuminate\\Support\\Facades\\DB;\nDB::statement('vacuum'); DB::raw('1'); DB::scalar('select 1'); DB::unprepared('x');", 3],
    'a named connection' => ["<?php\nuse Illuminate\\Support\\Facades\\DB;\nDB::connection('pgsql')->table('failed_jobs')->count();", 3],
]);

it('recognizes every way to reach a table or a statement without a model', function (string $source, int $line): void {
    $violations = new RawTableGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'], [])->inSource($source);

    expect($violations)->not->toBe([])
        ->and($violations[0])->toStartWith('line ' . $line . ':')
        ->and($violations[0])->toContain('raw table or statement', 'Eloquent model', 'migration');
})->with('ways to reach a table or a statement without a model');

dataset('calls that stay on the database facade or a model', [
    'transaction and hooks' => ["<?php\nuse Illuminate\\Support\\Facades\\DB;\nDB::transaction(fn () => 1); DB::afterCommit(fn () => 1); DB::query()->fromSub(\$q, 'sub'); DB::connection()->getPdo();"],
    'a console table and a model query' => ["<?php\n\$this->table(['Field'], \$rows); CategoryProduct::query()->toBase()->select('product_id');"],
    'a class named DB in another namespace' => ["<?php\nnamespace App\\Support;\nuse App\\Support\\DB;\nDB::table('x');"],
]);

it('accepts the facade calls that are not a raw table and the same method names on other receivers', function (string $source): void {
    expect(new RawTableGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'], [])->inSource($source))->toBe([]);
})->with('calls that stay on the database facade or a model');

it('reads a tree under a dot directory, skips the allowed file and names the rest relative to the root', function (): void {
    $raw = "<?php\nuse Illuminate\\Support\\Facades\\DB;\nDB::select('select 1');\n";
    $root = SourceSandbox::create([
        'app/Reports.php' => $raw,
        'app/Support/AdvisoryLock.php' => $raw,
        'routes/web.php' => "<?php\nreturn [];\n",
    ], prefix: '.checkout-');

    try {
        $violations = new RawTableGuard(new PhpFileInspector(), $root, ['app', 'routes', 'config'], ['app/Support/AdvisoryLock.php'])->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Reports.php:3: a raw table or statement without a model');
});

it('reaches the database through models: raw tables and raw SQL live in database/ and nowhere else', function (): void {
    $guard = new RawTableGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'routes', 'config'], []);

    expect($guard->violations())->toBe([]);
});
