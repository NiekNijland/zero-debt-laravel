<?php

declare(strict_types=1);

use Tests\Support\Architecture\MethodSizeGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Narrow;
use Tests\Support\SourceSandbox;

uses()->group('arch');

$sentence = 'A PHP file has at most 400 lines; a method at most 80 lines, a controller action at most 40.';

$body = static fn (int $lines): string => str_repeat("        \$value = 1;\n", $lines);

$method = static fn (string $head, int $lines): string => "    {$head}\n    {\n" . str_repeat("        \$value = 1;\n", $lines - 3) . "    }\n";

$php = static fn (string $kind, string $members, string $namespace = 'App\\Modules\\Orders\\Services'): string => "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\n{$kind}\n{\n{$members}}\n";

$run = static function (array $files, string $guide = '') use ($sentence): array {
    $tree = ['app/AGENTS.md' => $guide === '' ? $sentence . "\n" : $guide, ...Narrow::stringMap($files)];
    $root = SourceSandbox::create($tree, prefix: '.checkout-');

    try {
        $guard = new MethodSizeGuard(new PhpFileInspector(), $root, 'app', 'app/AGENTS.md', 'Controllers');
        $findings = $guard->findings();

        return [$findings, $guard->explain($findings)];
    } finally {
        SourceSandbox::remove($root);
    }
};

dataset('methods over their limit', [
    'a service method of 81 lines' => [
        ['app/Modules/Orders/Services/Runner.php' => $php('final class Runner', $method('public function run(): void', 81))],
        ['app/Modules/Orders/Services/Runner.php::Runner::run::81'],
        'Runner::run has 81 lines, over the limit of 80 for a method',
    ],
    'a private method of 81 lines' => [
        ['app/Modules/Orders/Services/Runner.php' => $php('final class Runner', $method('private function load(): void', 81))],
        ['app/Modules/Orders/Services/Runner.php::Runner::load::81'],
        'split it by responsibility, never by line count, as app/AGENTS.md says',
    ],
    'a controller action of 41 lines' => [
        ['app/Http/Controllers/Orders/ShowOrderController.php' => $php('final readonly class ShowOrderController', $method('public function __invoke(): void', 41), 'App\\Http\\Controllers\\Orders')],
        ['app/Http/Controllers/Orders/ShowOrderController.php::ShowOrderController::__invoke::41'],
        'and 40 for a controller action',
    ],
    'a private method of a controller of 81 lines' => [
        ['app/Http/Controllers/Orders/ShowOrderController.php' => $php('final readonly class ShowOrderController', $method('private function load(): void', 81), 'App\\Http\\Controllers\\Orders')],
        ['app/Http/Controllers/Orders/ShowOrderController.php::ShowOrderController::load::81'],
        'has 81 lines',
    ],
    'a trait method of 81 lines' => [
        ['app/Modules/Orders/Support/Runs.php' => $php('trait Runs', $method('public function run(): void', 81))],
        ['app/Modules/Orders/Support/Runs.php::Runs::run::81'],
        'Runs::run has 81 lines',
    ],
    'a method of an anonymous class' => [
        ['app/Support/Maker.php' => "<?php\n\ndeclare(strict_types=1);\n\nreturn new class {\n" . $method('public function make(): void', 90) . "};\n"],
        ['app/Support/Maker.php::class@anonymous::make::90'],
        'class@anonymous::make has 90 lines',
    ],
    'a method that a long closure makes long' => [
        ['app/Modules/Orders/Services/Runner.php' => $php('final class Runner', "    public function run(): void\n    {\n        \$f = function (): void {\n" . $body(80) . "        };\n    }\n")],
        ['app/Modules/Orders/Services/Runner.php::Runner::run::85'],
        'Runner::run has 85 lines',
    ],
    'a method with a signature over many lines' => [
        ['app/Modules/Orders/Services/Runner.php' => $php('final class Runner', "    public function run(\n" . str_repeat("        int \$a,\n", 10) . "        int \$last,\n    ): void {\n" . $body(70) . "    }\n")],
        ['app/Modules/Orders/Services/Runner.php::Runner::run::84'],
        'Runner::run has 84 lines',
    ],
    'a file the parser cannot read' => [
        ['app/Modules/Orders/Services/Broken.php' => "<?php\n\nfinal class Broken {\n"],
        ['unreadable app/Modules/Orders/Services/Broken.php: Syntax error, unexpected EOF on line 4'],
        'unreadable app/Modules/Orders/Services/Broken.php',
    ],
    'a guide that lost the sentence with the limits' => [
        ['app/Modules/Orders/Services/Runner.php' => $php('final class Runner', $method('public function run(): void', 10))],
        ['app/AGENTS.md no longer holds the sentence the guard reads (a method at most N lines, a controller action at most N): restore it, the guard takes its numbers from it'],
        'no longer holds the sentence the guard reads',
        "# Guide\n\nA method has a limit.\n",
    ],
]);

