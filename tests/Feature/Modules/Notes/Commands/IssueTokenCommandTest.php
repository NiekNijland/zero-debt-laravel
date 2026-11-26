<?php

declare(strict_types=1);

use App\Modules\Notes\Commands\IssueTokenCommand;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\Users;

it('prints a token that is stored for the user', function (): void {
    $user = Users::create();

    $status = Artisan::call('notes:issue-token', ['email' => $user->email, '--name' => 'smoke']);

    expect($status)->toBe(0)
        ->and(mb_trim(Artisan::output()))->toMatch('/^\d+\|\w+$/')
        ->and($user->tokens()->where('name', 'smoke')->count())->toBe(1);
});

it('fails for an email that no user has', function (): void {
    $status = Artisan::call('notes:issue-token', ['email' => 'nobody@example.test']);

    expect($status)->toBe(1)
        ->and(Artisan::output())->toContain('No user has that email.');
});

it('is registered with the console', function (): void {
    expect(Artisan::all())->toHaveKey('notes:issue-token')
        ->and(Artisan::all()['notes:issue-token'])->toBeInstanceOf(IssueTokenCommand::class);
});
