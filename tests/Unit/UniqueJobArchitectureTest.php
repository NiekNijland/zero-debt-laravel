<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\UniqueJobGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

$head = "<?php\nnamespace App;\nuse Illuminate\\Contracts\\Queue\\ShouldBeUnique;\nuse Illuminate\\Contracts\\Queue\\ShouldBeUniqueUntilProcessing;\nuse Illuminate\\Contracts\\Queue\\ShouldQueue;\n";

dataset('unique jobs without their own key', [
    'ShouldBeUnique' => ["<?php\nnamespace App;\nuse Illuminate\\Contracts\\Queue\\ShouldBeUnique;\nuse Illuminate\\Contracts\\Queue\\ShouldQueue;\nfinal class ExportJob implements ShouldBeUnique, ShouldQueue { public function handle(): void {} }\n", 'App\\ExportJob implements ShouldBeUnique without uniqueId()'],
    'ShouldBeUniqueUntilProcessing' => ["<?php\nnamespace App;\nuse Illuminate\\Contracts\\Queue\\ShouldBeUniqueUntilProcessing;\nfinal class FulfillJob implements ShouldBeUniqueUntilProcessing { public int \$uniqueFor = 60; }\n", 'App\\FulfillJob implements ShouldBeUniqueUntilProcessing without uniqueId()'],
    'a fully qualified contract' => ["<?php\nnamespace App;\nfinal class QualifiedJob implements \\Illuminate\\Contracts\\Queue\\ShouldBeUnique {}\n", 'App\\QualifiedJob implements ShouldBeUnique without uniqueId()'],
    'an aliased contract' => ["<?php\nnamespace App;\nuse Illuminate\\Contracts\\Queue\\ShouldBeUnique as Unique;\nfinal class AliasJob implements Unique {}\n", 'App\\AliasJob implements ShouldBeUnique without uniqueId()'],
    'a group import of the contract' => ["<?php\nnamespace App;\nuse Illuminate\\Contracts\\Queue\\{ShouldBeUnique, ShouldQueue};\nfinal class GroupJob implements ShouldQueue, ShouldBeUnique {}\n", 'App\\GroupJob implements ShouldBeUnique without uniqueId()'],
    'a sub-interface of the contract' => [$head . "interface KeyedJob extends ShouldBeUnique {}\nfinal class SubJob implements KeyedJob {}\n", 'App\\SubJob implements ShouldBeUnique without uniqueId()'],
    'a parent that already holds the contract' => [$head . "abstract class BaseJob implements ShouldBeUnique {}\nfinal class ChildJob extends BaseJob {}\n", 'App\\ChildJob implements ShouldBeUnique without uniqueId()'],
    'a sub-interface that only declares the method' => [$head . "interface KeyedJob extends ShouldBeUnique { public function uniqueId(): string; }\nfinal class DeclaredJob implements KeyedJob {}\n", 'App\\DeclaredJob implements ShouldBeUnique without uniqueId()'],
    'a trait without the method' => [$head . "trait Retries { public int \$tries = 3; }\nfinal class TraitJob implements ShouldBeUnique { use Retries; }\n", 'App\\TraitJob implements ShouldBeUnique without uniqueId()'],
    'a property with a default, one lock per class' => [$head . "final class FixedJob implements ShouldBeUnique { public string \$uniqueId = 'x'; }\n", 'a $uniqueId property that has a default'],
    'an anonymous class' => [$head . "\$job = new class implements ShouldBeUnique {};\n", 'an anonymous class on line'],
]);

it('detects a unique job that does not supply its own key', function (string $source, string $reason): void {
    $violations = new UniqueJobGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($reason)
        ->and($violations[0])->toContain('public function uniqueId(): string');
})->with('unique jobs without their own key');

dataset('unique jobs with their own key and near misses', [
    'the method' => [$head . "final class ExportJob implements ShouldBeUnique, ShouldQueue { public function uniqueId(): string { return 'x'; } }\n"],
    'a job that is not unique' => [$head . "final class PlainJob implements ShouldQueue { public function handle(): void {} }\n"],
    'a class named after the contract in another namespace' => [$head . "final class OtherJob implements \\App\\Contracts\\ShouldBeUnique {}\n"],
    'the method from a trait' => [$head . "trait HasKey { public function uniqueId(): string { return 'k'; } }\nfinal class TraitJob implements ShouldBeUnique { use HasKey; }\n"],
    'the method from a trait that uses a trait' => [$head . "trait Inner { public function uniqueId(): string { return 'k'; } }\ntrait Outer { use Inner; }\nfinal class NestedJob implements ShouldBeUnique { use Outer; }\n"],
    'the method from a parent' => [$head . "abstract class BaseJob implements ShouldBeUnique { public function uniqueId(): string { return 'k'; } }\nfinal class ChildJob extends BaseJob {}\n"],
    'an abstract base whose children carry the key' => [$head . "abstract class BaseJob implements ShouldBeUnique {}\nfinal class ChildJob extends BaseJob { public function uniqueId(): string { return 'k'; } }\n"],
    'the method behind a sub-interface' => [$head . "interface KeyedJob extends ShouldBeUnique {}\nfinal class SubJob implements KeyedJob { public function uniqueId(): string { return 'k'; } }\n"],
    'a promoted property set per instance' => [$head . "final class PromotedJob implements ShouldBeUnique { public function __construct(public readonly string \$uniqueId) {} }\n"],
    'a property without a default' => [$head . "final class PropertyJob implements ShouldBeUnique { public string \$uniqueId; }\n"],
    'an anonymous class with the method' => [$head . "\$job = new class implements ShouldBeUnique { public function uniqueId(): string { return 'k'; } };\n"],
    'a sub-interface alone' => [$head . "interface KeyedJob extends ShouldBeUnique {}\n"],
]);

it('accepts a unique job with its own key, a job that is not unique and the near misses', function (string $source): void {
    expect(new UniqueJobGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source))->toBe([]);
})->with('unique jobs with their own key and near misses');

it('reads a tree under a dot directory and names the file relative to the root', function () use ($head): void {
    $root = SourceSandbox::create([
        'app/Jobs/ExportJob.php' => $head . "final class ExportJob implements ShouldBeUnique {}\n",
        'app/Jobs/KeyedJob.php' => $head . "final class KeyedJob implements ShouldBeUnique { public function uniqueId(): string { return 'k'; } }\n",
    ], prefix: '.checkout-');

    try {
        $violations = new UniqueJobGuard(new PhpFileInspector(), $root, 'app')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Jobs/ExportJob.php: App\\ExportJob implements ShouldBeUnique without uniqueId()');
});

it('gives every unique job in app its own key', function (): void {
    expect(new UniqueJobGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->violations())->toBe([]);
});
