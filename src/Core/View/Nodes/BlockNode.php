<?php

namespace Core\View\Nodes;

use Core\View\Compilation\CompilationContext;

/**
 * Representa a abertura de um bloco de layout.
 *
 * A resolução de herança (extends/override) ocorre antes da compilação.
 * Aqui o bloco atua apenas como marcador sem emitir código.
 */
class BlockNode implements NodeInterface
{
    public function __construct(public readonly string $name) {}

    public function compile(CompilationContext $ctx): void
    {
        // No-op: conteúdo interno do bloco já deve ser emitido normalmente.
    }

    public function __toString(): string
    {
        return 'BLOCK: ' . $this->name;
    }
}
