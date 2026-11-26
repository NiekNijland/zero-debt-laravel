<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Http\JsonResponse;
use PhpToken;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\Contracts\BaseDataCollectable;
use Tests\Support\ClassNames;

final readonly class JsonEndpointGuard
{
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $folder,
        private string $controllers,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $controllers = $this->root . '/' . $this->controllers . '/';
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->folder) as $path) {
            $isController = str_starts_with($path, $controllers);
            $lines = $this->jsonHelperLines((string) file_get_contents($path));

            if ($lines === [] && ! $isController) {
                continue;
            }

            $types = array_keys($this->inspector->declaredTypes($path));
            $returns = $isController && $types !== [] ? $this->invokeReturnType(ClassNames::of($types[0])) : null;
            if ($isController) {
                foreach (PhpNewArgumentTypes::firstArgumentTypesOfNew($path, JsonResponse::class) as $argument) {
                    if ($this->notData($argument['type'])) {
                        $lines[] = $argument['line'];
                    }
                }
            }

            foreach ($lines as $line) {
                $violations[] = mb_substr($path, mb_strlen($this->root) + 1) . ': line ' . $line . ': a JSON answer that is not a Data object: return a Data object and let the framework write the response';
            }

            if ($returns instanceof ReflectionNamedType && $returns->getName() === 'array') {
                $violations[] = $types[0] . ' returns an array: return a Data object';
            }
        }

        return $violations;
    }

    /**
     * @return list<int>
     */
    public function jsonHelperLines(string $source): array
    {
        $tokens = array_values(array_filter(
            PhpToken::tokenize($source),
            static fn (PhpToken $token): bool => ! $token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
        ));
        $name = static fn (?PhpToken $token): string => $token?->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) === true ? mb_strtolower(basename(str_replace('\\', '/', $token->text))) : '';
        $lines = [];

        foreach ($tokens as $index => $token) {
            $isHelper = $name($token) === 'response' && ($tokens[$index - 1] ?? null)?->is([T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION]) !== true && ($tokens[$index + 1] ?? null)?->text === '(' && ($tokens[$index + 2] ?? null)?->text === ')' && ($tokens[$index + 3] ?? null)?->is(T_OBJECT_OPERATOR) === true && $name($tokens[$index + 4] ?? null) === 'json';
            $isFacade = $name($token) === 'response' && ($tokens[$index + 1] ?? null)?->is(T_DOUBLE_COLON) === true && $name($tokens[$index + 2] ?? null) === 'json';

            if ($isHelper || $isFacade) {
                $lines[] = $token->line;
            }
        }

        return $lines;
    }

    /**
     * @return list<string|null>
     */
    public function responseArgumentTypes(string $source): array
    {
        return TemporarySource::with($source, static fn (string $path): array => array_column(PhpNewArgumentTypes::firstArgumentTypesOfNew($path, JsonResponse::class), 'type'));
    }

    public function notData(?string $type): bool
    {
        return $type === null || array_any(
            explode('|', $type),
            static fn (string $part): bool => ! is_a($part, BaseData::class, true) && ! is_a($part, BaseDataCollectable::class, true),
        );
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
