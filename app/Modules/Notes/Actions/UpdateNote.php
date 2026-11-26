<?php

declare(strict_types=1);

namespace App\Modules\Notes\Actions;

use App\Models\Note;
use App\Modules\Notes\Data\NoteInputData;

final readonly class UpdateNote
{
    public function handle(Note $note, NoteInputData $data): Note
    {
        $note->update([
            'title' => $data->title,
            'body' => $data->body,
            'status' => $data->status,
            'pinned' => $data->pinned,
        ]);

        return $note->refresh();
    }
}
