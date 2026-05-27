<?php

namespace Beobles\Core\View\Nodes;

class TextNode implements NodeInterface
{
    public function __construct(
        public string $value
    ) {}

    public function __toString(): string
    {
        return 'TEXT: ' . substr($this->value, 0, 50);
    }
}
