<?php

namespace Core\View\Nodes;

use Core\View\Compilation\CompilationContext;

class ElseNode implements NodeInterface
{
    public function compile(CompilationContext $ctx): void
    {
        $ctx->writeLine('} else {');
    }

    public function __toString(): string
    {
        return 'ELSE';
    }
}
