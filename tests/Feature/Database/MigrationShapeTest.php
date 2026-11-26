<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('has a default on every boolean column', function (): void {
    $rows = DB::select("select table_name, column_name from information_schema.columns where table_schema = 'public' and data_type = 'boolean' and column_default is null");

    expect($rows)->toBe([]);
});
