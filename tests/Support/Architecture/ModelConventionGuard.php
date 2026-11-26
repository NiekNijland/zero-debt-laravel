<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class ModelConventionGuard
{
    private const array CONVENTIONS = [
        'a casts property' => ['/protected\s+\$casts\s*=/', 'declare the casts in the casts() method'],
        'a guarded property' => ['/protected\s+\$guarded\s*=/', 'list what is fillable in $fillable and never guard the rest'],
        'a get...Attribute accessor' => ['/function\s+get[A-Z]\w*Attribute\s*\(/', 'write the accessor as a method that returns an Attribute'],
        'a set...Attribute mutator' => ['/function\s+set[A-Z]\w*Attribute\s*\(/', 'write the mutator as a method that returns an Attribute'],
        'an Attribute constructor' => ['/new\s+Attribute\s*\(/', 'write Attribute::make(get: ..., set: ...)'],
        'a boot method' => ['/static\s+function\s+boot\s*\(/', 'write the hook as booted(), which does not need a call to the parent'],
    ];

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
        return FolderScan::over($this->inspector, $this->root, [$this->folder], fn (string $path, string $source): array => $this->inSource($source));
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $messages = [];

        foreach (self::CONVENTIONS as $convention => [$pattern, $replacement]) {
            if (preg_match($pattern, $source) === 1) {
                $messages[] = $convention . ': ' . $replacement;
            }
        }

        return $messages;
    }
}
