<?php

namespace Beobles\Core\View\Nodes;

use Beobles\Core\View\Compilation\CompilationContext;

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
