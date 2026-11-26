<?php

declare(strict_types=1);

use Tests\Support\Globs;
use Tests\Support\SourceSandbox;

uses()->group('arch');

it('lists the paths a pattern matches and an empty list when none does', function (): void {
    $root = SourceSandbox::create(['a.php' => '<?php', 'b.php' => '<?php', 'c.txt' => 'x']);

    try {
        $names = array_map(basename(...), Globs::of($root . '/*.php'));

        expect($names)->toBe(['a.php', 'b.php'])
            ->and(Globs::of($root . '/*.md'))->toBe([])
            ->and(Globs::of($root . '/{a,c}.*', GLOB_BRACE))->toHaveCount(2);
    } finally {
        SourceSandbox::remove($root);
    }
});
