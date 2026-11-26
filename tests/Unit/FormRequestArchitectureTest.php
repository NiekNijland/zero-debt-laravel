<?php

declare(strict_types=1);

use Tests\Support\Architecture\FormRequestGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('form requests out of line', [
    'authorize that only returns true' => ["<?php\nnamespace App\\Http\\Requests;\nfinal class StoreRequest extends FormRequest {\n    public function authorize(): bool { return true; }\n    public function rules(): array { return ['name' => ['required']]; }\n}\n", '4: authorize() that only returns true: remove the method'],
    'authorize that only returns true, in capitals' => ["<?php\nnamespace App\\Http\\Requests;\nfinal class StoreRequest extends FormRequest {\n    public function authorize()\n    {\n        return TRUE;\n    }\n}\n", '4: authorize() that only returns true: remove the method'],
    'rules as a pipe string' => ["<?php\nnamespace App\\Http\\Requests;\nfinal class StoreRequest extends FormRequest {\n    public function rules(): array\n    {\n        return [\n            'email' => 'required|email',\n        ];\n    }\n}\n", "7: the rules of a field as one string: write them as an array, ['required', 'email']"],
    'a pipe string held in a variable' => ["<?php\nnamespace App\\Http\\Requests;\nfinal class StoreRequest extends FormRequest {\n    public function rules(): array\n    {\n        \$email = 'required|email';\n        return ['email' => \$email];\n    }\n}\n", 'held in a variable or joined'],
    'a pipe string joined from two texts' => ["<?php\nnamespace App\\Http\\Requests;\nfinal class StoreRequest extends FormRequest {\n    public function rules(): array { return ['email' => 'required|' . 'email']; }\n}\n", 'held in a variable or joined'],
    'one rule as a string' => ["<?php\nnamespace App\\Http\\Requests;\nfinal class StoreRequest extends FormRequest {\n    public function rules(): array { return ['token' => 'required', 'name' => ['required']]; }\n}\n", "4: the rules of a field as one string: write them as an array, ['required']"],
    'a string rule in a merged array' => ["<?php\nnamespace App\\Http\\Requests;\nfinal class StoreRequest extends FormRequest {\n    public function rules(): array { return [...parent::rules(), 'images.*' => 'image']; }\n}\n", "4: the rules of a field as one string: write them as an array, ['image']"],
]);

it('detects a form request that authorizes everyone or writes its rules as a string', function (string $source, string $reason): void {
    $violations = new FormRequestGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Http/Requests')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($reason);
})->with('form requests out of line');

it('accepts rules as arrays, a check that depends on the input and an override that switches off a parent check', function (): void {
    $source = <<<'PHP'
        <?php
        namespace App\Http\Requests;
        use Override;
        final class StoreRequest extends FormRequest
        {
            public function authorize(): bool { return $this->user()?->can('update', $this->route('order')) ?? false; }
            public function rules(): array { return ['name' => ['required', 'string'], 'status' => ['required', Rule::enum(OrderStatus::class)], 'tags' => ['array']]; }
            public function messages(): array { return ['name.required' => 'common.required']; }
        }
        final class QuickStoreRequest extends StoreParentRequest
        {
            #[Override]
            public function authorize(): bool { return true; }
            public function rules(): array { return ['name' => parent::rules()['name']]; }
        }
        final class PlainRequest extends FormRequest
        {
            public function rules(): array { return []; }
        }
        PHP;

    expect(new FormRequestGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Http/Requests')->inSource($source))->toBe([]);
});

it('reads a tree under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create([
        'app/Http/Requests/BadRequest.php' => "<?php\nfinal class BadRequest extends FormRequest {\n    public function authorize(): bool { return true; }\n}\n",
        'app/Http/Requests/GoodRequest.php' => "<?php\nfinal class GoodRequest extends FormRequest {\n    public function rules(): array { return ['a' => ['required']]; }\n}\n",
    ], prefix: '.checkout-');

    try {
        $violations = new FormRequestGuard(new PhpFileInspector(), $root, 'app/Http/Requests')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Http/Requests/BadRequest.php:3: authorize() that only returns true');
});

it('keeps every form request in app to array rules and to an authorize() that checks something', function (): void {
    expect(new FormRequestGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Http/Requests')->violations())->toBe([]);
});
