<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Notes\Enums\NotePermission;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUserSeeder;
use Spatie\Permission\Models\Permission;

it('creates the permissions of the notes and a demo user who holds all of them', function (): void {
    $this->seed(DatabaseSeeder::class);

    $demo = User::query()->where('email', DemoUserSeeder::EMAIL)->firstOrFail();

    expect(Permission::query()->count())->toBe(count(NotePermission::cases()))
        ->and($demo->getAllPermissions()->count())->toBe(count(NotePermission::cases()));
});

it('can be run twice', function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe(1);
});

it('creates the permissions and no user in production', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'production');

    resolve(DatabaseSeeder::class)->setContainer($this->app)->__invoke();

    expect(Permission::query()->count())->toBe(count(NotePermission::cases()))
        ->and(User::query()->count())->toBe(0);
});

it('refuses to create the demo user in production', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'production');

    resolve(DemoUserSeeder::class)->setContainer($this->app)->__invoke();
})->throws(RuntimeException::class, 'never created in production');
