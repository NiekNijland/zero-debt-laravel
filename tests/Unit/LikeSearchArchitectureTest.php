<?php

declare(strict_types=1);

use Tests\Support\Architecture\LikeSearchGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('case-insensitive searches with a pattern built by hand', [
    'lower() like with a bound term' => ["<?php\n\$query->whereRaw('lower(name) like ?', ['%' . mb_strtolower(\$term) . '%']);\n"],
    'lower() like with an interpolated term' => ["<?php\n\$query->whereRaw(\"lower(name) like '%{\$term}%'\");\n"],
    'lower() over an expression' => ["<?php\n\$query->orWhereRaw('LOWER(coalesce(name, email)) LIKE ?', [\$term . '%']);\n"],
    'the ilike operator' => ["<?php\n\$query->where('name', 'ilike', '%' . \$term . '%');\n"],
    'the ilike operator in capitals inside a closure' => ["<?php\n\$query->where(fn (\$q) => \$q->orWhere('name', 'ILIKE', \"%{\$term}%\"));\n"],
    'ilike in raw SQL' => ["<?php\n\$query->whereRaw(\$nameSql . ' ilike ?', [\$search]);\n"],
    'an own escape' => ["<?php\n\$query->whereRaw('lower(email) like ?', [addcslashes(\$term, '%_') . '%']);\n"],
    'whereLike with a pattern joined by hand' => ["<?php\n\$query->whereLike('name', '%' . \$term . '%');\n"],
    'ilike with a call in the pattern' => ["<?php\n\$query->where('name', 'ilike', '%' . request('q') . '%');\n"],
    'a second pattern by hand next to one from the helper' => ["<?php\n\$query->whereRaw('lower(a) like ? or lower(b) like ?', [Like::contains(\$a), '%' . \$b . '%']);\n"],
    'a static call' => ["<?php\nDB::select('select 1 from users where name ilike ?', [\$term]);\n"],
]);

it('detects a case-insensitive LIKE that does not go through App\Support\Like', function (string $source): void {
    $violations = new LikeSearchGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('whereLike($column, Like::contains($term))');
})->with('case-insensitive searches with a pattern built by hand');

it('accepts the helper, the prefix search over an expression index, a fixed pattern and a case-sensitive match', function (): void {
    $source = <<<'PHP'
        <?php
        use App\Support\Like;
        $query->whereLike('name', Like::contains($term));
        $query->whereRaw('lower(email) like ?', [Like::escape(Str::lower($term)) . '%']);
        $query->whereRaw($nameSql . ' ilike ?', [Like::contains($search)]);
        $query->where(fn ($q) => $q->where('type', $type)->orWhere('name', 'ILIKE', '%mug%'));
        $query->where('mime_type', 'like', 'image/%');
        $query->where('sku', 'like', $prefix . '%');
        $query->where('note', 'I would like this')->where('id', $id);
        $query->whereLike('name', Like::contains($term));
        $query->whereLike('name', '%mug%');
        $query->whereRaw('lower(a) like ? or lower(b) like ?', [Like::contains($a), Like::contains($b)]);
        $query->where('name', 'ilike', Like::contains(request('q')));
        PHP;

    expect(new LikeSearchGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source))->toBe([]);
});

it('reads a tree under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create([
        'app/Bad.php' => "<?php\n\$query->where('name', 'ilike', '%' . \$term . '%');\n",
        'app/Good.php' => "<?php\n\$query->whereLike('name', Like::contains(\$term));\n",
    ], prefix: '.checkout-');

    try {
        $violations = new LikeSearchGuard(new PhpFileInspector(), $root, 'app')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Bad.php:2: a case-insensitive LIKE with a pattern built by hand');
});

it('sends every case-insensitive search in app through App\Support\Like', function (): void {
    expect(new LikeSearchGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->violations())->toBe([]);
});
