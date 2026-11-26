<?php

declare(strict_types=1);

use Tests\Support\Architecture\DocumentedPathGuard;
use Tests\Support\Narrow;
use Tests\Support\RecursiveFiles;
use Tests\Support\SourceSandbox;

uses()->group('arch');

$roots = ['.github', 'app', 'bootstrap', 'config', 'database', 'docs', 'lang', 'routes', 'scripts', 'tests'];
$guides = ['AGENTS.md', 'docs/review-rubric.md'];

dataset('guides that name a path that is gone', [
    'a file from the repository root' => [
        ['AGENTS.md' => "# Guide\n\nCopy `app/Support/Like.php`, not `app/Support/Search.php`.\n", 'app/Support/Like.php' => "<?php\n"],
        'AGENTS.md:3: `app/Support/Search.php` does not exist',
    ],
    'a folder in a nested guide' => [
        ['tests/AGENTS.md' => "| Helpers | `tests/Support/Feature/` | `tests/Support/Browser/` |\n", 'tests/Support/Feature/Helpers.php' => "<?php\n"],
        'tests/AGENTS.md:1: `tests/Support/Browser/` does not exist',
    ],
    'a relative file in a guide' => [
        ['AGENTS.md' => "Use `Notes/row-action.php` or\n`Notes/row-menu.php`.\n", 'app/Modules/Notes/row-action.php' => ''],
        'AGENTS.md:2: no file ends in `Notes/row-menu.php`',
    ],
    'the review rubric' => [
        ['docs/review-rubric.md' => "The list is `scripts/ci-ratchets.sh` (see `tests/Unit/GoneTest.php`).\n", 'scripts/ci-ratchets.sh' => ''],
        'docs/review-rubric.md:1: `tests/Unit/GoneTest.php` does not exist',
    ],
]);

it('detects a guide that names a path that does not exist', function (array $files, string $reason) use ($roots, $guides): void {
    $root = SourceSandbox::create(Narrow::stringMap($files));

    try {
        $violations = new DocumentedPathGuard($root, $roots, $guides, 'AGENTS.md')->violations();

        expect($violations)->toHaveCount(1)
            ->and($violations[0])->toContain('[documented-path] ' . $reason)
            ->and($violations[0])->toContain('placeholder such as <Name>');
    } finally {
        SourceSandbox::remove($root);
    }
})->with('guides that name a path that is gone');

it('accepts paths that exist, placeholders, wildcards and text that is not a path', function () use ($roots, $guides): void {
    $root = SourceSandbox::create([
        'AGENTS.md' => implode("\n", [
            'Copy `app/Support/Like.php` in `app/Support` or `app/Support/`; the page is `Pages/Order/Show.php`.',
            'A module is `app/Modules/<Domain>/Actions`, a plan `docs/plans/example-*.md`, areas `app/Http/Controllers/{Admin,Landlord}`.',
            "Run `composer pint`, read `vendor/package/README.md`, write `storage/app/x.json`, call `Facade::render()` and `Route::get('/a/b')`.",
            'Values read `and/or`, `text/plain`, `resources/js/...` and `phpstan.neon`.',
        ]),
        'app/Support/Like.php' => "<?php\n",
        'app/Pages/Order/Show.php' => '',
        'vendor/package/composer.json' => '{}',
        'storage/app/.gitignore' => '',
        'README.md' => 'Not a guide: `app/Gone.php`.',
        'docs/notes.md' => 'Not a guide: `app/Gone.php`.',
    ]);

    try {
        expect(new DocumentedPathGuard($root, $roots, $guides, 'AGENTS.md')->violations())->toBe([]);
    } finally {
        SourceSandbox::remove($root);
    }
});

it('names the guide relative to the root when the checkout sits under a dot directory', function () use ($roots, $guides): void {
    $root = SourceSandbox::create(['AGENTS.md' => "Copy `app/Support/Gone.php`.\n", 'app/Support/Like.php' => "<?php\n"], prefix: '.checkout-');

    try {
        $violations = new DocumentedPathGuard($root, $roots, $guides, 'AGENTS.md')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('[documented-path] AGENTS.md:1: `app/Support/Gone.php` does not exist');
});

it('names only paths that exist in the guides of the repository', function () use ($roots): void {
    $root = dirname(__DIR__, 2);
    $guides = ['AGENTS.md'];

    foreach (RecursiveFiles::in($root . '/docs') as $file) {
        $guides[] = mb_substr($file->getPathname(), mb_strlen($root) + 1);
    }

    $guard = new DocumentedPathGuard($root, $roots, $guides, 'AGENTS.md');

    expect($guard->pathsInGuide('AGENTS.md'))->not->toBe([])
        ->and($guard->violations())->toBe([])
        ->and(fn (): array => $guard->pathsInGuide('RENAMED.md'))->toThrow(RuntimeException::class, 'does not exist');
});
