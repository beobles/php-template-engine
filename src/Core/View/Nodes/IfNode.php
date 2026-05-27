<?php

namespace Beobles\Core\View\Nodes;

class IfNode implements NodeInterface
{
    public function __construct(
        public string $condition
    ) {}

    public function __toString(): string
    {
        return 'IF: ' . $this->condition;
    }
}
