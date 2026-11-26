<?php

declare(strict_types=1);

namespace Tests\Support\PHPStan;

use Override;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Type\FunctionParameterClosureThisExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Tests\TestCase;

final class PestClosureThis implements FunctionParameterClosureThisExtension
{
    #[Override]
    public function isFunctionSupported(FunctionReflection $functionReflection, ParameterReflection $parameter): bool
    {
        return in_array($functionReflection->getName(), ['beforeEach', 'afterEach', 'it'], true) && $parameter->getName() === 'closure';
    }

    #[Override]
    public function getClosureThisTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, ParameterReflection $parameter, Scope $scope): Type
    {
        return new ObjectType(TestCase::class);
    }
}
