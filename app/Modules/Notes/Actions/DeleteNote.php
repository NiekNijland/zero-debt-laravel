<?php

declare(strict_types=1);

namespace App\Modules\Notes\Actions;

use App\Models\Note;

final readonly class DeleteNote
{
    public function handle(Note $note): void
    {
        $note->delete();
    }
}
