<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()
    ->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        Process::fake([]);
        Sleep::fake();
        $this->freezeTime();
        Str::createUuidsNormally();
        Str::createRandomStringsNormally();
    })
    ->in('Feature');
