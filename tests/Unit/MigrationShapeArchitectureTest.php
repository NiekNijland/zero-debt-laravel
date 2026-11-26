<?php

declare(strict_types=1);

use Tests\Support\Architecture\MigrationShapeGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('migrations out of shape', [
    'no down()' => ["public function up(): void\n{\n    Schema::create('shipments', fn (Blueprint \$table) => \$table->id());\n}", 'a migration without down(): add public function down(): void'],
    'an empty down() after a schema change' => ["public function up(): void\n{\n    Schema::table('shipments', fn (Blueprint \$table) => \$table->string('tracking_code')->nullable());\n}\npublic function down(): void\n{\n}", 'an empty down() in a migration that changes the schema: undo up() in it'],
    'an empty down() after raw DDL in a helper' => ["public function up(): void\n{\n    \$this->add();\n}\nprivate function add(): void\n{\n    DB::statement('ALTER TABLE shipments ADD COLUMN tracking_code text');\n}\npublic function down(): void\n{\n}", 'an empty down() in a migration that changes the schema: undo up() in it'],
    'a constrained foreign key without an action' => ["public function up(): void\n{\n    Schema::create('shipments', fn (Blueprint \$table) => \$table->foreignId('tenant_id')->constrained());\n}\npublic function down(): void\n{\n    Schema::dropIfExists('shipments');\n}", 'a foreign key without its on-delete action: end it with ->cascadeOnDelete(), ->nullOnDelete() or ->restrictOnDelete()'],
    'an explicit foreign key without an action' => ["public function up(): void\n{\n    Schema::create('shipments', fn (Blueprint \$table) => \$table->foreign('carrier_id')->references('id')->on('carriers'));\n}\npublic function down(): void\n{\n    Schema::dropIfExists('shipments');\n}", 'a foreign key without its on-delete action: end it with ->cascadeOnDelete(), ->nullOnDelete() or ->restrictOnDelete()'],
    'a boolean without a default' => ["public function up(): void\n{\n    Schema::create('shipments', fn (Blueprint \$table) => \$table->boolean('is_insured')->nullable());\n}\npublic function down(): void\n{\n    Schema::dropIfExists('shipments');\n}", 'a boolean column without a default: add ->default(false) or ->default(true)'],
]);

it('detects a migration that cannot roll back or leaves a choice of the schema open', function (string $members, string $reason): void {
    $source = "<?php\nuse Illuminate\\Database\\Migrations\\Migration;\nreturn new class() extends Migration\n{\n" . $members . "\n};\n";
    $violations = new MigrationShapeGuard(dirname(__DIR__, 2), 'database/migrations', '2025_01_01')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($reason);
})->with('migrations out of shape');

it('accepts a migration that undoes its schema change and one that changes rows alone', function (): void {
    $schema = <<<'PHP'
        <?php
        use Illuminate\Database\Migrations\Migration;
        return new class() extends Migration
        {
            public function up(): void
            {
                Schema::create('shipments', function (Blueprint $table): void {
                    $table->id();
                    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                    $table->foreign('carrier_id')->references('id')->on('carriers')->onDelete('set null');
                    $table->boolean('is_insured')->default(false);
                });
            }
            public function down(): void
            {
                Schema::dropIfExists('shipments');
            }
        };
        PHP;
    $rows = <<<'PHP'
        <?php
        use Illuminate\Database\Migrations\Migration;
        return new class() extends Migration
        {
            public function up(): void
            {
                DB::table('events')->where('name', 'Old')->update(['name' => 'New']);
                DB::statement('UPDATE events SET created_at = now() WHERE created_at IS NULL');
            }
            public function down(): void
            {
            }
        };
        PHP;
    $guard = new MigrationShapeGuard(dirname(__DIR__, 2), 'database/migrations', '2025_01_01');

    expect($guard->inSource($schema))->toBe([])
        ->and($guard->inSource($rows))->toBe([]);
});

it('holds from the first file on, and names the file relative to the root under a dot directory', function (): void {
    $bad = "<?php\nreturn new class() extends Migration\n{\n    public function up(): void\n    {\n        Schema::create('t', fn (Blueprint \$table) => \$table->id());\n    }\n};\n";
    $root = SourceSandbox::create([
        'database/migrations/2024_12_31_000000_old.php' => $bad,
        'database/migrations/2025_01_01_000000_first.php' => $bad,
    ], prefix: '.checkout-');

    try {
        $violations = new MigrationShapeGuard($root, 'database/migrations', '2025_01_01')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('database/migrations/2025_01_01_000000_first.php:')
        ->and($violations[0])->toContain('a migration without down()');
});

it('throws on a migrations folder that does not exist', function (): void {
    expect(fn (): array => new MigrationShapeGuard(dirname(__DIR__, 2), 'database/renamed-migrations', '0001_01_01_000000')->violations())
        ->toThrow(InvalidArgumentException::class, 'does not exist');
});

it('keeps every migration of the project, from the first one, in shape', function (): void {
    expect(new MigrationShapeGuard(dirname(__DIR__, 2), 'database/migrations', '0001_01_01_000000')->violations())->toBe([]);
});
