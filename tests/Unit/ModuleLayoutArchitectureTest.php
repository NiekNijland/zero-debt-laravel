<?php

declare(strict_types=1);

use Tests\Support\Architecture\ModuleLayoutGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Narrow;
use Tests\Support\SourceSandbox;

uses()->group('arch');

$table = <<<'MD'
    Domain code lives in one module per domain, `app/Modules/<Domain>`, using only these folders:

    | Folder | Holds | Name |
    | --- | --- | --- |
    | `Actions` | one change of state | verb and noun, never an `Action` suffix |
    | `Services` | reads that several callers share | `<X>Service`, `<X>Client` |
    | `Events`, `Listeners` | events and the listeners that handle them | `<X>Event`; a listener says what it does |
    | `Jobs` | queued work | `<X>Job` |
    | `Mail`, `Notifications` | mailables and notifications | `<X>Mail`, `<X>Notification` |
    | `ValueObjects` | every other domain value | noun |
    MD;

$run = static function (array $files, ?string $guide = null) use ($table): array {
    $tree = ['app/AGENTS.md' => $guide ?? $table, ...array_fill_keys(Narrow::strings($files), "<?php\n")];
    $root = SourceSandbox::create($tree, prefix: '.checkout-');

    try {
        $guard = new ModuleLayoutGuard(new PhpFileInspector(), $root, 'app/AGENTS.md');
        $violations = $guard->violations();

        return [$violations, $guard->explain($violations)];
    } finally {
        SourceSandbox::remove($root);
    }
};

dataset('classes outside the module table', [
    'a file in the root of a module' => [['app/Modules/Orders/Loose.php'], ['place app/Modules/Orders/Loose.php'], 'no folder of the module table in app/AGENTS.md holds a class here'],
    'a folder that the table does not name' => [['app/Modules/Orders/Helpers/Thing.php'], ['place app/Modules/Orders/Helpers/Thing.php'], 'never in the module root or a folder the table does not name'],
    'a model folder inside a module' => [['app/Modules/Orders/Models/Order.php'], ['place app/Modules/Orders/Models/Order.php'], 'place app/Modules/Orders/Models/Order.php'],
    'two folders of subdomain' => [['app/Modules/Orders/Actions/Returns/Deep/CancelOrder.php'], ['place app/Modules/Orders/Actions/Returns/Deep/CancelOrder.php'], 'place'],
    'a job without its suffix' => [['app/Modules/Orders/Jobs/SendReceipt.php'], ['name app/Modules/Orders/Jobs/SendReceipt.php'], 'the folder is right and the name is not'],
    'a service named with a suffix of another folder' => [['app/Modules/Orders/Services/RefundJob.php'], ['name app/Modules/Orders/Services/RefundJob.php'], 'follows the Name column of the table in app/AGENTS.md'],
    'an action with the suffix the table forbids' => [['app/Modules/Orders/Actions/CancelOrderAction.php'], ['name app/Modules/Orders/Actions/CancelOrderAction.php'], 'name app/Modules/Orders/Actions/CancelOrderAction.php'],
    'a notification in the mail folder' => [['app/Modules/Orders/Mail/ShippedNotification.php'], ['name app/Modules/Orders/Mail/ShippedNotification.php'], 'name'],
]);

it('fails a class that sits outside the folders of the module table or carries the wrong name', function (array $files, array $entries, string $words) use ($run): void {
    [$violations, $explained] = $run($files);

    expect($violations)->toBe($entries)
        ->and(implode("\n", $explained))->toContain($words);
})->with('classes outside the module table');

it('fails a guide that lost the module table, and a guide that names no module root', function (string $guide): void {
    $root = SourceSandbox::create(['app/AGENTS.md' => $guide, 'app/Modules/Orders/Loose.php' => "<?php\n"]);

    try {
        expect(implode("\n", new ModuleLayoutGuard(new PhpFileInspector(), $root, 'app/AGENTS.md')->violations()))->toContain('app/AGENTS.md holds no table with the columns Folder, Holds and Name');
    } finally {
        SourceSandbox::remove($root);
    }
})->with([
    'no table' => ["# Guide\n\nDomain code lives in `app/Modules/<Domain>`.\n"],
    'a table without the Name column' => ["Domain code lives in `app/Modules/<Domain>`.\n\n| Folder | Holds |\n| --- | --- |\n| `Jobs` | queued work |\n"],
    'a table and no module root' => ["| Folder | Holds | Name |\n| --- | --- | --- |\n| `Jobs` | queued work | `<X>Job` |\n"],
]);

dataset('classes inside the module table', [
    'a class in every folder, with the suffix of its folder' => [['app/Modules/Orders/Actions/CancelOrder.php', 'app/Modules/Orders/Services/RefundService.php', 'app/Modules/Orders/Services/PaymentClient.php', 'app/Modules/Orders/Jobs/SendReceiptJob.php', 'app/Modules/Orders/Events/OrderPlacedEvent.php', 'app/Modules/Orders/Mail/ReceiptMail.php', 'app/Modules/Orders/Notifications/ShippedNotification.php']],
    'a listener, which says what it does' => [['app/Modules/Orders/Listeners/SendReceiptOnPlaced.php']],
    'a class of a folder with no suffix' => [['app/Modules/Orders/ValueObjects/Money.php']],
    'one folder of subdomain' => [['app/Modules/Orders/Actions/Returns/CancelReturn.php']],
    'a name that only contains a forbidden suffix' => [['app/Modules/Orders/Actions/ActionPlan.php']],
    'a class outside the modules, which this guard does not read' => [['app/Support/Helper.php', 'app/Http/Controllers/Orders/ShowOrderController.php', 'app/Modules/Orders/Actions/PlaceOrder.php']],
]);

it('accepts the folders of the table, a subdomain folder and a class outside the modules', function (array $files) use ($run): void {
    [$violations] = $run($files);

    expect($violations)->toBe([]);
})->with('classes inside the module table');

it('keeps every class of a module in the folders of the module table of app/AGENTS.md', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();

    $guard = new ModuleLayoutGuard($inspector, $root, 'app/AGENTS.md');
    $violations = $guard->violations();

    expect($inspector->phpFiles($root . '/app/Modules'))->not->toBe([])
        ->and($violations)->toBe([], implode("\n", $guard->explain($violations)));
});
