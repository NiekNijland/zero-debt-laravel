<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use InvalidArgumentException;
use PhpToken;
use Tests\Support\Globs;

final readonly class ScheduledOneServerGuard
{
    public function __construct(
        private string $root,
        private string $folder,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        if (! is_dir($this->root . '/' . $this->folder)) {
            throw new InvalidArgumentException('The directory ' . $this->root . '/' . $this->folder . ' does not exist: a guard over a missing folder would pass without reading a file');
        }

        $files = Globs::of($this->root . '/' . $this->folder . '/*.php');

        if ($files === []) {
            throw new InvalidArgumentException('The directory ' . $this->folder . ' holds no PHP file: is the schedule file renamed or moved?');
        }

        $violations = [];

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
        $messages = [];

        if (preg_match('/Schedule::call\(/', $source) === 1) {
            $messages[] = 'a closure is scheduled: make it a command, so the task has a name, a test and a lock';
        }

        foreach ($this->tasks($source) as $task) {
            if (! $task['onOneServer']) {
                $messages[] = 'line ' . $task['line'] . ': a scheduled task does not run on one server: end it with ->onOneServer(), or every server runs it';
            }
        }

        return $messages;
    }

    /**
     * @return list<array{line: int, onOneServer: bool}>
     */
    private function tasks(string $source): array
    {
        $tokens = array_values(array_filter(
            PhpToken::tokenize($source),
            static fn (PhpToken $token): bool => ! $token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
        ));
        $found = [];
        $current = null;
        $depth = 0;

        foreach ($tokens as $index => $token) {
            if ($current === null && $token->is(T_STRING) && $token->text === 'Schedule' && ($tokens[$index + 1] ?? null)?->is(T_DOUBLE_COLON) === true) {
                $current = ['line' => $token->line, 'onOneServer' => false];
            }

            if ($current === null) {
                continue;
            }

            if ($token->is(T_STRING) && $token->text === 'onOneServer' && ($tokens[$index - 1] ?? null)?->is(T_OBJECT_OPERATOR) === true) {
                $current['onOneServer'] = true;
            }

            $depth += match ($token->text) {
                '(', '[', '{' => 1,
                ')', ']', '}' => -1,
                default => 0,
            };

            if ($token->text === ';' && $depth === 0) {
                $found[] = $current;
                $current = null;
            }
        }

        return $found;
    }
}
