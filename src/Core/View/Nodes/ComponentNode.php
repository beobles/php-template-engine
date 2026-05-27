<?php

namespace Beobles\Core\View\Nodes;

use Beobles\Core\View\Compilation\CompilationContext;

class ComponentNode implements NodeInterface
{
    public function __construct(
        public readonly string $name,
        public readonly array $attributes = []
    ) {}

    public function compile(CompilationContext $ctx): void
    {
        $propPairs = array_map(
            fn(string $k, string $v): string => "'" . $k . "' => " . $ctx->expr($v),
            array_keys($this->attributes),
            array_values($this->attributes)
        );

        $propsCode = 'array(' . implode(', ', $propPairs) . ')';
        $ctx->writeLine('echo $__engine->renderComponent(' . var_export($this->name, true) . ', ' . $propsCode . ');');
    }

    public function __toString(): string
    {
        return 'COMPONENT: <' . $this->name . ' />';
    }
}
