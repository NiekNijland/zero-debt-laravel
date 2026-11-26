<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Note;

use App\Http\Requests\Api\Note\StoreRequest;
use App\Modules\Notes\Actions\CreateNote;
use App\Modules\Notes\Data\NoteData;

final readonly class StoreController
{
    public function __invoke(StoreRequest $request, CreateNote $createNote): NoteData
    {
        return NoteData::fromModel($createNote->handle($request->actor(), $request->toData()));
    }
}
