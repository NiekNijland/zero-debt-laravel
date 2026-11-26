<?php

declare(strict_types=1);

use Tests\Support\Architecture\TemporarySource;

uses()->group('arch');

it('hands the reader a file that holds the source and removes it afterwards', function (): void {
    $seen = [];
    $length = TemporarySource::with('<?php echo 1;', static function (string $path) use (&$seen): int {
        $seen[] = $path;

        return mb_strlen((string) file_get_contents($path));
    });

    expect($length)->toBe(13)
        ->and($seen)->toHaveCount(1)
        ->and(file_exists($seen[0]))->toBeFalse();
});

it('removes the file when the reader throws', function (): void {
    $path = '';

    try {
        TemporarySource::with('<?php', static function (string $file) use (&$path): never {
            $path = $file;

            throw new RuntimeException('stop');
        });
    } catch (RuntimeException) {
        expect($path)->not->toBe('')
            ->and(file_exists($path))->toBeFalse();
    }
});
