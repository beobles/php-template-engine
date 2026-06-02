<?php

namespace Core\View\Nodes;

use Core\View\Abstract\AbstractNode;
use Core\View\Compiler;

class ComponentNode extends AbstractNode
{
    public function __construct(
        public string $name,
        public array $attributes = [],
        int $line = 1,
        int $column = 1,
        array $metadata = []
    ) {
        parent::__construct($line, $column, $metadata);
    }

    public function compile(Compiler $compiler): string
    {
        return $compiler->compileComponentNode($this);
    }

    public function __toString(): string
    {
        return 'COMPONENT: <' . $this->name . ' />';
    }
}
