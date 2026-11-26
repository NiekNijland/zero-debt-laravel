<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Symfony\Component\Process\Process;

final readonly class BlameRevisionsGuard
{
    public const array RULES = ['blame-revisions'];

    public function __construct(
        private string $repository,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(string $revisionsFile): array
    {
        if (! is_file($revisionsFile)) {
            return ['[blame-revisions] the file of formatting commits that blame skips does not exist: ' . $revisionsFile];
        }

        return $this->inRevisions((string) file_get_contents($revisionsFile));
    }

    /**
     * @return list<string>
     */
    public function inRevisions(string $text): array
    {
        $violations = [];

        foreach (explode("\n", $text) as $line) {
            $revision = mb_trim((string) preg_replace('/\s*#.*$/', '', $line));

            if ($revision === '') {
                continue;
            }

            if (preg_match('/^[0-9a-f]{40}$/', $revision) !== 1) {
                $violations[] = '[blame-revisions] `' . $revision . '` is not a full commit hash';
            } elseif (! $this->reachable($revision)) {
                $violations[] = '[blame-revisions] ' . $revision . ' is not a commit reachable from the head: a rewritten history leaves a hash that blame cannot skip';
            }
        }

        return $violations;
    }

    private function reachable(string $revision): bool
    {
        return new Process(['git', 'merge-base', '--is-ancestor', $revision, 'HEAD'], $this->repository)->run() === 0;
    }
}
