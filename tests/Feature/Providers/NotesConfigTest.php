<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;

it('holds typed numbers for the pagination of the notes', function (): void {
    expect(Config::integer('notes.max_per_page'))->toBeGreaterThanOrEqual(Config::integer('notes.per_page'));
});
