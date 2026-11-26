<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;

final readonly class RouteParameterGuard
{
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
        $statements = PhpSource::parse($source);
        $violations = [];

        foreach (new NodeFinder()->find($statements, static fn (Node $node): bool => $node instanceof String_ || $node instanceof InterpolatedStringPart) as $text) {
            if (! $text instanceof String_ && ! $text instanceof InterpolatedStringPart) {
                continue;
            }

            preg_match_all('/\{([A-Za-z_][\w-]*)(?::[^{}]*)?\??\}/', $text->value, $matches);

            foreach ($matches[1] as $parameter) {
                if (preg_match('/^[a-z][A-Za-z0-9]*$/', $parameter) !== 1) {
                    $violations[] = $text->getStartLine() . ': the route parameter {' . $parameter . '} is not camelCase: name it {' . lcfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $parameter)))) . '}, the name of the controller argument it binds to';
                }
            }
        }

        return $violations;
    }
}
