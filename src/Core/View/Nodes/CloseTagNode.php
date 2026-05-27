<?php

namespace Beobles\Core\View\Nodes;

use Beobles\Core\View\Compilation\CompilationContext;

/**
 * Fecha um bloco de controle estruturado: If, Foreach ou Block.
 */
class CloseTagNode implements NodeInterface
{
    public function __construct(public readonly string $tagName) {}

    public function compile(CompilationContext $ctx): void
    {
        if ($this->tagName === 'Block') {
            $ctx->writeLine('ob_get_clean();');
        } else {
            $ctx->writeLine('}');
        }
    }

    public function __toString(): string
    {
        return 'CLOSE: </' . $this->tagName . '>';
    }
}
