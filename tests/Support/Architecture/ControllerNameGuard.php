<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class ControllerNameGuard
{
    /**
     * @param list<string> $verbs
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $folder,
        private array $verbs,
        private string $namespace,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function verbsOf(string $guide): array
    {
        $verbs = [];
        $inTable = false;

        foreach (explode("\n", $guide) as $line) {
            $cells = array_map(trim(...), explode('|', mb_trim($line, " |\t")));

            if (! str_starts_with(mb_trim($line), '|')) {
                $inTable = false;

                continue;
            }

            if ($cells[0] === 'Verb') {
                $inTable = true;

                continue;
            }

            if ($inTable && preg_match('/^`([A-Z][A-Za-z]+)`$/', $cells[0], $match) === 1) {
                $verbs[] = $match[1];
            }
        }

        return $verbs;
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];
        $used = [];
        $controllers = 0;

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->folder) as $file) {
            foreach (array_keys($this->inspector->declaredTypes($file)) as $type) {
                $controllers++;
                $message = $this->inType($type);

                if ($message === null) {
                    $used[basename(str_replace('\\', '/', $type), 'Controller')] = true;
                } else {
                    $violations[] = $type . ': ' . $message;
                }
            }
        }

        if ($controllers === 0) {
            $violations[] = $this->folder . ': no controller was read, so the guard has read nothing: is the folder renamed?';
        }

        foreach (array_diff($this->verbs, array_keys($used)) as $verb) {
            $violations[] = 'the verb ' . $verb . ' is on the list and no controller carries it: remove it from the list';
        }

        return $violations;
    }

    public function inType(string $type): ?string
    {
        $short = basename(str_replace('\\', '/', $type));
        $namespace = mb_substr($type, 0, -mb_strlen($short) - 1);
        $verb = mb_substr($short, 0, -mb_strlen('Controller'));

        if ($namespace === $this->namespace) {
            return 'a controller sits in the controller root: put it in a folder named after what it acts on';
        }

        if (! str_ends_with($short, 'Controller')) {
            return 'a controller is named <Verb>Controller';
        }

        if (! in_array($verb, $this->verbs, true)) {
            return 'the verb ' . $verb . ' is not one of ' . implode(', ', $this->verbs) . ': name the controller after one of them';
        }

        return null;
    }
}
