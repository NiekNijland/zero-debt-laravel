<?php

declare(strict_types=1);

use Tests\Support\Architecture\ConcurrentIndexGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

$migration = static fn (string $up, string $members = ''): string => "<?php\nreturn new class() extends Migration {\n{$members}\n    public function up(): void\n    {\n{$up}\n    }\n};\n";

dataset('blocking index migrations', [
    'index on an existing table' => ["Schema::table('products', function (Blueprint \$table): void {\n    \$table->index('sku');\n});", '', 'products'],
    'unique column on an existing table' => ["Schema::table('coupons', fn (Blueprint \$table) => \$table->string('code')->unique());", '', 'coupons'],
    'foreign key on an existing table' => ["Schema::table('orders', function (Blueprint \$table): void {\n    \$table->foreignId('carrier_id')->nullable()->constrained();\n});", '', 'orders'],
    'explicit foreign on an existing table' => ["Schema::table('orders', function (Blueprint \$table): void {\n    \$table->foreign('carrier_id')->references('id')->on('carriers');\n});", '', 'orders'],
    'morphs on an existing table' => ["Schema::table('notifications', fn (Blueprint \$table) => \$table->nullableMorphs('subject'));", '', 'notifications'],
    'primary key on an existing table' => ["Schema::table('category_product', fn (Blueprint \$table) => \$table->primary(['category_id', 'product_id']));", '', 'category_product'],
    'method name in another case' => ["Schema::table('products', fn (Blueprint \$table) => \$table->Index('brand'));", '', 'products'],
    'schema from a connection' => ["Schema::connection('landlord')->table('events', function (Blueprint \$table): void {\n    \$table->index('name');\n});", '', 'events'],
    'blueprint index next to a concurrent one' => ["DB::statement('CREATE INDEX CONCURRENTLY products_sku_index ON products (sku)');\nSchema::table('products', fn (Blueprint \$table) => \$table->index('brand'));", 'public $withinTransaction = false;', 'products'],
    'index in a helper method' => ['$this->addIndex();', "private function addIndex(): void\n{\n    Schema::table('users', fn (Blueprint \$table) => \$table->index('email'));\n}", 'users'],
    'raw index on an existing table' => ["DB::statement('CREATE INDEX products_brand_index ON products (brand)');", '', 'products'],
    'raw unique index on an existing table' => ["DB::statement(<<<'SQL'\n    create unique index users_email_unique on public.users (lower(email))\n    SQL);", '', 'users'],
    'raw foreign key that is validated at once' => ["DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_carrier_id_foreign FOREIGN KEY (carrier_id) REFERENCES carriers (id)');", 'public $withinTransaction = false;', 'orders'],
]);

it('finds a blocking index on a table that an earlier migration created', function (string $up, string $members, string $table) use ($migration): void {
    $violations = new ConcurrentIndexGuard(dirname(__DIR__, 2), 'database/migrations', '2025_01_01')->inSource($migration($up, $members));

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith($table . ': an index or foreign key built on a table that already exists')
        ->and($violations[0])->toContain('CREATE INDEX CONCURRENTLY', '$withinTransaction = false', 'NOT VALID');
})->with('blocking index migrations');

dataset('safe index migrations', [
    'index on a table created in the same migration' => ["Schema::create('warehouses', function (Blueprint \$table): void {\n    \$table->id();\n});\nSchema::table('warehouses', function (Blueprint \$table): void {\n    \$table->index('name');\n});"],
    'raw index on a table created in the same migration' => ["DB::statement('CREATE TABLE stock_moves (id bigint PRIMARY KEY, warehouse_id bigint REFERENCES warehouses (id))');\nDB::statement('CREATE INDEX stock_moves_warehouse_id_index ON stock_moves (warehouse_id)');"],
    'column without an index on an existing table' => ["Schema::table('products', function (Blueprint \$table): void {\n    \$table->string('brand')->nullable();\n});"],
    'concurrent index outside a transaction' => ["DB::statement('CREATE INDEX CONCURRENTLY products_brand_index ON products (brand)');", 'public $withinTransaction = false;'],
    'foreign key added NOT VALID and validated afterwards' => ["DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_carrier_id_foreign FOREIGN KEY (carrier_id) REFERENCES carriers (id) NOT VALID');\nDB::statement('ALTER TABLE orders VALIDATE CONSTRAINT orders_carrier_id_foreign');", 'public $withinTransaction = false;'],
    'index restored in down' => ['', "public function down(): void\n{\n    Schema::table('products', fn (Blueprint \$table) => \$table->index('brand'));\n}"],
]);

it('accepts an index that blocks no existing table', function (string $up, string $members = '') use ($migration): void {
    expect(new ConcurrentIndexGuard(dirname(__DIR__, 2), 'database/migrations', '2025_01_01')->inSource($migration($up, $members)))->toBe([]);
})->with('safe index migrations');

it('holds from the first file on, and names the file relative to the root under a dot directory', function () use ($migration): void {
    $blocking = $migration("Schema::table('products', fn (Blueprint \$table) => \$table->index('brand'));");
    $root = SourceSandbox::create([
        'database/migrations/2024_12_31_000000_old.php' => $blocking,
        'database/migrations/2025_01_01_000000_first.php' => $blocking,
        'database/migrations/2025_02_01_000000_safe.php' => $migration("Schema::create('t', fn (Blueprint \$table) => \$table->id());"),
    ], prefix: '.checkout-');

    try {
        $violations = new ConcurrentIndexGuard($root, 'database/migrations', '2025_01_01')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('database/migrations/2025_01_01_000000_first.php: products: an index or foreign key built on a table that already exists');
});

it('builds indexes on existing tables concurrently in every migration of the project', function (): void {
    expect(new ConcurrentIndexGuard(dirname(__DIR__, 2), 'database/migrations', '0001_01_01_000000')->violations())->toBe([]);
});
