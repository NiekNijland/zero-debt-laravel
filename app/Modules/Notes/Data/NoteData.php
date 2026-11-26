<?php

declare(strict_types=1);

namespace App\Modules\Notes\Data;

use App\Models\Note;
use App\Modules\Notes\Enums\NoteStatus;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
final class NoteData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $body,
        public readonly NoteStatus $status,
        public readonly bool $pinned,
        public readonly CarbonImmutable $createdAt,
        public readonly CarbonImmutable $updatedAt,
    ) {
    }

    public static function fromModel(Note $note): self
    {
        return new self(
            $note->id,
            $note->title,
            $note->body,
            $note->status,
            $note->pinned,
            $note->created_at,
            $note->updated_at,
        );
    }
}
