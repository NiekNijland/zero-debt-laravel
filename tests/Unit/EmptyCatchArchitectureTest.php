<?php

declare(strict_types=1);

use Tests\Support\Architecture\EmptyCatchGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('swallowed broad catches', [
    'imported throwable' => ["<?php\nnamespace App;\nuse Throwable;\ntry { f(); } catch (Throwable) {}\n"],
    'fully qualified exception' => ["<?php\nnamespace App;\ntry { f(); } catch (\\Exception \$e) {}\n"],
    'comment only' => ["<?php\nnamespace App;\nuse RuntimeException;\ntry { f(); } catch (RuntimeException) {\n    " . '/' . "/ ignored\n}\n"],
    'union with a broad type' => ["<?php\nnamespace App;\nuse Throwable;\ntry { f(); } catch (\\App\\Foo | Throwable) {}\n"],
    'inside a closure' => ["<?php\nnamespace App;\n\$f = function (): void { try { f(); } catch (\\Error) {} };\n"],
    'aliased throwable' => ["<?php\nnamespace App;\nuse Throwable as Failure;\ntry { f(); } catch (Failure) {}\n"],
    'return null' => ["<?php\nnamespace App;\nuse Throwable;\nfunction g(): ?int { try { return f(); } catch (Throwable) { return null; } }\n"],
    'return false' => ["<?php\nnamespace App;\nuse Exception;\nfunction g(): bool { try { return f(); } catch (Exception \$e) {\n    " . '/' . "/ ignored\n    return false;\n} }\n"],
    'return true' => ["<?php\nnamespace App;\nuse Throwable;\nfunction g(): bool { try { return f(); } catch (Throwable) { return true; } }\n"],
    'return an empty array' => ["<?php\nnamespace App;\nuse Throwable;\nfunction g(): array { try { return f(); } catch (Throwable) { return []; } }\n"],
    'bare return' => ["<?php\nnamespace App;\nuse Throwable;\nfunction g(): void { try { f(); } catch (Throwable) { return; } }\n"],
    'fallback value' => ["<?php\nnamespace App;\nuse Throwable;\nfunction h(): array { try { return f(); } catch (Throwable) { return ['fallback']; } }\n"],
    'log only' => ["<?php\nnamespace App;\nuse Throwable;\nuse Illuminate\\Support\\Facades\\Log;\ntry { f(); } catch (Throwable \$e) { Log::warning(\$e->getMessage()); }\n"],
    'console error only' => ["<?php\nnamespace App;\nuse RuntimeException;\nfinal class C { public function handle(): int { try { f(); } catch (RuntimeException \$e) { \$this->error(\$e->getMessage()); return 1; } return 0; } }\n"],
    'a domain exception factory without throw' => ["<?php\nnamespace App;\nuse Throwable;\ntry { f(); } catch (Throwable \$e) { \$failure = FooExceptions::wrap(\$e); }\n"],
]);

it('detects a broad catch that only swallows the exception', function (string $source): void {
    $violations = new EmptyCatchGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'])->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('only swallows the exception', 'report it with report()', 'rethrow it', 'catch the exact exception');
})->with('swallowed broad catches');

dataset('catches that report, rescue, rethrow, fail or name the exact exception', [
    'report' => ["<?php\nnamespace App;\nuse Throwable;\ntry { f(); } catch (Throwable \$e) { report(\$e); }\n"],
    'rethrow' => ["<?php\nnamespace App;\nuse Throwable;\ntry { f(); } catch (Throwable \$e) { throw \$e; }\n"],
    'wrap with the previous exception' => ["<?php\nnamespace App;\nuse Throwable;\ntry { f(); } catch (Throwable \$e) { throw new \\DomainException('wrapped', previous: \$e); }\n"],
    'the exact exception, empty body' => ["<?php\nnamespace App;\nuse League\\Flysystem\\UnableToReadFile;\ntry { f(); } catch (UnableToReadFile) {}\n"],
    'rescue' => ["<?php\nnamespace App;\nuse Throwable;\ntry { f(); } catch (Throwable \$e) { rescue(static fn () => report(\$e), report: false); }\n"],
    'the exceptions facade' => ["<?php\nnamespace App;\nuse Throwable;\nuse Illuminate\\Support\\Facades\\Exceptions;\ntry { f(); } catch (Throwable \$e) { Exceptions::report(\$e); }\n"],
    'a reporting factory' => ["<?php\nnamespace App;\nuse Throwable;\ntry { f(); } catch (Throwable \$e) { ReportedFailureException::reportSafely('Label', \$e); }\n"],
    'report, then a fallback value' => ["<?php\nnamespace App;\nuse Throwable;\nfunction g(): ?int { try { return f(); } catch (Throwable \$e) { report(\$e); return null; } }\n"],
    'a test that fails' => ["<?php\nnamespace App;\nfinal class T { public function test(): void { try { f(); } catch (\\Exception \$e) { \$this->fail(\$e->getMessage()); } } }\n"],
]);

it('accepts catches that report, rescue, rethrow, fail or name the exact expected exception', function (string $source): void {
    expect(new EmptyCatchGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'])->inSource($source))->toBe([]);
})->with('catches that report, rescue, rethrow, fail or name the exact exception');

it('reads a tree under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create([
        'app/Swallows.php' => "<?php\nnamespace App;\nuse Throwable;\ntry { f(); } catch (Throwable) {}\n",
        'app/Reports.php' => "<?php\nnamespace App;\nuse Throwable;\ntry { f(); } catch (Throwable \$e) { report(\$e); }\n",
    ], prefix: '.checkout-');

    try {
        $violations = new EmptyCatchGuard(new PhpFileInspector(), $root, ['app'])->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Swallows.php:4: a broad catch that only swallows the exception');
});

it('lets no file in app swallow a broad exception', function (): void {
    expect(new EmptyCatchGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'])->violations())->toBe([]);
});
