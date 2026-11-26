<?php

declare(strict_types=1);

namespace Tests\Support\PHPStan;

use Illuminate\Database\Seeder;
use Override;
use ReflectionMethod;
use ShipMonk\PHPStan\DeadCode\Provider\ReflectionBasedMemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;
use Spatie\LaravelData\Data;

final class LaravelMagicUsage extends ReflectionBasedMemberUsageProvider
{
    #[Override]
    protected function shouldMarkMethodAsUsed(ReflectionMethod $method): ?VirtualUsageData
    {
        $class = $method->getDeclaringClass();
        $name = $method->getName();

        if ($name === '__construct' && str_starts_with($class->getName(), 'App\\')) {
            return VirtualUsageData::withNote('The container builds the classes of the application and injects their constructors.');
        }

        if (str_starts_with($class->getName(), 'App\\Policies\\') && $method->isPublic()) {
            return VirtualUsageData::withNote('Policy abilities are checked by name from routes and the gate.');
        }

        if ($class->isSubclassOf(Data::class) && ($name === 'rules' || str_starts_with($name, 'from'))) {
            return VirtualUsageData::withNote('Spatie Data calls its creation methods and reads rules() when it validates.');
        }

        if ($class->isSubclassOf(Seeder::class) && $name === 'run') {
            return VirtualUsageData::withNote('The seed command calls run().');
        }

        if (str_starts_with($class->getName(), 'App\\Http\\Middleware\\') && $name === 'handle') {
            return VirtualUsageData::withNote('The router calls handle() of a middleware.');
        }

        if (str_starts_with($class->getName(), 'App\\Http\\Controllers\\') && $name === '__invoke') {
            return VirtualUsageData::withNote('The router calls the invokable controller.');
        }

        if (str_starts_with($class->getName(), 'App\\Providers\\') && $name === 'boot') {
            return VirtualUsageData::withNote('The container boots providers.');
        }

        if (str_starts_with($class->getName(), 'App\\Http\\Requests\\') && $name === 'rules') {
            return VirtualUsageData::withNote('A form request is validated through rules().');
        }

        if (str_starts_with($class->getName(), 'Database\\Factories\\') && $name === 'definition') {
            return VirtualUsageData::withNote('A factory calls definition().');
        }

        if (str_starts_with($class->getName(), 'App\\Models\\') && $name === 'casts') {
            return VirtualUsageData::withNote('A model calls casts() when it builds its attributes.');
        }

        return null;
    }
}
