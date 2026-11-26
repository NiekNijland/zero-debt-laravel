<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Tests\Support\Architecture\PhpFileInspector;

uses()->group('arch');

arch('models register their events in booted(), never through an observer attribute')
    ->expect('App')
    ->not->toHaveAttribute(ObservedBy::class);

arch('the application has no observer classes')
    ->expect('App')
    ->classes()
    ->not->toHaveSuffix('Observer');

it('never registers an observer with observe()', function (): void {
    $root = dirname(__DIR__, 2);
    $inspector = new PhpFileInspector();
    $offenders = [];

    foreach ($inspector->phpFiles($root . '/app') as $file) {
        foreach ($inspector->callsIn($file) as $call) {
            if ($call['kind'] === 'static' && $call['name'] === 'observe') {
                $offenders[] = mb_substr($file, mb_strlen($root) + 1) . ':' . $call['line'];
            }
        }
    }

    expect($offenders)->toBe([]);
});
