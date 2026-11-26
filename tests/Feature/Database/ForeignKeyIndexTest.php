<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('indexes every foreign key column as the first column of an index', function (): void {
    $rows = DB::select(<<<'SQL'
        select c.conrelid::regclass::text as table_name, a.attname as column_name
        from pg_constraint c
        join pg_attribute a on a.attrelid = c.conrelid and a.attnum = c.conkey[1]
        where c.contype = 'f'
          and not exists (
            select 1 from pg_index i
            where i.indrelid = c.conrelid and i.indkey[0] = c.conkey[1]
          )
        SQL);

    expect($rows)->toBe([]);
});
