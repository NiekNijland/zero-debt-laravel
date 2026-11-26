<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Notes\Actions\SyncNotePermissions;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(SyncNotePermissions $syncNotePermissions): void
    {
        $syncNotePermissions->handle();
    }
}
