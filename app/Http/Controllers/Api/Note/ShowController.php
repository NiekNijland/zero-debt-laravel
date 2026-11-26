<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Note;

use App\Models\Note;
use App\Modules\Notes\Data\NoteData;

final readonly class ShowController
{
    public function __invoke(Note $note): NoteData
    {
        return NoteData::fromModel($note);
    }
}
