<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Note;

use App\Http\Requests\Api\Note\StoreRequest;
use App\Modules\Notes\Actions\CreateNote;
use App\Modules\Notes\Data\NoteData;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class StoreController
{
    public function __invoke(StoreRequest $request, CreateNote $createNote): JsonResponse
    {
        return new JsonResponse(NoteData::fromModel($createNote->handle($request->actor(), $request->toData())), Response::HTTP_CREATED);
    }
}
