<?php

declare(strict_types=1);

use App\Models\Note;
use App\Models\User;
use App\Modules\Notes\Enums\NoteStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\LazyLoadingViolationException;

it('casts its columns', function (): void {
    $created = Note::factory()->create();
    $note = Note::query()->findOrFail($created->id);

    expect($note->status)->toBe(NoteStatus::DRAFT)
        ->and($note->pinned)->toBeFalse()
        ->and($note->created_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('belongs to its user', function (): void {
    $user = User::factory()->create();
    $note = Note::factory()->for($user)->create();

    expect(Note::query()->with('user')->findOrFail($note->id)->user->id)->toBe($user->id);
});

it('refuses a lazy load', function (): void {
    Note::factory()->count(2)->create();
    $note = Note::query()->get()->firstOrFail();

    expect(fn (): User => $note->user)->toThrow(LazyLoadingViolationException::class);
});

it('keeps the owner out of mass assignment', function (): void {
    $note = new Note();

    expect(fn (): Note => $note->fill(['user_id' => 5]))->toThrow(MassAssignmentException::class);
});
