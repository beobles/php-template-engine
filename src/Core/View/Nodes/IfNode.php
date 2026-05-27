<?php

namespace Core\View\Nodes;

use Core\View\Compilation\CompilationContext;

class IfNode implements NodeInterface
{
    public function __construct(public readonly string $condition) {}

    public function compile(CompilationContext $ctx): void
    {
        $ctx->writeLine('if (' . $ctx->expr($this->condition) . ') {');
    }

    public function __toString(): string
    {
        return 'IF: ' . $this->condition;
    }
}
