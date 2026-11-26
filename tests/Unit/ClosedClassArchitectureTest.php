<?php

declare(strict_types=1);

use Tests\Support\Architecture\ClosedClassGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Narrow;
use Tests\Support\SourceSandbox;

uses()->group('arch');

$run = static function (array $files, string $prefix = 'source-sandbox-'): array {
    $tree = ['app/.gitkeep' => ''];

    foreach (Narrow::stringMap($files) as $path => $declaration) {
        $tree[$path] = str_contains($declaration, '<?php') ? $declaration : "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Modules\\Orders\\Support;\n\n{$declaration}\n";
    }

    $root = SourceSandbox::create($tree, $prefix);

    try {
        $guard = new ClosedClassGuard(new PhpFileInspector(), $root, 'app');
        $violations = $guard->violations();

        return [$violations, $guard->explain($violations)];
    } finally {
        SourceSandbox::remove($root);
    }
};

dataset('classes that are open', [
    'a concrete class that is not final' => [['app/Modules/Orders/Support/Cart.php' => 'class Cart {}'], ['open-class App\Modules\Orders\Support\Cart'], 'App\Modules\Orders\Support\Cart is not final: declare it `final`'],
    'a readonly class that is not final' => [['app/Modules/Orders/Support/Money.php' => 'readonly class Money {}'], ['open-class App\Modules\Orders\Support\Money'], 'is not final'],
    'a concrete parent that another class extends' => [['app/Modules/Orders/Support/Cart.php' => 'class Cart {}', 'app/Modules/Orders/Support/GiftCart.php' => 'final class GiftCart extends Cart {}'], ['extended-concrete App\Modules\Orders\Support\Cart', 'open-class App\Modules\Orders\Support\Cart'], 'make the parent `abstract`'],
    'a final parent that another class extends' => [['app/Modules/Orders/Support/Cart.php' => 'final class Cart {}', 'app/Modules/Orders/Support/GiftCart.php' => 'final class GiftCart extends Cart {}'], ['extended-concrete App\Modules\Orders\Support\Cart'], 'is concrete and another class extends it'],
    'a tagged parent that another class extends: nothing exempts a concrete parent' => [['app/Modules/Orders/Support/Cart.php' => "/** @final */\nclass Cart {}", 'app/Modules/Orders/Support/GiftCart.php' => 'final class GiftCart extends Cart {}'], ['extended-concrete App\Modules\Orders\Support\Cart'], 'make the parent `abstract`'],
    'the tag on a final class' => [['app/Modules/Orders/Support/Cart.php' => "/** @final */\nfinal class Cart {}"], ['stale-open App\Modules\Orders\Support\Cart'], 'carries the docblock tag `@final` and is final or abstract: delete the tag'],
    'the tag on an abstract class' => [['app/Modules/Orders/Support/Base.php' => "/** @final */\nabstract class Base {}"], ['stale-open App\Modules\Orders\Support\Base'], 'delete the tag'],
    'a file the parser cannot read' => [['app/Broken.php' => "<?php\nfinal class Broken {\n"], ['unreadable app/Broken.php: Syntax error, unexpected EOF on line 3'], 'unreadable app/Broken.php'],
]);

it('fails a concrete class that is open, a concrete class that is extended and a tag that no longer holds', function (array $files, array $entries, string $words) use ($run): void {
    [$violations, $explained] = $run($files);

    expect($violations)->toBe($entries)
        ->and(implode("\n", $explained))->toContain($words);
})->with('classes that are open');

dataset('classes that are closed', [
    'a final class and a final readonly class' => [['app/Modules/Orders/Support/Cart.php' => 'final class Cart {}', 'app/Modules/Orders/Support/Money.php' => 'final readonly class Money {}']],
    'an abstract base with a final child' => [['app/Modules/Orders/Support/Base.php' => 'abstract class Base {}', 'app/Modules/Orders/Support/Cart.php' => 'final class Cart extends Base {}']],
    'an interface, a trait and an enum' => [['app/Modules/Orders/Support/Shape.php' => 'interface Shape {}', 'app/Modules/Orders/Support/HasShape.php' => 'trait HasShape {}', 'app/Modules/Orders/Support/Mode.php' => "enum Mode: string { case FAST = 'fast'; }"]],
    'an anonymous class' => [['app/Modules/Orders/Support/Maker.php' => 'final class Maker { public function make(): object { return new class {}; } }']],
    'a class named like the keyword' => [['app/Modules/Orders/Support/AbstractFactory.php' => 'final class AbstractFactory {}']],
    'a concrete class that stays open, with the tag and a decision row' => [['app/Modules/Orders/Support/Cart.php' => "/**\n * @final decision row 12: the framework proxies it\n */\nclass Cart {}"]],
    'a child of a vendor class that is not ours' => [['app/Modules/Orders/Support/Failure.php' => 'final class Failure extends \RuntimeException {}']],
    'no code' => [[]],
]);

it('accepts final classes, abstract bases, other kinds of type and a concrete class that carries the tag', function (array $files) use ($run): void {
    [$violations] = $run($files);

    expect($violations)->toBe([]);
})->with('classes that are closed');

it('reads a tree under a dot directory', function () use ($run): void {
    [$violations] = $run(['app/Modules/Orders/Support/Cart.php' => 'class Cart {}'], '.checkout-');

    expect($violations)->toBe(['open-class App\Modules\Orders\Support\Cart']);
});

it('keeps every concrete class in app final, or tagged as a decision', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();

    expect($inspector->phpFiles($root . '/app'))->not->toBe([])
        ->and(new ClosedClassGuard($inspector, $root, 'app')->violations())->toBe([]);
});
