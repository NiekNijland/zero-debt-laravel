<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\Support\Globs;

final readonly class TestTierGuard
{
    private const string FRAMEWORK_TESTING = 'Illuminate\\Foundation\\Testing\\';

    private const array FRAMEWORK_BOOTING = ['TestCase', 'RefreshDatabase', 'LazilyRefreshDatabase', 'DatabaseTransactions', 'DatabaseMigrations', 'DatabaseTruncation', 'WithCachedConfig', 'WithCachedRoutes'];

    private const array OTHER_BOOTING = ['Tests\\' . 'TestCase', 'Orchestra\\Testbench\\' . 'TestCase'];

    private const array FACTORY_FORBIDDEN_NAMES = [
        DB::class,
        Schema::class,
    ];

    private const array DATABASE_NAME_PREFIXES = [
        'Illuminate\Database\\',
        DB::class,
        Schema::class,
        'Spatie\Permission\Models\\',
    ];

    /**
     * @param list<string> $pureLayers
     * @param list<string> $factoryLayers
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $testFolder,
        private string $unitFolder,
        private string $bootstrapFile,
        private array $pureLayers,
        private array $factoryLayers,
    ) {
    }

    /**
     * @return array<string, list<string>>
     */
    public function unitTestViolations(): array
    {
        $booting = array_merge($this->bootingRoots(), $this->bootingSupportTypes());
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->unitFolder) as $file) {
            $hits = array_values(array_intersect($this->inspector->referencedNames($file), $booting));

            if ($hits !== []) {
                $violations[$this->relative($file)] = $hits;
            }
        }

        foreach ($this->phpFilesMatching($this->root . '/' . $this->testFolder, '/->in\(|\b(uses|extend)\(|->use\(/') as $file) {
            $isGlobalFile = $this->relative($file) === $this->bootstrapFile;

            foreach (PestBindingReader::pestBindings($file) as $binding) {
                $coversUnit = $binding['targets'] === null
                    ? $isGlobalFile
                    : array_filter($binding['targets'], $this->coversUnitDirectory(...)) !== [];
                $hits = array_values(array_intersect($binding['bound'], $booting));

                if ($coversUnit && $hits !== []) {
                    $violations[$this->relative($file)] = array_values(array_unique([...($violations[$this->relative($file)] ?? []), ...$hits]));
                }
            }
        }

        ksort($violations);

        return $violations;
    }

    /**
     * @return array<string, list<string>>
     */
    public function pureLayerViolations(): array
    {
        $violations = [];
        $directories = array_map(fn (string $layer): array => Globs::of($this->root . '/' . $layer, GLOB_ONLYDIR), $this->pureLayers);

        if (array_merge(...$directories) === []) {
            throw new InvalidArgumentException('None of the pure layers ' . implode(', ', $this->pureLayers) . ' exists: a guard that reads no file passes without checking');
        }

        foreach ($this->pureLayers as $index => $layer) {
            foreach ($directories[$index] as $directory) {
                foreach ($this->inspector->phpFiles($directory) as $file) {
                    $hits = in_array($layer, $this->factoryLayers, true)
                        ? $this->namesOutsideFactories($file)
                        : array_values(array_filter($this->inspector->referencedNames($file), $this->isDatabaseLayerName(...)));

                    if ($hits !== []) {
                        sort($hits);
                        $violations[$this->relative($file)] = $hits;
                    }
                }
            }
        }

        ksort($violations);

        return $violations;
    }

    public function isDatabaseLayerName(string $name): bool
    {
        foreach (self::DATABASE_NAME_PREFIXES as $prefix) {
            if ($name === mb_rtrim($prefix, '\\') || str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return str_starts_with($name, 'App\\') && preg_match('/\\\\Models\\\\/', $name) === 1;
    }

    /**
     * @return list<string>
     */
    private function namesOutsideFactories(string $file): array
    {
        $names = $this->inspector->referencedNamesByFactory($file);

        return array_values(array_unique([
            ...array_filter($names['outside'], $this->isDatabaseLayerName(...)),
            ...array_filter($names['inside'], static fn (string $name): bool => in_array($name, self::FACTORY_FORBIDDEN_NAMES, true)),
        ]));
    }

    private function coversUnitDirectory(string $target): bool
    {
        return $target === '*' || preg_match('#(^|/)' . preg_quote(basename($this->unitFolder), '#') . '(/|$)#', $target) === 1;
    }

    /**
     * @return list<string>
     */
    private function bootingRoots(): array
    {
        return [...self::OTHER_BOOTING, ...array_map(static fn (string $name): string => self::FRAMEWORK_TESTING . $name, self::FRAMEWORK_BOOTING)];
    }

    /**
     * @return list<string>
     */
    private function bootingSupportTypes(): array
    {
        $related = [];

        foreach ($this->supportFiles() as $file) {
            foreach ($this->inspector->declaredTypes($file) as $type => $names) {
                $related[$type] = $names;
            }
        }

        $roots = $this->bootingRoots();
        $booting = $roots;

        do {
            $grew = false;

            foreach ($related as $type => $names) {
                if (! in_array($type, $booting, true) && array_intersect($names, $booting) !== []) {
                    $booting[] = $type;
                    $grew = true;
                }
            }
        } while ($grew);

        return array_values(array_diff($booting, $roots));
    }

    /**
     * @return list<string>
     */
    private function supportFiles(): array
    {
        return $this->phpFilesMatching($this->root . '/' . $this->testFolder, '/^\s*(abstract\s+|final\s+|readonly\s+)*(class|trait|interface)\s/m');
    }

    /**
     * @return list<string>
     */
    private function phpFilesMatching(string $directory, string $pattern): array
    {
        return array_values(array_filter(
            $this->inspector->phpFiles($directory),
            static fn (string $file): bool => preg_match($pattern, (string) file_get_contents($file)) === 1,
        ));
    }

    private function relative(string $file): string
    {
        return mb_ltrim(mb_substr($file, mb_strlen($this->root)), '/');
    }
}
