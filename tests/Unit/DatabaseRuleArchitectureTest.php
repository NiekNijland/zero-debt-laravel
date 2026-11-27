<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Validation\Rule;
use Tests\Support\Architecture\DatabaseRuleGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('database rules that skip the model', [
    'a table name' => ["<?php\nuse Illuminate\\Validation\\Rule;\n\$r = Rule::exists('users', 'id');\n", 'without a model class'],
    'a table name by the fully qualified facade' => ['<?php
$r = ' . Rule::class . '::unique(\'invoices\', \'title\');
', 'without a model class'],
    'the table of a new model' => ["<?php\nuse App\\Models\\Invoice;\nuse Illuminate\\Validation\\Rule;\n\$r = Rule::exists(new Invoice()->getTable(), 'id');\n", 'without a model class'],
    'a class that is not a model' => ["<?php\nuse Illuminate\\Validation\\Rule;\n\$r = Rule::exists(Rule::class, 'id');\n", 'without a model class'],
    'a class that does not exist' => ["<?php\nuse Illuminate\\Validation\\Rule;\n\$r = Rule::unique(App\\Models\\Missing::class, 'id');\n", 'without a model class'],
    'a variable' => ["<?php\nuse Illuminate\\Validation\\Rule;\n\$r = Rule::unique(\$model, 'id');\n", 'without a model class'],
    'a table name by an aliased facade' => ["<?php\nuse Illuminate\\Validation\\Rule as R;\n\$r = R::exists('users', 'id');\n", 'without a model class'],
    'a rule object built by hand' => ["<?php\nuse Illuminate\\Validation\\Rules\\Exists;\n\$r = new Exists('users', 'id');\n", 'new Exists()'],
    'a unique rule object built by hand and fully qualified' => ["<?php\n\$r = new \\Illuminate\\Validation\\Rules\\Unique('users', 'email');\n", 'new Unique()'],
    'a string rule' => ["<?php\n\$rules = ['locale' => ['required', 'exists:locales,code']];\n", "the string rule 'exists:'"],
    'a string rule in double quotes after a pipe' => ["<?php\n\$rules = ['name' => \"required|unique:invoices,title\"];\n", "the string rule 'unique:'"],
    'a string rule with an interpolated table' => ["<?php\n\$rules = ['id' => [\"exists:{\$table},id\"]];\n", "the string rule 'exists:'"],
]);

it('detects an existence or uniqueness rule that skips the model class', function (string $source, string $reason): void {
    $violations = new DatabaseRuleGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($reason)
        ->and($violations[0])->toContain('Model::class');
})->with('database rules that skip the model');

it('accepts a rule on a model class and the near misses of a string rule', function (): void {
    $source = <<<'PHP'
        <?php
        use App\Models\User;
        use Illuminate\Support\Facades\Storage;
        use Illuminate\Validation\Rule;
        $rules = [
            Rule::exists(User::class, 'id'),
            Rule::unique(User::class, 'email')->ignore($id),
            Rule::in(['unique', 'exists']),
            'in:exists,unique',
            'required_unless:exists,1',
        ];
        $exists = Storage::exists('unique') && $query->exists() && $collection->unique('id')->isNotEmpty();
        PHP;

    expect(new DatabaseRuleGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source))->toBe([]);
});

it('reads a tree under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create([
        'app/Bad.php' => '<?php
$r = ' . Rule::class . '::exists(\'users\', \'id\');
',
        'app/Good.php' => '<?php
$r = ' . Rule::class . '::exists(' . User::class . '::class, \'id\');
',
    ], prefix: '.checkout-');

    try {
        $violations = new DatabaseRuleGuard(new PhpFileInspector(), $root, 'app')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Bad.php:2: Rule::exists() without a model class');
});

it('writes every existence and uniqueness rule in app on its model class', function (): void {
    expect(new DatabaseRuleGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->violations())->toBe([]);
});
