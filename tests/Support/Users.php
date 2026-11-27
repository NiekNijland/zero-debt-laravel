<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use BackedEnum;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

final class Users
{
    public static function create(BackedEnum ...$permissions): User
    {
        $created = User::factory()->create();
        $user = User::query()->findOrFail($created->id);

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate((string) $permission->value, 'sanctum'));
        }

        return $user;
    }

    public static function signedIn(BackedEnum ...$permissions): User
    {
        $user = self::create(...$permissions);
        Sanctum::actingAs($user);

        return $user;
    }
}
