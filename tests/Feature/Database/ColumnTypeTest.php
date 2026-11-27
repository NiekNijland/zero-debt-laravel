<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('stores no moment as a plain timestamp and no structured data as plain json', function (): void {
    $rows = DB::select("select table_name, column_name, data_type from information_schema.columns where table_schema = 'public' and data_type in ('timestamp without time zone', 'json')");

    expect($rows)->toBe([]);
});

it('stores a moment as timestamptz', function (): void {
    $type = DB::table('information_schema.columns')->where('table_name', 'users')->where('column_name', 'created_at')->value('data_type');

    expect($type)->toBe('timestamp with time zone');
});
