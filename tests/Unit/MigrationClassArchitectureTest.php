<?php

declare(strict_types=1);

use App\Modules\Notes\Enums\NotePermission;
use Tests\Support\Architecture\MigrationClassGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('migrations that name a class', [
    'the live permission enum' => ["<?php\nuse App\\Modules\\Notes\\Enums\\NotePermission;\n", 'the live permission or role class'],
    'a live permission case' => ["<?php\n\$a = NotePermission::VIEW;\n", 'write the name as a string'],
    'an application class' => ["<?php\nuse App\\Models\\Order;\n", 'names an application class'],
    'an application class in a call' => ["<?php\n\$a = \\App\\Support\\Json::decode(\$x);\n", 'names an application class'],
]);

it('detects a migration that names an application class', function (string $source, string $words): void {
    $messages = new MigrationClassGuard(dirname(__DIR__, 2), 'database/migrations', [NotePermission::class], 'App\\')->inSource($source);

    expect($messages)->not->toBe([])
        ->and($messages[0])->toContain($words);
})->with('migrations that name a class');

dataset('migrations that name no class', [
    'a plain migration' => ["<?php\nuse Illuminate\\Database\\Migrations\\Migration;\nreturn new class extends Migration {};\n"],
    'a word that ends like the namespace' => ["<?php\n\$a = 'MyApp\\\\Models';\n"],
    'a role as a string' => ["<?php\nDB::table('roles')->insert(['name' => 'admin']);\n"],
    'a class that ends in the name of the enum' => ["<?php\n\$a = OtherNotePermission::class;\n"],
]);

it('accepts a migration that names no application class', function (string $source): void {
    expect(new MigrationClassGuard(dirname(__DIR__, 2), 'database/migrations', [NotePermission::class], 'App\\')->inSource($source))->toBe([]);
})->with('migrations that name no class');

it('names the file of a migration that names a class, in a checkout under a dot directory, and fails on an empty folder', function (): void {
    $root = SourceSandbox::create([
        'database/migrations/2024_01_01_000000_a.php' => "<?php\nuse App\\Models\\Order;\n",
        'database/migrations/2024_01_02_000000_b.php' => "<?php\n",
        'empty/.gitkeep' => '',
    ], prefix: '.checkout-');

    try {
        $violations = new MigrationClassGuard($root, 'database/migrations', [NotePermission::class], 'App\\')->violations();
        $empty = new MigrationClassGuard($root, 'empty', [NotePermission::class], 'App\\')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('database/migrations/2024_01_01_000000_a.php: a migration names an application class')
        ->and($empty)->toHaveCount(1)
        ->and($empty[0])->toContain('no migration was read');
});

it('keeps application classes out of the migrations', function (): void {
    expect(new MigrationClassGuard(dirname(__DIR__, 2), 'database/migrations', [NotePermission::class], 'App\\')->violations())->toBe([]);
});
