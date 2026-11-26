<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;

final readonly class NamedRouteUrlGuard
{
    private const array REQUEST_METHODS = [
        'get' => 'GET',
        'getjson' => 'GET',
        'post' => 'POST',
        'postjson' => 'POST',
        'put' => 'PUT',
        'putjson' => 'PUT',
        'patch' => 'PATCH',
        'patchjson' => 'PATCH',
        'delete' => 'DELETE',
        'deletejson' => 'DELETE',
        'json' => null,
        'call' => null,
    ];

    private const string PEST_FUNCTIONS = 'pest\\laravel\\';

    /**
     * @param list<array{methods: list<string>, uri: string, name: string}> $routes
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $tests,
        private array $routes,
    ) {
    }

    /**
     * @return array<string, list<string>>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->tests) as $file) {
            $found = $this->inSource((string) file_get_contents($file));

            if ($found !== []) {
                $violations[mb_substr($file, mb_strlen($this->root) + 1)] = $found;
            }
        }

        ksort($violations);

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function inSource(string $source): array
    {
        $found = [];
        $statements = PhpSource::resolved($source);

        foreach (new NodeFinder()->find($statements, static fn (Node $node): bool => $node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof FuncCall) as $call) {
            $verb = $this->verb($call);

            if ($verb === null || ! ($call instanceof MethodCall || $call instanceof NullsafeMethodCall || $call instanceof FuncCall)) {
                continue;
            }

            $arguments = $call->getArgs();
            $method = self::REQUEST_METHODS[$verb];
            $url = $arguments[$method === null ? 1 : 0]->value ?? null;

            if ($method === null) {
                $written = $arguments[0]->value ?? null;
                $method = $written instanceof String_ ? mb_strtoupper($written->value) : null;
            }

            $name = $method === null || $url === null ? null : $this->namedRoute($method, $this->parts($url));

            if ($name !== null) {
                $found[] = 'line ' . $call->name->getStartLine() . ': a hard-coded URL of the route ' . $name . ": write route('" . $name . "')";
            }
        }

        return $found;
    }

    private function verb(Node $call): ?string
    {
        if ($call instanceof CallLike && $call->isFirstClassCallable()) {
            return null;
        }

        $verb = null;

        if (($call instanceof MethodCall || $call instanceof NullsafeMethodCall) && $call->name instanceof Identifier) {
            $verb = $call->name->toLowerString();
        }

        if ($call instanceof FuncCall && $call->name instanceof Name && str_starts_with(mb_ltrim($call->name->toLowerString(), '\\'), self::PEST_FUNCTIONS)) {
            $verb = mb_substr(mb_ltrim($call->name->toLowerString(), '\\'), mb_strlen(self::PEST_FUNCTIONS));
        }

        return $verb !== null && array_key_exists($verb, self::REQUEST_METHODS) ? $verb : null;
    }

    /**
     * @return list<string|null>
     */
    private function parts(Expr $url): array
    {
        if ($url instanceof String_) {
            return [$url->value];
        }

        if ($url instanceof InterpolatedString) {
            return array_map(static fn (Expr|InterpolatedStringPart $part): ?string => $part instanceof InterpolatedStringPart ? $part->value : null, array_values($url->parts));
        }

        if ($url instanceof Concat) {
            return [...$this->parts($url->left), ...$this->parts($url->right)];
        }

        return [null];
    }

    /**
     * @param list<string|null> $parts
     */
    private function namedRoute(string $method, array $parts): ?string
    {
        $first = $parts[0] ?? null;

        if ($first === null || ! str_starts_with($first, '/')) {
            return null;
        }

        $hasQuery = str_contains($first, '?');
        $endsInAParameter = count($parts) === 2 && $parts[1] === null && ! $hasQuery && $first !== '/' && str_ends_with($first, '/');

        if (count($parts) > 1 && ! $hasQuery && ! $endsInAParameter) {
            return null;
        }

        $path = mb_trim(explode('?', $first)[0], '/');

        foreach ($this->routes as $route) {
            $uri = mb_trim($route['uri'], '/');
            $matches = $endsInAParameter ? preg_match('#^' . preg_quote($path, '#') . '/\{[^/{}]+\}$#', $uri) === 1 : $uri === $path;

            if ($matches && in_array($method, $route['methods'], true)) {
                return $route['name'];
            }
        }

        return null;
    }
}
