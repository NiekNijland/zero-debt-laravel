<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Note;

use App\Http\Requests\Api\Note\IndexRequest;
use App\Modules\Notes\Data\NoteData;
use App\Modules\Notes\Services\NoteService;
use Spatie\LaravelData\PaginatedDataCollection;

final readonly class IndexController
{
    /**
     * @return PaginatedDataCollection<int, NoteData>
     */
    public function __invoke(IndexRequest $request, NoteService $notes): PaginatedDataCollection
    {
        return NoteData::collect(
            $notes->paginateFor($request->actor(), $request->perPage())->through(NoteData::fromModel(...)),
            PaginatedDataCollection::class,
        );
    }
}
