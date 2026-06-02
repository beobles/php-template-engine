<?php

namespace Core\View\NodeVisitor;

use Core\View\Nodes\NodeInterface;

/**
 * Contrato para visitantes de nós da AST.
 *
 * Um visitante pode inspecionar ou modificar cada nó durante a
 * travessia. Exemplos de uso: análise de segurança, otimização,
 * coleta de dependências, debug dump.
 */
interface NodeVisitorInterface
{
    /**
     * Chamado ao entrar em um nó.
     *
     * Retorne null para manter o nó original, ou um NodeInterface
     * substituto para trocar o nó na AST.
     */
    public function enterNode(NodeInterface $node): ?NodeInterface;

    /**
     * Chamado ao sair de um nó (após processar seus filhos, se houver).
     *
     * Retorne null para manter o nó original, ou um NodeInterface
     * substituto para trocar o nó na AST.
     */
    public function leaveNode(NodeInterface $node): ?NodeInterface;
}
