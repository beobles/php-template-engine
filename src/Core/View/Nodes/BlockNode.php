<?php

namespace Beobles\Core\View\Nodes;

use Beobles\Core\View\Abstract\AbstractNode;
use Beobles\Core\View\Compiler;

class BlockNode extends AbstractNode
{
    /** @param array<NodeInterface> $children */
    public function __construct(
        public string $name,
        public array $children = [],
        int $line = 1,
        int $column = 1,
        array $metadata = []
    ) {
        parent::__construct($line, $column, $metadata);
    }

    public function compile(Compiler $compiler): string
    {
        return $compiler->compileBlockNode($this);
    }
}
