<?php

declare(strict_types=1);

namespace Tests\Support\PHPStan;

use Override;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

final class PestArchFunction implements DynamicFunctionReturnTypeExtension
{
    #[Override]
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'arch';
    }

    #[Override]
    public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type
    {
        return new ObjectType(PestArchCall::class);
    }
}
