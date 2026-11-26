<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpToken;
use Tests\Support\RecursiveFiles;

final readonly class TestHelperFunctionGuard
{
    public function __construct(
        private string $testsDirectory,
    ) {
    }

    /**
     * @return array<string, list<string>>
     */
    public function violations(): array
    {
        $violations = [];

        foreach (RecursiveFiles::in($this->testsDirectory) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $functions = $this->declaredFunctions((string) file_get_contents($file->getPathname()));

            if ($functions !== []) {
                $violations[mb_substr($file->getPathname(), mb_strlen($this->testsDirectory) + 1)] = $functions;
            }
        }

        ksort($violations);

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function declaredFunctions(string $source): array
    {
        $tokens = array_values(array_filter(
            PhpToken::tokenize($source),
            static fn (PhpToken $token): bool => ! $token->isIgnorable(),
        ));

        $functions = [];
        $braces = [];
        $parentheses = 0;
        $classOpensAt = null;

        foreach ($tokens as $index => $token) {
            $previous = $tokens[$index - 1] ?? null;

            if ($token->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM]) && $previous?->is(T_DOUBLE_COLON) !== true) {
                $classOpensAt = $parentheses;
            } elseif ($token->text === '(') {
                $parentheses++;
            } elseif ($token->text === ')') {
                $parentheses--;
            } elseif ($token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                $braces[] = false;
            } elseif ($token->text === '{') {
                $braces[] = $classOpensAt === $parentheses;
                $classOpensAt = null;
            } elseif ($token->text === '}') {
                array_pop($braces);
            } elseif ($token->is(T_FUNCTION) && $previous?->is(T_USE) !== true) {
                $name = $tokens[$index + 1] ?? null;
                $name = $name?->text === '&' ? ($tokens[$index + 2] ?? null) : $name;

                if ($name?->is(T_STRING) === true && ($braces === [] || end($braces) === false)) {
                    $functions[] = $name->text;
                }
            } elseif ($token->is(T_CONST) && $previous?->is(T_USE) !== true) {
                $name = $tokens[$index + 1] ?? null;

                if ($name?->is(T_STRING) === true && ($braces === [] || end($braces) === false)) {
                    $functions[] = 'const ' . $name->text;
                }
            } elseif ($token->is(T_STRING) && mb_strtolower($token->text) === 'define' && ($tokens[$index + 1] ?? null)?->text === '(' && $previous?->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW]) !== true) {
                $name = $tokens[$index + 2] ?? null;

                if ($name?->is(T_CONSTANT_ENCAPSED_STRING) === true && ($braces === [] || end($braces) === false)) {
                    $functions[] = 'define ' . mb_trim($name->text, '\'"');
                }
            }
        }

        return $functions;
    }
}
