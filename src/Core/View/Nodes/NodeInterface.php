<?php

namespace Beobles\Core\View\Nodes;

/**
 * Interface para nós da AST
 */
interface NodeInterface
{
    /**
     * Retorna string de representação
     * 
     * @return string
     */
    public function __toString(): string;
}
