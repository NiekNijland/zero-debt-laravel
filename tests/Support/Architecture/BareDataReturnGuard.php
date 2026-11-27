<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\Contracts\BaseDataCollectable;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;
use Tests\Support\Globs;

final readonly class BareDataReturnGuard
{
    public function __construct(
        private string $root,
        private string $routes,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];
        $files = 0;

        foreach (Globs::of($this->root . '/' . $this->routes . '/*.php') as $file) {
            $files++;

            foreach (PhpCallReader::nonGetRouteControllers($file) as $route) {
                if (class_exists($route['controller']) && $this->isBare($this->invokeReturnType($route['controller']))) {
                    $violations[] = mb_substr($file, mb_strlen($this->root) + 1) . ': line ' . $route['line'] . ': ' . $route['controller'] . ' returns a bare Data object from a route that writes: return it in a JsonResponse with an explicit status';
                }
            }
        }

        if ($files === 0) {
            $violations[] = $this->routes . ': no route file was read, so the guard has read nothing: is the folder renamed?';
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function writingControllers(string $source): array
    {
        return TemporarySource::with($source, static fn (string $path): array => array_column(PhpCallReader::nonGetRouteControllers($path), 'controller'));
    }

    public function isBare(?ReflectionType $type): bool
    {
        return array_any($type instanceof ReflectionUnionType ? $type->getTypes() : [$type], fn (?ReflectionType $part): bool => $part instanceof ReflectionNamedType && ! $part->isBuiltin() && (
            is_subclass_of($part->getName(), BaseData::class)
            || is_subclass_of($part->getName(), BaseDataCollectable::class)
            || in_array($part->getName(), [DataCollection::class, PaginatedDataCollection::class], true)
        ));
    }

    /**
     * @param class-string $class
     */
    private function invokeReturnType(string $class): ?ReflectionType
    {
        foreach (new ReflectionClass($class)->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getName() === '__invoke') {
                return $method->getReturnType();
            }
        }

        return null;
    }
}
