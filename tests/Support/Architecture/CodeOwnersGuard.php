<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class CodeOwnersGuard
{
    public const array RULES = ['code-owners'];

    /**
     * @param list<string> $paths the files that carry the guards, relative to the root: the scripts, the caps file, the shared config, the pipeline files, the guard folder and the code-owners file itself
     */
    public function __construct(
        private string $codeOwnersFile,
        private array $paths,
        private bool $hostEnforced = false,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        if (! is_file($this->codeOwnersFile)) {
            return $this->hostEnforced ? [] : ['[code-owners] no code-owners file at ' . $this->codeOwnersFile . ': GitHub reads one in the repository root, in docs or in the host folder; on a host without the file, name the default reviewers and the merge check in the profile and pass hostEnforced'];
        }

        return $this->inCodeOwners((string) file_get_contents($this->codeOwnersFile));
    }

    /**
     * @return list<string>
     */
    public function inCodeOwners(string $text): array
    {
        $rules = $this->rules($text);
        $violations = [];

        foreach ($this->paths as $path) {
            $owners = null;

            foreach ($rules as [$pattern, $ruleOwners]) {
                if (preg_match($this->regex($pattern), $path) === 1) {
                    $owners = $ruleOwners;
                }
            }

            if ($owners === null) {
                $violations[] = '[code-owners] ' . $path . " is covered by no code-owners rule: an author could edit it, or write the override tag, without the owner's review";
            } elseif ($owners === []) {
                $violations[] = '[code-owners] ' . $path . ' is matched last by a rule with no owner: that rule takes the file out of the review';
            }
        }

        return $violations;
    }

    /**
     * @return list<array{string, list<string>}>
     */
    private function rules(string $text): array
    {
        $rules = [];

        foreach (explode("\n", $text) as $line) {
            $line = mb_trim((string) preg_replace('/(^|\s)#.*$/', '', $line));

            if ($line === '' || str_starts_with($line, '[') || str_starts_with($line, '^[')) {
                continue;
            }

            $parts = preg_split('/\s+/', $line);
            $parts = $parts === false ? [] : $parts;
            $rules[] = [(string) array_shift($parts), array_values(array_filter($parts, static fn (string $owner): bool => str_contains($owner, '@')))];
        }

        return $rules;
    }

    private function regex(string $pattern): string
    {
        $directory = str_ends_with($pattern, '/');
        $trimmed = mb_trim($pattern, '/');
        $anchored = str_starts_with($pattern, '/') || str_contains($trimmed, '/');
        $body = '';
        $tokens = preg_split('#(\*\*/|/\*\*|\*\*|\*|\?)#', $trimmed, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        foreach ($tokens === false ? [] : $tokens as $token) {
            $body .= match ($token) {
                '**/' => '(?:.*/)?',
                '/**' => '/.*',
                '**' => '.*',
                '*' => '[^/]*',
                '?' => '[^/]',
                default => preg_quote($token, '#'),
            };
        }

        return '#^' . ($anchored ? '' : '(?:.*/)?') . $body . ($directory ? '/.*' : '(?:/.*)?') . '$#';
    }
}
