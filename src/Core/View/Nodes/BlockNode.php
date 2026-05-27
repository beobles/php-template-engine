<?php

namespace Core\View\Nodes;

use Core\View\Compilation\CompilationContext;

/**
 * Representa a abertura de um bloco de layout.
 *
 * Em tempo de compilação emite ob_start(); o conteúdo é capturado
 * até o CloseTagNode correspondente emitir ob_get_clean().
 */
class BlockNode implements NodeInterface
{
    public function __construct(public readonly string $name) {}

    public function compile(CompilationContext $ctx): void
    {
        $ctx->writeLine('ob_start(); // block: ' . $this->name);
    }

    public function __toString(): string
    {
        return 'BLOCK: ' . $this->name;
    }
}
