<?php

declare(strict_types=1);

namespace App\Modules\Notes\Actions;

use App\Models\Note;
use App\Models\User;
use App\Modules\Notes\Data\NoteInputData;

final readonly class CreateNote
{
    public function handle(User $user, NoteInputData $data): Note
    {
        $note = new Note();
        $note->user_id = $user->id;
        $note->fill([
            'title' => $data->title,
            'body' => $data->body,
            'status' => $data->status,
            'pinned' => $data->pinned,
        ]);
        $note->save();

        return $note->refresh();
    }
}
