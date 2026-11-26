<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class RequestValidationGuard
{
    /**
     * @param list<string> $exemptPrefixes
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $folder,
        private array $exemptPrefixes,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        return FolderScan::over(
            $this->inspector,
            $this->root,
            [$this->folder],
            fn (string $path, string $source): array => array_any($this->exemptPrefixes, static fn (string $prefix): bool => str_starts_with($path, $prefix)) ? [] : $this->inSource($source),
        );
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $messages = [];

        foreach ($this->inspector->calls($source) as $call) {
            $facade = $call['kind'] === 'static' && in_array(basename(str_replace('\\', '/', $call['class'] ?? '')), ['Validator', 'ValidatorFacade'], true) && in_array($call['name'], ['make', 'validate'], true);
            $onRequest = $call['kind'] === 'method' && in_array($call['name'], ['validate', 'validatewithbag'], true) && in_array($call['receiver'], ['$request', 'request()', '->request()', '::request()'], true);

            if ($facade || $onRequest) {
                $messages[] = 'line ' . ($facade ? $call['classLine'] : $call['line']) . ': a request is validated by hand: write the rules in a form request and type it in the action';
            }
        }

        return $messages;
    }
}
