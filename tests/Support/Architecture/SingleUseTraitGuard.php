<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeFinder;

final readonly class SingleUseTraitGuard
{
    private const string MESSAGE = 'a trait used by exactly one class: move its members into that class, or into a parent or a collaborator; a trait is for code that several classes share';

    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $folder,
    ) {
    }

    /**
     * @param list<string> $sources
     * @return array<string, int>
     */
    public function traitUsers(array $sources): array
    {
        $declared = [];
        $users = [];
        $finder = new NodeFinder();

        foreach ($sources as $index => $source) {
            $statements = PhpSource::resolved($source);

            foreach ($finder->findInstanceOf($statements, ClassLike::class) as $class) {
                if ($class instanceof Trait_ && $class->namespacedName instanceof Name) {
                    $declared[] = $class->namespacedName->toString();
                }

                $user = $class->namespacedName?->toString() ?? $index . '@' . $class->getStartLine();

                foreach ($class->stmts as $statement) {
                    if (! $statement instanceof TraitUse) {
                        continue;
                    }

                    foreach ($statement->traits as $trait) {
                        $users[$trait->toString()][$user] = true;
                    }
                }
            }
        }

        $counts = [];

        foreach ($declared as $trait) {
            $counts[$trait] = count($users[$trait] ?? []);
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param list<string> $sources
     * @return list<string>
     */
    public function inSources(array $sources): array
    {
        $single = array_keys(array_filter($this->traitUsers($sources), static fn (int $users): bool => $users === 1));

        return array_map(static fn (string $trait): string => $trait . ': ' . self::MESSAGE, $single);
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $files = $this->inspector->phpFiles($this->root . '/' . $this->folder);

        return $this->inSources(array_map(static fn (string $file): string => (string) file_get_contents($file), $files));
    }
}
