<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class TestLocationGuard
{
    /**
     * @param list<string> $tiers
     * @param array<string, string> $homes
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $appFolder,
        private string $testFolder,
        private array $tiers,
        private array $homes,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $appRoot = $this->root . '/' . $this->appFolder . '/';
        $classes = [];
        $folders = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->appFolder) as $file) {
            $relative = mb_substr($file, mb_strlen($appRoot));
            $folder = dirname($relative) === '.' ? '' : dirname($relative);
            $classes[basename($relative, '.php')][$folder] = true;

            for ($ancestor = $folder; $ancestor !== '' && $ancestor !== '.'; $ancestor = dirname($ancestor)) {
                $folders[$ancestor] = true;
            }
        }

        $violations = [];

        foreach ($this->tiers as $tier) {
            $tierRoot = $this->root . '/' . $this->testFolder . '/' . $tier . '/';

            foreach ($this->inspector->phpFiles($tierRoot, mayBeAbsent: true) as $file) {
                if (! str_ends_with($file, 'Test.php')) {
                    continue;
                }

                $relative = mb_substr($file, mb_strlen($tierRoot));
                $folder = dirname($relative) === '.' ? '' : dirname($relative);
                $name = basename($relative, 'Test.php');
                $path = $this->testFolder . '/' . $tier . '/' . $relative;

                if (isset($classes[$name])) {
                    if (! isset($classes[$name][$folder])) {
                        $violations[] = $path . ': the class ' . $name . ' lives in ' . $this->appFolder . '/' . implode(' and ' . $this->appFolder . '/', array_keys($classes[$name])) . ', the test belongs in the same folder of ' . $this->testFolder . '/' . $tier;
                    }

                    continue;
                }

                $home = explode('/', $folder)[0];

                if ($folder !== '' && ! isset($folders[$folder]) && ! isset($this->homes[$home])) {
                    $violations[] = $path . ': ' . $this->appFolder . '/' . $folder . ' does not exist, the test belongs in the folder of the code it covers';
                }
            }
        }

        sort($violations);

        return $violations;
    }
}
