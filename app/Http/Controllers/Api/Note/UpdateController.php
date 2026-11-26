<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Note;

use App\Http\Requests\Api\Note\UpdateRequest;
use App\Models\Note;
use App\Modules\Notes\Actions\UpdateNote;
use App\Modules\Notes\Data\NoteData;

final readonly class UpdateController
{
    public function __invoke(UpdateRequest $request, Note $note, UpdateNote $updateNote): NoteData
    {
        return NoteData::fromModel($updateNote->handle($note, $request->toData()));
    }
}
