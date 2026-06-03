<?php

namespace Core\View\Nodes;

use Core\View\Abstract\AbstractNode;
use Core\View\Compiler;

class ForeachNode extends AbstractNode
{
    /** @param array<NodeInterface> $children */
    public function __construct(
        public string $items,
        public string $as,
        public array $children = [],
        int $line = 1,
        int $column = 1,
        array $metadata = []
    ) {
        parent::__construct($line, $column, $metadata);
    }

    public function compile(Compiler $compiler): string
    {
        return $compiler->compileForeachNode($this);
    }
}
