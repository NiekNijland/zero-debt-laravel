<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final class FolderScan
{
    /**
     * @param list<string> $folders
     * @param callable(string, string): list<string> $check
     * @return list<string>
     */
    public static function over(PhpFileInspector $inspector, string $root, array $folders, callable $check): array
    {
        $violations = [];

        foreach ($folders as $folder) {
            foreach ($inspector->phpFiles($root . '/' . $folder) as $file) {
                $path = mb_substr($file, mb_strlen($root) + 1);

                foreach ($check($path, (string) file_get_contents($file)) as $message) {
                    $violations[] = $path . ': ' . $message;
                }
            }
        }

        return $violations;
    }
}
