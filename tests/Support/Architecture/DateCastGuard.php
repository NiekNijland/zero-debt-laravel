<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class DateCastGuard
{
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
        $mutable = TemporarySource::with($source, fn (string $file): bool => array_any(
            $this->inspector->keyedArrayStrings($file),
            static fn (string $value): bool => preg_match('/^(datetime|date)(:|$)/', $value) === 1,
        ));

        return $mutable ? ['a date is cast as datetime or date: cast it as immutable_datetime or immutable_date, so a date is never changed in place'] : [];
    }
}
