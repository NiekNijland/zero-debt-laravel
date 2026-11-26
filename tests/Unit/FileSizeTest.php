<?php

declare(strict_types=1);

use Tests\Support\Architecture\FileSizeGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Narrow;
use Tests\Support\SourceSandbox;

uses()->group('arch');

$lines = static fn (int $count): string => "<?php\n\nfinal class Big\n{\n" . str_repeat("    \x2f\x2f line\n", $count - 5) . "}\n";
$data = static fn (int $count): string => "<?php\n\nreturn [\n" . str_repeat("    'key' => 'value',\n", $count - 4) . "];\n";

dataset('files over the limit', [
    'a class of 401 lines' => [fn (): string => $lines(402), '402 lines, the limit is 400'],
    'a class far over' => [fn (): string => $lines(900), '900 lines, the limit is 400'],
    'a file that returns an array and holds more than data' => [fn (): string => "<?php\n\n\$x = 1;\n" . mb_substr($data(500), 6), '502 lines, the limit is 400'],
]);

it('detects a file over 400 lines and says how to split it', function (Closure $source, string $reason): void {
    $violation = new FileSizeGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'])->inSource('app/Big.php', Narrow::string($source()));

    expect($violation)->toContain('app/Big.php has ' . $reason)
        ->and($violation)->toContain('split it by behavior');
})->with('files over the limit');

dataset('files within the limit', [
    'exactly 400 lines' => [fn (): string => $lines(400)],
    'a short file' => [fn (): string => $lines(20)],
    'a data file over the limit that only returns a list' => [fn (): string => $data(900)],
    'a data file with a strict-types declaration' => [fn (): string => "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n" . str_repeat("    'key' => 'value',\n", 600) . "];\n"],
]);

it('accepts a file within the limit and a file that only returns data', function (Closure $source): void {
    expect(new FileSizeGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'])->inSource('app/Big.php', Narrow::string($source())))->toBeNull();
})->with('files within the limit');

it('names the file relative to the root when the checkout sits under a dot directory', function () use ($lines): void {
    $root = SourceSandbox::create(['app/Big.php' => $lines(450), 'app/Small.php' => $lines(10)], prefix: '.checkout-');

    try {
        $violations = new FileSizeGuard(new PhpFileInspector(), $root, ['app'])->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Big.php has 450 lines');
});

it('keeps every file in every folder of PHP at 400 lines or fewer', function (): void {
    expect(new FileSizeGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'bootstrap', 'config', 'database', 'lang', 'routes', 'tests'])->violations())->toBe([]);
});
