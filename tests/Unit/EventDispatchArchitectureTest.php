<?php

declare(strict_types=1);
use Tests\Support\Architecture\EventDispatchGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('events of the application handed to the dispatcher', [
    'the facade' => ["<?php\nnamespace App\\Actions;\nuse App\\Events\\OrderPlacedEvent;\nuse Illuminate\\Support\\Facades\\Event;\nEvent::dispatch(new OrderPlacedEvent(\$order));\n", 'Event::dispatch(new OrderPlacedEvent(...))'],
    'the facade with named arguments' => ["<?php\nnamespace App\\Actions;\nuse Illuminate\\Support\\Facades\\Event;\nEvent::dispatch(new \\App\\Events\\OrderPlacedEvent(order: \$order, user: \$user));\n", 'Event::dispatch(new OrderPlacedEvent(...))'],
    'the global alias' => ["<?php\nnamespace App\\Actions;\n\\Eve" . "nt::dispatch(new \\App\\Events\\OrderPlacedEvent());\n", 'Event::dispatch(new OrderPlacedEvent(...))'],
    'an event in the same namespace' => ["<?php\nnamespace App\\Events;\nuse Illuminate\\Support\\Facades\\Event;\nEvent::dispatch(new OrderPlacedEvent(\$order));\n", 'Event::dispatch(new OrderPlacedEvent(...))'],
    'an injected dispatcher property' => ["<?php\nnamespace App\\Actions;\nuse App\\Events\\OrderPlacedEvent;\nuse Illuminate\\Contracts\\Events\\Dispatcher;\nfinal class Place { public function __construct(private Dispatcher \$events) {} public function run(\$order): void { \$this->events->dispatch(new OrderPlacedEvent(\$order)); } }\n", 'the dispatcher(new OrderPlacedEvent(...))'],
    'a dispatcher received as a parameter' => ["<?php\nnamespace App\\Actions;\nuse App\\Events\\OrderPlacedEvent;\nuse Illuminate\\Contracts\\Events\\Dispatcher;\nfinal class Place { public function run(Dispatcher \$events, \$order): void { \$events->dispatch(new OrderPlacedEvent(\$order)); } }\n", 'the dispatcher(new OrderPlacedEvent(...))'],
    'a typed property that is assigned later' => ["<?php\nnamespace App\\Actions;\nuse Illuminate\\Events\\Dispatcher;\nfinal class Place { private Dispatcher \$bus; public function run(\$order): void { \$this->bus->dispatch(new \\App\\Events\\OrderPlacedEvent(\$order)); } }\n", 'the dispatcher(new OrderPlacedEvent(...))'],
    'the container string' => ["<?php\nnamespace App\\Actions;\napp('events')->dispatch(new \\App\\Events\\OrderPlacedEvent());\n", 'the dispatcher(new OrderPlacedEvent(...))'],
]);

it('detects an event of the application that is built for the dispatcher', function (string $source, string $written): void {
    $violations = new EventDispatchGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($written)
        ->and($violations[0])->toContain('write OrderPlacedEvent::dispatch(...)');
})->with('events of the application handed to the dispatcher');

it('accepts the static form, an event built earlier, an event of a vendor, a dispatcher that is not the event facade and the global helper that the analyser owns', function (): void {
    $source = <<<'PHP'
        <?php
        namespace App\Actions;
        use App\Events\OrderPlacedEvent;
        use Illuminate\Auth\Events\Lockout;
        use Illuminate\Contracts\Events\Dispatcher;
        use Illuminate\Support\Facades\Bus;
        use Illuminate\Support\Facades\Event;
        final class Place
        {
            public function __construct(private Dispatcher $events, private $untyped) {}

            public function run($event, $order): void
            {
                OrderPlacedEvent::dispatch($order);
                Event::dispatch($event);
                Event::dispatch(clone $event);
                Event::dispatch(new Lockout($request));
                $this->events->dispatch($event);
                $this->events->dispatch(new Lockout($request));
                $this->untyped->dispatch(new OrderPlacedEvent($order));
                app('bus')->dispatch(new \App\Jobs\ExportInvoiceJob($order));
                Bus::dispatch(new \App\Jobs\ExportInvoiceJob($order));
                $this->analytics->event(new \App\ValueObjects\Purchase());
                event(new OrderPlacedEvent($order));
            }
        }
        PHP;

    expect(new EventDispatchGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source))->toBe([]);
});

it('reads a tree under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create(['app/Fires.php' => "<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Event;\nEvent::dispatch(new \\App\\Events\\OrderPlacedEvent());\n", 'app/Clean.php' => "<?php\n"], prefix: '.checkout-');

    try {
        $violations = new EventDispatchGuard(new PhpFileInspector(), $root, 'app')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Fires.php:4: Event::dispatch(new OrderPlacedEvent(...))');
});

it('dispatches every event of the application in app through its own dispatch()', function (): void {
    expect(new EventDispatchGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->violations())->toBe([]);
});
