<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Notes\Actions\SyncNotePermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

final class DatabaseSeeder extends Seeder
{
    public function run(SyncNotePermissions $syncNotePermissions): void
    {
        $syncNotePermissions->handle();

        if (! App::isProduction()) {
            $this->call(DemoUserSeeder::class);
        }
    }
}
