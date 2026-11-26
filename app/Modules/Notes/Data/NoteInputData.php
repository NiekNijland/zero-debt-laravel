<?php

declare(strict_types=1);

namespace App\Modules\Notes\Data;

use App\Modules\Notes\Enums\NoteStatus;
use Spatie\LaravelData\Data;

final class NoteInputData extends Data
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly NoteStatus $status,
        public readonly bool $pinned,
    ) {
    }
}
