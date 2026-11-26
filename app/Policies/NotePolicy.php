<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Note;
use App\Models\User;
use App\Modules\Notes\Enums\NotePermission;

final readonly class NotePolicy
{
    public function view(User $user, Note $note): bool
    {
        return $this->allows($user, $note, NotePermission::VIEW);
    }

    public function update(User $user, Note $note): bool
    {
        return $this->allows($user, $note, NotePermission::UPDATE);
    }

    public function delete(User $user, Note $note): bool
    {
        return $this->allows($user, $note, NotePermission::DELETE);
    }

    private function allows(User $user, Note $note, NotePermission $permission): bool
    {
        return $user->id === $note->user_id && $user->checkPermissionTo($permission->value, 'sanctum');
    }
}
