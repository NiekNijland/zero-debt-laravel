<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\RoleNameGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('role names that are checked', [
    'a role method' => ["<?php\n\$user->hasRole('admin');\n", 'check a permission with can()'],
    'a nullsafe role method' => ["<?php\n\$user?->hasAnyRole(['admin']);\n", 'check a permission with can()'],
    'all roles' => ["<?php\n\$user->hasAllRoles(['a', 'b']);\n", 'check a permission with can()'],
    'exact roles' => ["<?php\n\$user->hasExactRoles(['a']);\n", 'check a permission with can()'],
    'a role middleware string' => ["<?php\nRoute::get('/x', X::class)->middleware('role:admin');\n", 'a permission middleware'],
    'a role or permission string in a list' => ["<?php\n\$m = ['auth|role_or_permission:admin'];\n", 'a permission middleware'],
    'a role string after a pipe' => ["<?php\n\$m = ['auth:sanctum|role:admin'];\n", 'a permission middleware'],
]);

it('detects a role name that is checked in application code', function (string $source, string $words): void {
    $violations = new RoleNameGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'])->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($words);
})->with('role names that are checked');

dataset('role checks and near misses that pass', [
    'a permission check' => ["<?php\n\$user->can('notes.view');\n"],
    'a permission middleware' => ["<?php\nRoute::get('/x', X::class)->middleware('permission:notes.view');\n"],
    'a can middleware' => ["<?php\nRoute::get('/x', X::class)->middleware('can:view,note');\n"],
    'a method that only starts like a role check' => ["<?php\n\$user->hasRoleLabel();\n"],
    'a text that mentions the word role' => ["<?php\n\$a = 'the role: admin is shown';\n"],
    'a permission check by the spatie method' => ["<?php\n\$user->hasPermissionTo('notes.view');\n"],
]);

it('accepts a permission check and the near misses of a role check', function (string $source): void {
    expect(new RoleNameGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'])->inSource($source))->toBe([]);
})->with('role checks and near misses that pass');

it('reads the folders below a checkout under a dot directory', function (): void {
    $root = SourceSandbox::create([
        'app/Bad.php' => "<?php\n\$user->hasRole('admin');\n",
        'routes/api.php' => "<?php\nRoute::get('/x', X::class)->middleware('role:admin');\n",
        'app/Good.php' => "<?php\n\$user->can('a');\n",
    ], prefix: '.checkout-');

    try {
        $violations = new RoleNameGuard(new PhpFileInspector(), $root, ['app', 'routes'])->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(2)
        ->and($violations[0])->toStartWith('app/Bad.php: a role name is checked')
        ->and($violations[1])->toStartWith('routes/api.php: a role name is checked');
});

it('never checks a role name in application code', function (): void {
    expect(new RoleNameGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'routes', 'bootstrap', 'config'])->violations())->toBe([]);
});
