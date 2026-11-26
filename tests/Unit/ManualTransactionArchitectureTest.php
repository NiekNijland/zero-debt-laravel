<?php

declare(strict_types=1);

use Tests\Support\Architecture\ManualTransactionGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('transactions handled by hand', [
    'begin and commit' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\DB;\nfinal class Save { public function handle(): void { DB::beginTransaction(); \$this->write(); DB::commit(); } }\n", 'committed by hand,opened by hand'],
    'begin, commit and a rollback in a catch' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\DB;\nfinal class Save { public function handle(): void { DB::beginTransaction(); try { \$this->write(); DB::commit(); } catch (\\Throwable \$e) { DB::rollBack(); throw \$e; } } }\n", 'committed by hand,opened by hand,rolled back by hand'],
    'a named connection' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\DB;\nDB::connection('landlord')->beginTransaction();\nDB::connection('landlord')->commit();\n", 'opened by hand,committed by hand'],
    'an injected connection' => ["<?php\nnamespace App;\nfinal class Save { public function handle(): void { \$this->connection->beginTransaction(); \$this->connection->rollBack(); } }\n", 'opened by hand,rolled back by hand'],
    'a commit on a typed connection' => ["<?php\nnamespace App;\nuse Illuminate\\Database\\ConnectionInterface;\nfinal class Save { public function __construct(private ConnectionInterface \$db) {} public function handle(): void { \$this->db->commit(); } }\n", 'committed by hand'],
    'a commit on the container string' => ["<?php\nnamespace App;\napp('db')->commit();\n", 'committed by hand'],
    'a rollback outside a finally on a typed connection' => ["<?php\nnamespace App;\nuse Illuminate\\Database\\Connection;\nfinal class Save { public function handle(Connection \$db): void { \$db->rollback(); } }\n", 'rolled back by hand'],
    'the global alias' => ["<?php\nnamespace App;\n\\DB::beginTransaction();\n\\DB::commit();\n", 'opened by hand,committed by hand'],
    'a dry run whose rollback sits in another method' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\DB;\nfinal class Preview { public function a(): void { DB::beginTransaction(); } public function b(): void { try { f(); } finally { DB::rollBack(); } } }\n", 'opened by hand'],
    'a commit on a nullable typed property' => ["<?php\nnamespace App;\nuse Illuminate\\Database\\ConnectionInterface;\nfinal class Save { private ?ConnectionInterface \$db = null; public function handle(): void { \$this->db->commit(); } }\n", 'committed by hand'],
    'a commit on a union typed promoted property' => ["<?php\nnamespace App;\nuse Illuminate\\Database\\Connection;\nuse Illuminate\\Database\\ConnectionInterface;\nfinal class Save { public function __construct(private ConnectionInterface|Connection \$db) {} public function handle(): void { \$this->db->commit(); } }\n", 'committed by hand'],
    'a commit on an alias variable of a typed parameter' => ["<?php\nnamespace App;\nuse Illuminate\\Database\\ConnectionInterface;\nfinal class Save { public function handle(?ConnectionInterface \$db): void { \$d = \$db; \$d->commit(); } }\n", 'committed by hand'],
    'a commit on a typed parameter inside a closure that uses it' => ["<?php\nnamespace App;\nuse Illuminate\\Database\\ConnectionInterface;\nfinal class Save { public function handle(ConnectionInterface \$db): void { \$run = function () use (\$db): void { \$db->commit(); }; \$run(); } }\n", 'committed by hand'],
]);

it('detects a transaction that is opened, committed or rolled back by hand', function (string $source, string $reasons): void {
    $violations = new ManualTransactionGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source);

    expect($violations)->toHaveCount(count(explode(',', $reasons)));

    foreach (explode(',', $reasons) as $index => $reason) {
        expect($violations[$index])->toContain($reason)->toContain('DB::transaction(fn () => ...)');
    }
})->with('transactions handled by hand');

it('accepts DB::transaction, a dry run that always rolls back and a commit that is not of the database', function (): void {
    $source = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Facades\DB;
        final class Save
        {
            public function handle(): int { return DB::transaction(fn (): int => $this->write()); }
            public function preview(): int
            {
                DB::beginTransaction();
                try { return DB::transaction(fn (): int => $this->write()); } finally { DB::rollBack(); }
            }
            public function migrate(): void { $this->migrator->rollback(); $this->repository->commit(); $this->untyped->commit(); }
            public function later(): void { DB::afterCommit(fn () => $this->notify()); $this->repository->commit(); }
        }
        PHP;

    expect(new ManualTransactionGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source))->toBe([]);
});

it('reads a typed receiver in its own scope: the same name untyped in another class, another method or another closure is not a connection', function (): void {
    $source = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Database\ConnectionInterface;
        final class Typed
        {
            public function __construct(private ConnectionInterface $db, private readonly Repository $repository) {}
            public function read(ConnectionInterface $d): int { return $d->select('x'); }
            public function other(Repository $d): void { $d->commit(); }
            public function later(): void { $run = function (Repository $db): void { $db->commit(); }; $run(); }
            public function reassigned(ConnectionInterface $c): void { $d = $c; $d = $this->repository; $d->commit(); }
        }
        final class Untyped
        {
            private $db;
            public function handle(): void { $this->db->commit(); }
        }
        PHP;

    expect(new ManualTransactionGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source))->toBe([]);
});

it('reads a tree under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create(['app/Commits.php' => "<?php\nuse Illuminate\\Support\\Facades\\DB;\nDB::commit();\n", 'app/Clean.php' => "<?php\n"], prefix: '.checkout-');

    try {
        $violations = new ManualTransactionGuard(new PhpFileInspector(), $root, 'app')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Commits.php:3: a transaction committed by hand');
});

it('opens every transaction in app through DB::transaction', function (): void {
    expect(new ManualTransactionGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->violations())->toBe([]);
});
