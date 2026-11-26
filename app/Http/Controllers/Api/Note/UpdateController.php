<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Note;

use App\Http\Requests\Api\Note\UpdateRequest;
use App\Models\Note;
use App\Modules\Notes\Actions\UpdateNote;
use App\Modules\Notes\Data\NoteData;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class UpdateController
{
    public function __invoke(UpdateRequest $request, Note $note, UpdateNote $updateNote): JsonResponse
    {
        return new JsonResponse(NoteData::fromModel($updateNote->handle($note, $request->toData())), Response::HTTP_OK);
    }
}
