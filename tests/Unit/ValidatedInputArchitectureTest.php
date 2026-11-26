<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\ValidatedInputGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('raw input reads', [
    'input' => ['<?php $a = $request->input(\'name\');'],
    'typed reader' => ['<?php $a = $request->string(\'name\')->toString();'],
    'query' => ['<?php $a = $request->query(\'tab\');'],
    'safe' => ['<?php $a = $request->safe()->only([\'a\']);'],
    'boolean' => ['<?php $a = $request->boolean(\'enabled\');'],
    'filled' => ['<?php if ($request->filled(\'code\')) {}'],
    'file' => ['<?php $a = $request->file(\'image\');'],
    'all' => ['<?php $a = $request->all();'],
    'except' => ['<?php $a = $request->except(\'token\');'],
    'helper' => ['<?php $a = request()->input(\'name\');'],
    'helper with a key' => ['<?php $a = request(\'name\');'],
    'facade' => ['<?php use Illuminate\Support\Facades\Request; $a = Request::input(\'name\');'],
    'property' => ['<?php $a = $this->request->integer(\'page\');'],
]);

it('detects input a controller reads past its form request', function (string $source): void {
    $violations = new ValidatedInputGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Http/Controllers')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('past the form request', '$request->validated()', 'only validated data');
})->with('raw input reads');

dataset('validated input and what is not input', [
    'validated' => ['<?php $a = $request->validated();'],
    'validated with a key and default' => ['<?php $b = $request->validated(\'name\', \'default\');'],
    'a validated string accessor' => ['<?php $c = $request->validatedString(\'name\');'],
    'an accessor of the form request' => ['<?php $d = $request->searchFilter();'],
    'the user' => ['<?php $e = $request->user();'],
    'the url' => ['<?php $f = $request->url();'],
    'the session' => ['<?php $g = $request->session()->get(\'key\');'],
    'a header' => ['<?php $h = $request->header(\'Idempotency-Key\');'],
    'a request helper without a key' => ['<?php $i = request()->userAgent();'],
    'only on a collection' => ['<?php $j = $collection->only([\'a\']);'],
    'get on a query' => ['<?php $k = $query->get();'],
]);

it('accepts validated input, accessors of the form request and what is not input', function (string $source): void {
    expect(new ValidatedInputGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Http/Controllers')->inSource($source))->toBe([]);
})->with('validated input and what is not input');

it('reads a tree under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create([
        'app/Http/Controllers/BadController.php' => "<?php\n\$a = \$request->input('name');\n",
        'app/Http/Controllers/GoodController.php' => "<?php\n\$a = \$request->validated();\n",
    ], prefix: '.checkout-');

    try {
        $violations = new ValidatedInputGuard(new PhpFileInspector(), $root, 'app/Http/Controllers')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Http/Controllers/BadController.php:line 2: request input read past the form request');
});

it('reads request input in controllers only from validated data', function (): void {
    expect(new ValidatedInputGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Http/Controllers')->violations())->toBe([]);
});
