<?php

declare(strict_types=1);

namespace App\Modules\Notes\Enums;

enum NotePermission: string
{
    case VIEW = 'notes.view';
    case CREATE = 'notes.create';
    case UPDATE = 'notes.update';
    case DELETE = 'notes.delete';
}
