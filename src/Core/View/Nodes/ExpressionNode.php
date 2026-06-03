<?php

namespace Core\View\Nodes;

use Core\View\Abstract\AbstractNode;
use Core\View\Compiler;

class ExpressionNode extends AbstractNode
{
    public function __construct(
        public string $value,
        int $line = 1,
        int $column = 1,
        array $metadata = []
    ) {
        parent::__construct($line, $column, $metadata);
    }

    public function compile(Compiler $compiler): string
    {
        return $compiler->compileExpressionNode($this);
    }

    public function __toString(): string
    {
        return 'EXPRESSION: {{ ' . $this->value . ' }}';
    }
}
