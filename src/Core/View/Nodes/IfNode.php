<?php

namespace Core\View\Nodes;

use Core\View\Abstract\AbstractNode;
use Core\View\Compiler;

class IfNode extends AbstractNode
{
    /** @var array<int, array{condition:?string, nodes:array<NodeInterface>}> */
    public array $branches;

    /**
     * @param array<int, array{condition:?string, nodes:array<NodeInterface>}> $branches
     */
    public function __construct(array $branches, int $line = 1, int $column = 1, array $metadata = [])
    {
        parent::__construct($line, $column, $metadata);
        $this->branches = $branches;
    }

    public function compile(Compiler $compiler): string
    {
        return $compiler->compileIfNode($this);
    }
}
