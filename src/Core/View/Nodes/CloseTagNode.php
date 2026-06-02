<?php

namespace Core\View\Nodes;

use Core\View\Compilation\CompilationContext;

/**
 * Fecha um bloco de controle estruturado: If, Foreach ou Block.
 */
class CloseTagNode implements NodeInterface
{
    public function __construct(public readonly string $tagName) {}

    public function compile(CompilationContext $ctx): void
    {
        if ($this->tagName === 'Foreach') {
            $ctx->writeLine('}');
            // Restaura o $__loop do loop pai (suporte a foreach aninhado)
            $ctx->writeLine('$__loop = array_pop($__loop_stack);');
        } elseif ($this->tagName !== 'Block') {
            $ctx->writeLine('}');
        }
    }

    public function __toString(): string
    {
        return 'CLOSE: </' . $this->tagName . '>';
    }
}
