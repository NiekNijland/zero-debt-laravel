<?php

declare(strict_types=1);

namespace App\Modules\Notes\Actions;

use App\Modules\Notes\Enums\NotePermission;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final readonly class SyncNotePermissions
{
    public function __construct(private PermissionRegistrar $registrar)
    {
    }

    public function handle(): void
    {
        foreach (NotePermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'sanctum');
        }

        $this->registrar->forgetCachedPermissions();
    }
}
