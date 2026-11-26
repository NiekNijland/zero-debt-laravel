<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Note;

use App\Models\Note;
use App\Modules\Notes\Actions\DeleteNote;
use Illuminate\Http\Response;

final readonly class DestroyController
{
    public function __invoke(Note $note, DeleteNote $deleteNote): Response
    {
        $deleteNote->handle($note);

        return response()->noContent();
    }
}
