<?php

declare(strict_types=1);

namespace App\Modules\Notes\Enums;

enum NoteStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
}
