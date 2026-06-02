<?php

namespace Core\View\Nodes;

use Core\View\Abstract\AbstractNode;
use Core\View\Compiler;

class IncludeNode extends AbstractNode
{
    public function __construct(
        public string $path,
        public ?string $dataExpression = null,
        int $line = 1,
        int $column = 1,
        array $metadata = []
    ) {
        parent::__construct($line, $column, $metadata);
    }

    public function compile(Compiler $compiler): string
    {
        return $compiler->compileIncludeNode($this);
    }
}
