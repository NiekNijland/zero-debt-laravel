<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class ModuleLayoutGuard
{
    private const string WORD = '[A-Z][A-Za-z0-9]*';

    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $guide,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $path = $this->root . '/' . $this->guide;
        $text = is_file($path) ? (string) file_get_contents($path) : '';
        $moduleRoot = preg_match('/`([A-Za-z]+(?:\/[A-Za-z]+)*)\/<Domain>`/', $text, $found) === 1 ? $found[1] : null;
        $rules = $this->rules($text);

        if ($moduleRoot === null || $rules === []) {
            return ["{$this->guide} holds no table with the columns Folder, Holds and Name, or no folder of the form `<folder>/<Domain>`: the guard is generated from them, so write them as the guide does"];
        }

        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $moduleRoot) as $file) {
            $relative = mb_substr($file, mb_strlen($this->root . '/' . $moduleRoot) + 1);

            if (preg_match('#^' . self::WORD . '/(' . self::WORD . ')(?:/' . self::WORD . ')?/(' . self::WORD . ')\.php$#', $relative, $match) !== 1 || ! isset($rules[$match[1]])) {
                $violations[] = 'place ' . $moduleRoot . '/' . $relative;

                continue;
            }

            [$suffixes, $forbidden] = $rules[$match[1]];

            if (($suffixes !== [] && ! array_any($suffixes, static fn (string $suffix): bool => str_ends_with($match[2], $suffix))) || ($forbidden !== null && str_ends_with($match[2], $forbidden))) {
                $violations[] = 'name ' . $moduleRoot . '/' . $relative;
            }
        }

        sort($violations, SORT_STRING);

        return $violations;
    }

    /**
     * @param list<string> $violations
     * @return list<string>
     */
    public function explain(array $violations): array
    {
        return array_map(function (string $violation): string {
            [$kind, $file] = array_pad(explode(' ', $violation, 2), 2, '');

            return match ($kind) {
                'place' => "{$violation}: no folder of the module table in {$this->guide} holds a class here. A class sits in <Domain>/<Folder>/[<Subdomain>/]<Class>.php, never in the module root or a folder the table does not name, as the table says.",
                'name' => "{$violation}: the folder is right and the name is not. The name of a class in " . basename(dirname($file)) . " follows the Name column of the table in {$this->guide}.",
                default => $violation,
            };
        }, $violations);
    }

    /**
     * @return array<string, array{list<string>, string|null}>
     */
    private function rules(string $text): array
    {
        $rules = [];
        $inTable = false;

        foreach (explode("\n", $text) as $line) {
            $cells = array_map(trim(...), explode('|', mb_trim($line, " |\t")));

            if (! str_starts_with(mb_trim($line), '|')) {
                $inTable = false;

                continue;
            }

            if ($cells === ['Folder', 'Holds', 'Name']) {
                $inTable = true;

                continue;
            }

            if (! $inTable || count($cells) !== 3 || preg_match('/^[-: ]+$/', $cells[0]) === 1) {
                continue;
            }

            preg_match_all('/`<?X>?([A-Za-z]+)`/', $cells[2], $suffixes);
            $forbidden = preg_match('/never an? `(?:\.\.\.)?([A-Za-z]+)` suffix/', $cells[2], $never) === 1 ? $never[1] : null;
            $folders = array_map(static fn (string $folder): string => mb_trim($folder, '` '), explode(',', $cells[0]));

            foreach ($folders as $folder) {
                $own = array_values(array_filter($suffixes[1], static fn (string $suffix): bool => $suffix === $folder || $suffix . 's' === $folder));
                $rules[$folder] = [count($folders) === 1 ? $suffixes[1] : $own, $forbidden];
            }
        }

        return $rules;
    }
}
