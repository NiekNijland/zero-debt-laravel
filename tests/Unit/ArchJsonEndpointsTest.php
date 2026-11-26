<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Note\StoreController;
use App\Http\Controllers\Web\Page\ShowController;
use App\Modules\Notes\Data\NoteData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Response;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;
use Tests\Support\Architecture\BareDataReturnGuard;
use Tests\Support\Architecture\JsonEndpointGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('JSON answers written by hand', [
    'the helper' => ['<?php return response()->json([]);', 1],
    'the helper with a leading backslash' => ['<?php return \\response()->json($x);', 1],
    'the facade' => ['<?php return Response::json($x);', 1],
    'the qualified facade' => ['<?php return ' . Response::class . '::json($x);', 1],
    'all four' => ['<?php return response()->json([]); return \response()->json($x); return Response::json($x); return ' . Response::class . '::json($x);', 4],
]);

it('detects a JSON answer written by hand with response()->json()', function (string $source, int $count): void {
    $guard = new JsonEndpointGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'app/Http/Controllers');

    expect($guard->jsonHelperLines($source))->toHaveCount($count);
})->with('JSON answers written by hand');

dataset('JSON answers that pass', [
    'the response class with data' => ['<?php return new JsonResponse($data, 201); return new JsonResponse(status: 201);'],
    'a class that is named like the facade' => ['<?php return WebhookResponse::json($x);'],
    'a method called json on another object' => ['<?php return $this->response()->json();'],
]);

it('accepts a JsonResponse built with new and a json() call on something else', function (string $source): void {
    $guard = new JsonEndpointGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'app/Http/Controllers');

    expect($guard->jsonHelperLines($source))->toBe([]);
})->with('JSON answers that pass');

dataset('JsonResponse arguments and their type', [
    'an array literal' => ["return new JsonResponse(['a' => 1]);", '', ['array']],
    'an array held in a variable' => ['$rows = [1]; return new JsonResponse($rows);', '', ['array']],
    'a new Data object ' => ['return new JsonResponse(new XData(1), 409);', '', [NoteData::class]],
    'a variable that holds Data ' => ['$data = XData::from($x); return new JsonResponse($data);', '', [NoteData::class]],
    'a named argument ' => ['return new JsonResponse(data: XData::from($x));', '', [NoteData::class]],
    'a collection with its class ' => ['return new JsonResponse(XData::collect($x, \\' . DataCollection::class . '::class));', '', [DataCollection::class]],
    'a helper that returns an array' => ["return new JsonResponse(Arr::only(\$x, ['a']));", '', [null]],
    'a parameter without a type known' => ['return new JsonResponse($x);', '', [null]],
    'a parameter typed Data ' => ['return new JsonResponse($x);', 'XData $x', [NoteData::class]],
    'the result of a service call' => ['return new JsonResponse($this->service->run());', '', [null]],
    'no argument at all' => ['return new JsonResponse(status: 201); return new JsonResponse();', '', []],
]);

it('reads the type of what a controller puts in its JsonResponse', function (string $body, string $parameters, array $types): void {
    $source = "<?php namespace App\\Http\\Controllers; use Illuminate\\Http\\JsonResponse; use App\\Modules\\Notes\\Data\\NoteData as XData; use Illuminate\\Support\\Arr; final class C { public function __construct(private readonly \\stdClass \$service) {} public function __invoke({$parameters}): JsonResponse { {$body} } }";
    $guard = new JsonEndpointGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'app/Http/Controllers');

    expect($guard->responseArgumentTypes($source))->toBe($types);
})->with('JsonResponse arguments and their type');

it('says an array and a type that is not known are not Data, and a collection of Data is', function (): void {
    $guard = new JsonEndpointGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'app/Http/Controllers');

    expect($guard->notData('array'))->toBeTrue()
        ->and($guard->notData(null))->toBeTrue()
        ->and($guard->notData(DataCollection::class . '|array'))->toBeTrue()
        ->and($guard->notData(DataCollection::class . '|' . PaginatedDataCollection::class))->toBeFalse();
});

it('names the file and the replacement of a hand-written JSON answer, in a checkout under a dot directory', function (): void {
    $root = SourceSandbox::create([
        'app/Support/Hand.php' => "<?php\nnamespace App\\Support;\nfinal class Hand { public function a() { return response()->json([]); } }\n",
        'app/Support/Clean.php' => "<?php\nnamespace App\\Support;\nfinal class Clean {}\n",
        'app/Http/Controllers/.gitkeep' => '',
    ], prefix: '.checkout-');

    try {
        $violations = new JsonEndpointGuard(new PhpFileInspector(), $root, 'app', 'app/Http/Controllers')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Support/Hand.php: line 3: a JSON answer that is not a Data object: return a Data object');
});

it('returns a Data object from every JSON endpoint: no response()->json, nothing but Data in a controller JsonResponse, no array return', function (): void {
    expect(new JsonEndpointGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'app/Http/Controllers')->violations())->toBe([]);
});

dataset('routes that write', [
    'a post route' => ['<?php Route::post("b", B::class)->name("b");', ['B']],
    'a match with put' => ['<?php Route::match(["put"], "d", D::class);', ['D']],
    'a delete route in a group' => ['<?php Route::middleware("x")->group(function () { Route::delete("e", E::class); });', ['E']],
    'a closure is no controller' => ['<?php Route::patch("f", fn () => 1);', []],
    'a get route and a match with get and head' => ['<?php Route::get("a", A::class); Route::match(["get", "head"], "c", C::class);', []],
]);

it('reads the controller of every route that writes and of no other', function (string $source, array $controllers): void {
    expect(new BareDataReturnGuard(dirname(__DIR__, 2), 'routes')->writingControllers($source))->toBe($controllers);
})->with('routes that write');

it('calls a bare Data object, or a union that holds a collection, bare, and a JsonResponse or a redirect not', function (): void {
    $guard = new BareDataReturnGuard(dirname(__DIR__, 2), 'routes');

    expect($guard->isBare(new ReflectionFunction(static fn (): Data => throw new LogicException())->getReturnType()))->toBeTrue()
        ->and($guard->isBare(new ReflectionFunction(static fn (): JsonResponse|DataCollection => throw new LogicException())->getReturnType()))->toBeTrue()
        ->and($guard->isBare(new ReflectionFunction(static fn (): JsonResponse => throw new LogicException())->getReturnType()))->toBeFalse()
        ->and($guard->isBare(new ReflectionFunction(static fn (): RedirectResponse|JsonResponse => throw new LogicException())->getReturnType()))->toBeFalse();
});

it('names the file and the controller of a route that writes a bare Data object, under a dot directory', function (): void {
    $root = SourceSandbox::create(['routes/api.php' => '<?php
Route::post(\'a\', ' . ShowController::class . '::class);
Route::post(\'b\', ' . StoreController::class . '::class);
'], prefix: '.checkout-');

    try {
        $violations = new BareDataReturnGuard($root, 'routes')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('routes/api.php: line 2: App\\Http\\Controllers\\Web\\Page\\ShowController returns a bare Data object from a route that writes');
});

it('fails a folder with no route that writes, because it has read nothing', function (): void {
    $root = SourceSandbox::create(['routes/api.php' => "<?php\nRoute::get('a', A::class);\n"], prefix: '.checkout-');

    try {
        $violations = new BareDataReturnGuard($root, 'routes')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('no route that writes was read');
});

it('gives every route that writes an explicit status', function (): void {
    expect(new BareDataReturnGuard(dirname(__DIR__, 2), 'routes')->violations())->toBe([]);
});
