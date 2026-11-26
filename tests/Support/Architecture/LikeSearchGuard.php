<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class LikeSearchGuard
{
    private const string CASE_INSENSITIVE = '/\bilike\b|\blower\s*\(.*\)\s*like\b/is';

    private const array LIKE_METHODS = ['wherelike', 'orwherelike', 'wherenotlike', 'orwherenotlike'];

    private const string HELPER_CALL = '/Like::\w+(\((?:[^()]|(?1))*\))/';

    private const string CLOSURE = '/^(static\s+)?(fn|function)\b/';

    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $source,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->source) as $file) {
            foreach ($this->inSource((string) file_get_contents($file)) as $violation) {
                $violations[] = mb_substr($file, mb_strlen($this->root) + 1) . ':' . $violation;
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $violations = [];

        foreach ($this->inspector->calls($source) as $call) {
            $arguments = array_values(array_filter($call['arguments'], static fn (string $argument): bool => preg_match(self::CLOSURE, $argument) !== 1));
            $insensitive = in_array(mb_strtolower($call['name']), self::LIKE_METHODS, true) || preg_match(self::CASE_INSENSITIVE, implode(', ', $arguments)) === 1;

            if (! $insensitive) {
                continue;
            }

            $arguments = array_map(static fn (string $argument): string => (string) preg_replace(self::HELPER_CALL, '', $argument), $arguments);

            if (! $this->builtByHand($arguments)) {
                continue;
            }

            $violations[] = $call['line'] . ': a case-insensitive LIKE with a pattern built by hand: write whereLike($column, Like::contains($term)), or for a prefix search over an expression index whereRaw(\'lower(col) like ?\', [Like::escape($term) . \'%\'])';
        }

        return array_values(array_unique($violations));
    }

    /**
     * @param list<string> $arguments
     */
    private function builtByHand(array $arguments): bool
    {
        foreach ($arguments as $index => $argument) {
            if ($index === 0 && preg_match('/\{\$|\$\{|%/', $argument) === 1) {
                return true;
            }

            if ($index > 0 && preg_match('/\$|\w\s*\(/', $argument) === 1) {
                return true;
            }
        }

        return false;
    }
}
