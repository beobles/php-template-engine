<?php

namespace Core\View\NodeVisitor;

use Core\View\Nodes\NodeInterface;

/**
 * Percorre a lista de nós da AST chamando cada visitante registrado.
 *
 * Padrão Visitor: desacopla a travessia da AST de qualquer lógica
 * específica de análise ou transformação.
 */
class NodeTraverser
{
    /** @var NodeVisitorInterface[] */
    private array $visitors = [];

    public function addVisitor(NodeVisitorInterface $visitor): void
    {
        $this->visitors[] = $visitor;
    }

    /**
     * Percorre os nós aplicando todos os visitantes.
     *
     * @param  NodeInterface[] $nodes
     * @return NodeInterface[]
     */
    public function traverse(array $nodes): array
    {
        if ($this->visitors === []) {
            return $nodes;
        }

        $result = [];

        foreach ($nodes as $node) {
            // enterNode: visitante pode substituir o nó
            foreach ($this->visitors as $visitor) {
                $replacement = $visitor->enterNode($node);
                if ($replacement !== null) {
                    $node = $replacement;
                }
            }

            // leaveNode: visitante pode substituir o nó
            foreach ($this->visitors as $visitor) {
                $replacement = $visitor->leaveNode($node);
                if ($replacement !== null) {
                    $node = $replacement;
                }
            }

            $result[] = $node;
        }

        return $result;
    }
}
