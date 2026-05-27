<?php

namespace Core\View\Nodes;

use Core\View\Compilation\CompilationContext;

/**
 * Contrato para todos os nós da AST.
 *
 * Cada nó é responsável por:
 *   - Representar um fragmento semântico do template
 *   - Saber como compilar a si mesmo via compile()
 */
interface NodeInterface
{
    /**
     * Compila o nó, escrevendo código PHP no contexto de compilação.
     */
    public function compile(CompilationContext $ctx): void;

    /**
     * Retorna representação legível do nó (para debug/dump).
     */
    public function __toString(): string;
}
