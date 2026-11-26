<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Tests\Support\Globs;

final readonly class MigrationClassGuard
{
    /**
     * @param list<class-string> $liveEnums
     */
    public function __construct(
        private string $root,
        private string $folder,
        private array $liveEnums,
        private string $applicationNamespace,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $files = Globs::of($this->root . '/' . $this->folder . '/*.php');
        $violations = $files === [] ? [$this->folder . ': no migration was read, so the guard has read nothing: is the folder renamed?'] : [];

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

        foreach ($this->liveEnums as $enum) {
            $short = mb_substr($enum, (int) mb_strrpos($enum, '\\') + 1);

            if (preg_match('/' . preg_quote($enum, '/') . '\b|(?<![\w\\\\])' . preg_quote($short, '/') . '::/', $source) === 1) {
                $messages[] = 'a migration uses the live permission or role class: write the name as a string, so a rename of the class cannot break a migration that has run';
            }
        }

        if (preg_match('/(?<![A-Za-z0-9_])' . preg_quote($this->applicationNamespace, '/') . '/', $source) === 1) {
            $messages[] = 'a migration names an application class: write the table, the column and the value as they are, so a move of the class cannot break a migration that has run';
        }

        return $messages;
    }
}