it('fails a method over its limit, a controller action over its own and a guide without the sentence', function (array $files, array $entries, string $words, string $guide = '') use ($run): void {
    [$findings, $explained] = $run($files, $guide);

    expect($findings)->toBe($entries)
        ->and(implode("\n", $explained))->toContain($words);
})->with('methods over their limit');

dataset('methods inside their limit', [
    'a method of 80 lines' => [['app/Modules/Orders/Services/Runner.php' => $php('final class Runner', $method('public function run(): void', 80))]],
    'a controller action of 40 lines' => [['app/Http/Controllers/Orders/ShowOrderController.php' => $php('final readonly class ShowOrderController', $method('public function __invoke(): void', 40), 'App\\Http\\Controllers\\Orders')]],
    'a private helper of 60 lines in a controller' => [['app/Http/Controllers/Orders/ShowOrderController.php' => $php('final readonly class ShowOrderController', $method('private function load(): void', 60), 'App\\Http\\Controllers\\Orders')]],
    'a constructor of 60 lines in a controller' => [['app/Http/Controllers/Orders/ShowOrderController.php' => $php('final readonly class ShowOrderController', $method('public function __construct()', 60), 'App\\Http\\Controllers\\Orders')]],
    'a service method of 60 lines in a folder that only looks like a controller folder' => [['app/Modules/Orders/Services/ControllersMap.php' => $php('final class ControllersMap', $method('public function run(): void', 60))]],
    'a long docblock and attributes above a short method' => [['app/Modules/Orders/Services/Runner.php' => $php('final class Runner', "    /**\n" . str_repeat("     * A line of the docblock.\n", 90) . "     */\n    #[\\Deprecated]\n" . $method('public function run(): void', 10))]],
    'an interface method' => [['app/Modules/Orders/Contracts/Runs.php' => $php('interface Runs', "    public function run(): void;\n")]],
    'a long closure outside a class' => [['app/Support/helpers.php' => "<?php\n\ndeclare(strict_types=1);\n\n\$f = function (): void {\n" . str_repeat("    \$value = 1;\n", 200) . "};\n"]],
    'two methods of one class that together pass the limit' => [['app/Modules/Orders/Services/Runner.php' => $php('final class Runner', $method('public function one(): void', 50) . $method('public function two(): void', 50))]],
]);

it('passes a method at its limit and the code the limit does not apply to', function (array $files) use ($run): void {
    [$findings] = $run($files);

    expect($findings)->toBe([]);
})->with('methods inside their limit');

it('keeps every method in app inside the limits the guide states', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();
    $guard = new MethodSizeGuard($inspector, $root, 'app', 'app/AGENTS.md', 'Controllers');
    $findings = $guard->findings();

    expect($inspector->phpFiles($root . '/app'))->not->toBe([])
        ->and($guard->limits())->toBe([80, 40])
        ->and($findings)->toBe([], implode("\n", $guard->explain($findings)));
});
