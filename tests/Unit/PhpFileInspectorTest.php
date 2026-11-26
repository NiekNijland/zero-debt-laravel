<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Redirect;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;
use Tests\Support\Unit\PhpFileInspectorHelpers;

uses()->group('arch');

it('lists the php files below a directory in a stable order and nothing else', function (): void {
    $root = SourceSandbox::create(['b.php' => '<?php', 'a/c.php' => '<?php', 'a/readme.md' => 'text', 'a/d.txt' => 'text']);

    try {
        $files = array_map(static fn (string $file): string => mb_substr($file, mb_strlen($root) + 1), new PhpFileInspector()->phpFiles($root));

        expect($files)->toBe(['a/c.php', 'b.php'])
            ->and(new PhpFileInspector()->phpFiles($root . '/missing', mayBeAbsent: true))->toBe([]);
    } finally {
        SourceSandbox::remove($root);
    }
});

it('fails on a directory that does not exist, so a guard over a renamed folder cannot pass without reading a file', function (): void {
    $root = SourceSandbox::create(['b.php' => '<?php']);

    try {
        expect(static fn (): array => new PhpFileInspector()->phpFiles($root . '/missing'))->toThrow(InvalidArgumentException::class, 'does not exist');
    } finally {
        SourceSandbox::remove($root);
    }
});

it('describes every kind of call with its callee, receiver and line', function (): void {
    $source = "<?php\nnamespace App\\X;\n\nuse Illuminate\\Support\\Facades\\Redirect as R;\n\n\$a = \\app(Foo::class);\nR::route('home');\nredirect()\n    ->back();\n\$this->container->make(Foo::class);\n\$x?->authorize('a');\nabort_if(\$a, 403, 'x');\n";
    $inspector = new PhpFileInspector();

    expect(PhpFileInspectorHelpers::summarisedCalls($source))->toBe([
        'function|6|\app|-',
        'static|7|R|-',
        'function|8|redirect|-',
        'method|9|back|redirect()',
        'method|10|make|->container',
        'nullsafe|11|authorize|$x',
        'function|12|abort_if|-',
    ])
        ->and($inspector->calls($source)[1]['class'])->toBe(Redirect::class)
        ->and($inspector->calls($source)[3]['classLine'])->toBe(8)
        ->and($inspector->calls($source)[6]['arguments'])->toBe(['$a', '403', "'x'"]);
});

it('does not see a call that only stands in a comment or a string', function (): void {
    expect(PhpFileInspectorHelpers::summarisedCalls("<?php\n// app()\n\$a = 'resolve(Foo::class)';\n"))->toBe([]);
});

dataset('role name sources', [
    'a method call' => ['<?php $user->hasRole(\'admin\');', true],
    'a nullsafe method call' => ['<?php $user?->hasAnyRole([\'admin\']);', true],
    'a middleware string' => ["<?php Route::middleware('role:admin');", true],
    'a middleware string after another' => ["<?php Route::middleware('can:x|role_or_permission:y');", true],
    'only a comment' => ['<?php ' . '/' . '/ $user->hasRole(\'admin\') and \'role:admin\'', false],
    'a permission check' => ['<?php $user->can(\'x\'); Route::middleware(\'permission:x\');', false],
    'an interpolated role' => ['<?php Route::middleware("role:{$role}");', true],
    'a heredoc role' => ["<?php \$x = <<<TXT\nrole:admin\nTXT;", true],
]);

it('sees a role name check in the ways the old text scan did', function (string $source, bool $expected): void {
    expect(PhpFileInspectorHelpers::checksRoleName($source))->toBe($expected);
})->with('role name sources');

dataset('date cast sources', [
    'datetime cast' => ["<?php return ['at' => 'datetime'];", true],
    'date cast with a format' => ["<?php return ['at' => 'date:Y-m-d'];", true],
    'immutable cast' => ["<?php return ['at' => 'immutable_datetime'];", false],
    'a rule list that names date' => ["<?php return ['required', 'date'];", false],
    'a rule string' => ["<?php return ['at' => 'date|required'];", false],
]);

it('sees a mutable date cast in the ways the old text scan did', function (string $source, bool $expected): void {
    expect(PhpFileInspectorHelpers::castsDateMutably($source))->toBe($expected);
})->with('date cast sources');
