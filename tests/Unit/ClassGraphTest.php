<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Tests\Support\Architecture\ClassGraph;

uses()->group('arch');

$source = "<?php\nnamespace App;\nuse Illuminate\\Contracts\\Queue\\ShouldQueue;\ninterface Keyed extends ShouldQueue {}\ntrait Limits { public int \$tries = 3; #[Marker] public function limit(): int { return 1; } }\nabstract class Base implements Keyed { public string \$name = 'x'; public function base(): void {} }\n#[Marker]\nfinal class Job extends Base { use Limits; public function __construct(public readonly int \$id, private string \$hidden = 'a') {} }\nfinal class Plain { public string \$bare; }\n\$anonymous = new class {};\n";

it('lists the concrete classes, anonymous ones included, and skips abstract classes, interfaces and traits', function () use ($source): void {
    $names = array_map(static fn (array $info): string => $info['name'], ClassGraph::of(['a.php' => $source])->concreteClasses());

    expect($names)->toBe(['App\Job', 'App\Plain', 'an anonymous class on line 10']);
});

it('reaches a contract through a parent, a sub-interface and the framework', function () use ($source): void {
    $graph = ClassGraph::of(['a.php' => $source]);
    $job = $graph->concreteClasses()[0];
    $plain = $graph->concreteClasses()[1];

    expect($graph->reaches($job, [ShouldQueue::class]))->toBe(ShouldQueue::class)
        ->and($graph->reaches($job, ['App\Keyed']))->toBe('App\Keyed')
        ->and($graph->reaches($job, ['App\Other']))->toBeNull()
        ->and($graph->reaches($plain, [ShouldQueue::class]))->toBeNull();
});

it('finds a method on the class, a parent or a trait, and a framework method', function () use ($source): void {
    $graph = ClassGraph::of(['a.php' => $source]);
    $job = $graph->concreteClasses()[0];

    expect($graph->hasMethod($job, '__construct'))->toBeTrue()
        ->and($graph->hasMethod($job, 'base'))->toBeTrue()
        ->and($graph->hasMethod($job, 'LIMIT'))->toBeTrue()
        ->and($graph->hasMethod($job, 'missing'))->toBeFalse()
        ->and($graph->hasMethod($graph->concreteClasses()[1], 'base'))->toBeFalse();
});

it('tells a property set per object from a property with a default', function () use ($source): void {
    $graph = ClassGraph::of(['a.php' => $source]);
    $job = $graph->concreteClasses()[0];
    $plain = $graph->concreteClasses()[1];

    expect($graph->property($job, 'id'))->toBe('instance')
        ->and($graph->property($job, 'hidden'))->toBe('instance')
        ->and($graph->property($job, 'name'))->toBe('default')
        ->and($graph->property($job, 'tries'))->toBe('default')
        ->and($graph->property($plain, 'bare'))->toBe('instance')
        ->and($graph->property($job, 'missing'))->toBeNull();
});

it('reads a property of a vendor parent from its own file', function (): void {
    $graph = ClassGraph::of(['a.php' => "<?php\nnamespace App;\nfinal class Model extends \\Illuminate\\Database\\Eloquent\\Model {}\n"]);

    expect($graph->property($graph->concreteClasses()[0], 'incrementing'))->toBe('default')
        ->and($graph->property($graph->concreteClasses()[0], 'nothingLikeThis'))->toBeNull();
});

it('finds an attribute on the class by the last segment of its name', function () use ($source): void {
    $graph = ClassGraph::of(['a.php' => $source]);

    expect($graph->hasAttribute($graph->concreteClasses()[0], 'Marker'))->toBeTrue()
        ->and($graph->hasAttribute($graph->concreteClasses()[1], 'Marker'))->toBeFalse();
});
