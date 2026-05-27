<?php

namespace Beobles\Core\View\Nodes;

class ExpressionNode implements NodeInterface
{
    public function __construct(
        public string $value
    ) {}

    public function __toString(): string
    {
        return 'EXPRESSION: {{ ' . $this->value . ' }}';
    }
}
