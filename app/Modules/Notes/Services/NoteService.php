<?php

declare(strict_types=1);

namespace App\Modules\Notes\Services;

use App\Models\Note;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class NoteService
{
    /**
     * @return LengthAwarePaginator<int, Note>
     */
    public function paginateFor(User $user, int $perPage): LengthAwarePaginator
    {
        return Note::query()
            ->where('user_id', $user->id)
            ->orderByDesc('pinned')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
