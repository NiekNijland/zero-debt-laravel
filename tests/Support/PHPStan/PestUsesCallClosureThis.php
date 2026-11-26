<?php

declare(strict_types=1);

namespace Tests\Support\PHPStan;

use Override;
use Pest\PendingCalls\UsesCall;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Type\MethodParameterClosureThisExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Tests\TestCase;

final class PestUsesCallClosureThis implements MethodParameterClosureThisExtension
{
    #[Override]
    public function isMethodSupported(MethodReflection $methodReflection, ParameterReflection $parameter): bool
    {
        return $methodReflection->getDeclaringClass()->getName() === UsesCall::class
            && in_array($methodReflection->getName(), ['beforeEach', 'afterEach'], true);
    }

    #[Override]
    public function getClosureThisTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, ParameterReflection $parameter, Scope $scope): Type
    {
        return new ObjectType(TestCase::class);
    }
}
