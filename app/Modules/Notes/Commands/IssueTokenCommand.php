<?php

declare(strict_types=1);

namespace App\Modules\Notes\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

#[Description('Print a new API token of a user')]
#[Signature('notes:issue-token {email : The email of the user} {--name=cli : The name of the token}')]
final class IssueTokenCommand extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', Arr::string($this->arguments(), 'email'))->first();

        if (! $user instanceof User) {
            $this->components->error('No user has that email.');

            return self::FAILURE;
        }

        $this->line($user->createToken(Arr::string($this->options(), 'name'))->plainTextToken);

        return self::SUCCESS;
    }
}
