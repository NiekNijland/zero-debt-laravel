<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Tests\Support\Globs;

final readonly class ContainerBindingGuard
{
    public function __construct(
        private string $root,
        private string $folder,
        private string $namespace,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];
        $files = Globs::of($this->root . '/' . $this->folder . '/*.php');

        if ($files === []) {
            $violations[] = $this->folder . ': no provider was read, so the guard has read nothing: is the folder renamed?';
        }

        foreach ($files as $file) {
            foreach ($this->inSource((string) file_get_contents($file)) as $message) {
                $violations[] = mb_substr($file, mb_strlen($this->root) + 1) . ': ' . $message;
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        preg_match_all('/^use ([\w\\\\]+)(?: as (\w+))?;$/m', $source, $uses, PREG_SET_ORDER);
        $imports = [];

        foreach ($uses as $use) {
            $imports[$use[2] ?? basename(str_replace('\\', '/', $use[1]))] = $use[1];
        }

        preg_match_all('/->(?:scoped|singleton|bind)\(\s*(\w+)::class\s*(?:,\s*(\w+)::class\s*)?\)/', $source, $calls, PREG_SET_ORDER);
        $messages = [];

        foreach ($calls as $call) {
            $class = $imports[$call[1]] ?? $call[1];

            if (str_starts_with($class, $this->namespace)) {
                $messages[] = 'binds ' . $class . ' in a provider: declare the binding on the class with #[Scoped], #[Singleton] or #[Bind]';
            }
        }

        return $messages;
    }
}
