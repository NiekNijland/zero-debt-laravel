<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use App\Modules\Notes\Actions\SyncNotePermissions;
use App\Modules\Notes\Enums\NotePermission;
use Laravel\Sanctum\Sanctum;

final class Users
{
    public static function create(NotePermission ...$permissions): User
    {
        resolve(SyncNotePermissions::class)->handle();

        $created = User::factory()->create();
        $user = User::query()->findOrFail($created->id);
        $user->givePermissionTo(array_map(static fn (NotePermission $permission): string => $permission->value, $permissions));

        return $user;
    }

    public static function signedIn(NotePermission ...$permissions): User
    {
        $user = self::create(...$permissions);
        Sanctum::actingAs($user);

        return $user;
    }
}
