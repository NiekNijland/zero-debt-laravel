<?php

declare(strict_types=1);

use App\Modules\Notes\Enums\NotePermission;
use Database\Seeders\DatabaseSeeder;
use Spatie\Permission\Models\Permission;

it('creates the permissions of the notes', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(Permission::query()->count())->toBe(count(NotePermission::cases()));
});
