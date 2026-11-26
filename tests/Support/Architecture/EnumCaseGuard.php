<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\NodeFinder;

final readonly class EnumCaseGuard
{
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $folder,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];
        $enums = 0;

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->folder) as $file) {
            $source = (string) file_get_contents($file);
            $enums += $this->enumCount($source);

            foreach ($this->inSource($source) as $message) {
                $violations[] = mb_substr($file, mb_strlen($this->root) + 1) . ': ' . $message;
            }
        }

        if ($enums === 0) {
            $violations[] = $this->folder . ': no enum was read, so the guard has read nothing: is the folder renamed?';
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $violations = [];

        foreach (new NodeFinder()->findInstanceOf($this->statements($source), EnumCase::class) as $case) {
            $name = $case->name->toString();

            if ($name !== mb_strtoupper($name)) {
                $violations[] = 'line ' . $case->getStartLine() . ': the enum case ' . $name . ' is not UPPER_CASE: write it as ' . mb_strtoupper((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $name));
            }
        }

        return $violations;
    }

    public function enumCount(string $source): int
    {
        return count(new NodeFinder()->findInstanceOf($this->statements($source), Enum_::class));
    }

    /**
     * @return list<Stmt>
     */
    private function statements(string $source): array
    {
        return PhpSource::parse($source);
    }
}
