<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Notes\Actions\SyncNotePermissions;
use App\Modules\Notes\Enums\NotePermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use RuntimeException;

final class DemoUserSeeder extends Seeder
{
    public const string EMAIL = 'demo@example.test';

    public function run(SyncNotePermissions $syncNotePermissions): void
    {
        throw_if(App::isProduction(), RuntimeException::class, 'The demo user is never created in production.');

        $syncNotePermissions->handle();

        $user = User::query()->firstOrCreate(['email' => self::EMAIL], ['name' => 'Demo', 'password' => Str::random(40)]);
        $user->givePermissionTo(array_map(static fn (NotePermission $permission): string => $permission->value, NotePermission::cases()));
    }
}
