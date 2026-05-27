<?php

namespace Beobles\Core\View\Nodes;

class ComponentNode implements NodeInterface
{
    public function __construct(
        public string $name,
        public array $attributes = []
    ) {}

    public function __toString(): string
    {
        return 'COMPONENT: <' . $this->name . ' />';
    }
}
