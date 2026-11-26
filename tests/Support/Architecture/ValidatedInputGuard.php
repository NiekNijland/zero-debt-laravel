<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Support\Facades\Request;

final readonly class ValidatedInputGuard
{
    private const array RAW_READERS = [
        'input', 'string', 'str', 'integer', 'float', 'boolean', 'date', 'enum', 'enums', 'array', 'collect', 'query', 'post', 'get',
        'all', 'only', 'except', 'safe', 'json', 'keys', 'fluent', 'has', 'hasany', 'filled', 'anyfilled', 'missing', 'exists',
        'isnotfilled', 'whenhas', 'whenfilled', 'file', 'hasfile', 'allfiles',
    ];

    private const array REQUESTS = ['$request', '->request', 'request()'];

    private const array REQUEST_FACADES = ['Request', Request::class];

    private const string MESSAGE = 'request input read past the form request: read it from $request->validated() (or an accessor of the form request) so only validated data reaches the action';

    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $folder,
    ) {
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        return array_map(static fn (int $line): string => 'line ' . $line . ': ' . self::MESSAGE, $this->rawReadLines($this->inspector->calls($source)));
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->folder) as $file) {
            foreach ($this->rawReadLines($this->inspector->callsIn($file)) as $line) {
                $violations[] = mb_substr($file, mb_strlen($this->root) + 1) . ':line ' . $line . ': ' . self::MESSAGE;
            }
        }

        return $violations;
    }

    /**
     * @param list<array{kind: string, line: int, classLine: int, name: string, written: string, class: string|null, receiver: string|null, arguments: list<string>}> $calls
     * @return list<int>
     */
    private function rawReadLines(array $calls): array
    {
        $lines = [];

        foreach ($calls as $call) {
            $reader = in_array($call['name'], self::RAW_READERS, true);

            if ($reader && in_array($call['kind'], ['method', 'nullsafe'], true) && in_array($call['receiver'], self::REQUESTS, true)) {
                $lines[] = $call['line'];
            }

            if ($reader && $call['kind'] === 'static' && in_array(mb_ltrim($call['written'], '\\'), self::REQUEST_FACADES, true)) {
                $lines[] = $call['line'];
            }

            if ($call['kind'] === 'function' && mb_ltrim(mb_strtolower($call['written']), '\\') === 'request' && $call['arguments'] !== []) {
                $lines[] = $call['line'];
            }
        }

        sort($lines);

        return array_values(array_unique($lines));
    }
}
