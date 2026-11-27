<?php

declare(strict_types=1);
use Tests\Support\Architecture\EventDispatchGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('an event of the application dispatched in the old forms', [
    'the Dispatchable trait on an event class' => ["<?php\nnamespace App\\Events;\nuse Illuminate\\Foundation\\Events\\Dispatchable;\nfinal class OrderPlacedEvent { use Dispatchable; }\n", 'uses Illuminate\\Foundation\\Events\\Dispatchable'],
    'the trait written fully qualified' => ["<?php\nnamespace App\\Events;\nfinal class OrderPlacedEvent { use \\Illuminate\\Foundation\\Events\\Dispatchable; }\n", 'uses Illuminate\\Foundation\\Events\\Dispatchable'],
    'the trait on a class that is no event by name' => ["<?php\nnamespace App\\Support;\nuse Illuminate\\Foundation\\Events\\Dispatchable;\nfinal class Placed { use Dispatchable; }\n", 'uses Illuminate\\Foundation\\Events\\Dispatchable'],
    'a dispatch() of its own on an event class' => ["<?php\nnamespace App\\Events;\nfinal class OrderPlacedEvent { public static function dispatch(int \$id): void {} }\n", 'declares its own dispatch()'],
    'the dispatch of an event class' => ["<?php\nnamespace App\\Actions;\nuse App\\Events\\OrderPlacedEvent;\nOrderPlacedEvent::dispatch(\$order);\n", 'write Event::dispatch(new OrderPlacedEvent(...))'],
    'the dispatch of an event class written fully qualified' => ["<?php\nnamespace App\\Actions;\n\\App\\Events\\OrderPlacedEvent::dispatch(\$order, \$user);\n", 'write Event::dispatch(new OrderPlacedEvent(...))'],
    'the dispatch of an event in the same namespace' => ["<?php\nnamespace App\\Events;\nOrderPlacedEvent::dispatch(\$order);\n", 'write Event::dispatch(new OrderPlacedEvent(...))'],
    'an injected dispatcher property' => ["<?php\nnamespace App\\Actions;\nuse App\\Events\\OrderPlacedEvent;\nuse Illuminate\\Contracts\\Events\\Dispatcher;\nfinal class Place { public function __construct(private Dispatcher \$events) {} public function run(\$order): void { \$this->events->dispatch(new OrderPlacedEvent(\$order)); } }\n", 'the dispatcher(new OrderPlacedEvent(...)) is no facade call'],
    'a dispatcher received as a parameter' => ["<?php\nnamespace App\\Actions;\nuse App\\Events\\OrderPlacedEvent;\nuse Illuminate\\Contracts\\Events\\Dispatcher;\nfinal class Place { public function run(Dispatcher \$events, \$order): void { \$events->dispatch(new OrderPlacedEvent(\$order)); } }\n", 'the dispatcher(new OrderPlacedEvent(...)) is no facade call'],
    'a typed property that is assigned later' => ["<?php\nnamespace App\\Actions;\nuse Illuminate\\Events\\Dispatcher;\nfinal class Place { private Dispatcher \$bus; public function run(\$order): void { \$this->bus->dispatch(new \\App\\Events\\OrderPlacedEvent(\$order)); } }\n", 'the dispatcher(new OrderPlacedEvent(...)) is no facade call'],
    'the container string' => ["<?php\nnamespace App\\Actions;\napp('events')->dispatch(new \\App\\Events\\OrderPlacedEvent());\n", 'the dispatcher(new OrderPlacedEvent(...)) is no facade call'],
]);

it('detects an event class that dispatches itself and a call that is not the facade with the event built at the call', function (string $source, string $written): void {
    $violations = new EventDispatchGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($written);
})->with('an event of the application dispatched in the old forms');

it('accepts the facade with the event built at the call, a vendor event, the dispatch of a job, an event class without the trait and the global helper that the analyser owns', function (): void {
    $source = <<<'PHP'
        <?php
        namespace App\Actions;
        use App\Events\OrderPlacedEvent;
        use App\Jobs\ExportInvoiceJob;
        use Illuminate\Auth\Events\Lockout;
        use Illuminate\Contracts\Events\Dispatcher;
        use Illuminate\Foundation\Bus\Dispatchable;
        use Illuminate\Support\Facades\Bus;
        use Illuminate\Support\Facades\Event;
        final class Place
        {
            use Dispatchable;

            public function __construct(private Dispatcher $events, private $untyped) {}

            public function run($event, $order): void
            {
                Event::dispatch(new OrderPlacedEvent($order));
                Event::dispatch(new OrderPlacedEvent(order: $order));
                Event::dispatch($event);
                Event::dispatch(new Lockout($request));
                $this->events->dispatch($event);
                $this->events->dispatch(new Lockout($request));
                $this->untyped->dispatch(new OrderPlacedEvent($order));
                ExportInvoiceJob::dispatch($order);
                app('bus')->dispatch(new ExportInvoiceJob($order));
                Bus::dispatch(new ExportInvoiceJob($order));
                $this->analytics->event(new \App\ValueObjects\Purchase());
                event(new OrderPlacedEvent($order));
            }
        }
        final class OrderPlacedEvent { public function __construct(public int $order) {} public function dispatch(): void {} }
        PHP;

    expect(new EventDispatchGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source))->toBe([]);
});

it('reads a tree under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create(['app/Fires.php' => "<?php\nnamespace App;\n\\App\\Events\\OrderPlacedEvent::dispatch(\$order);\n", 'app/Clean.php' => "<?php\n"], prefix: '.checkout-');

    try {
        $violations = new EventDispatchGuard(new PhpFileInspector(), $root, 'app')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Fires.php:3: OrderPlacedEvent::dispatch(...)');
});

it('dispatches every event of the application in app through the facade, with an event class that has no Dispatchable trait', function (): void {
    expect(new EventDispatchGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->violations())->toBe([]);
});
