<?php

declare(strict_types=1);

use App\Modules\Notes\Actions\SyncNotePermissions;
use App\Modules\Notes\Enums\NotePermission;
use Spatie\Permission\Models\Permission;

it('creates every permission once', function (): void {
    $action = resolve(SyncNotePermissions::class);

    $action->handle();
    $action->handle();

    expect(Permission::query()->count())->toBe(count(NotePermission::cases()))
        ->and(Permission::query()->where('guard_name', 'sanctum')->count())->toBe(count(NotePermission::cases()));
});
