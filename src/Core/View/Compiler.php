<?php

namespace Beobles\Core\View;

use Beobles\Core\View\Compilation\CompilationContext;
use Beobles\Core\View\Compilation\ExpressionCompiler;
use Beobles\Core\View\NodeVisitor\NodeTraverser;
use Beobles\Core\View\NodeVisitor\NodeVisitorInterface;
use Beobles\Core\View\Nodes\NodeInterface;

/**
 * Orquestrador da compilação de templates.
 *
 * Responsabilidades:
 *   1. Escrever o preamble PHP (helper __tpl_get)
 *   2. Passar a AST pelos NodeVisitors registrados
 *   3. Iterar os nós pedindo que cada um se compile
 *
 * Todo conhecimento de como compilar um fragmento específico
 * vive no próprio Node, não aqui.
 */
class Compiler
{
    private NodeTraverser $traverser;
    private ExpressionCompiler $expressionCompiler;

    public function __construct()
    {
        $this->traverser          = new NodeTraverser();
        $this->expressionCompiler = new ExpressionCompiler();
    }

    /**
     * Registra um visitante de nós para análise ou transformação da AST.
     */
    public function addVisitor(NodeVisitorInterface $visitor): void
    {
        $this->traverser->addVisitor($visitor);
    }

    /**
     * Compila a AST em código PHP pronto para execução.
     *
     * @param  NodeInterface[] $nodes
     */
    public function compile(array $nodes): string
    {
        $nodes = $this->traverser->traverse($nodes);

        $ctx = new CompilationContext($this->expressionCompiler);

        $ctx->writeLine('<?php');
        $ctx->writeLine();
        $ctx->write($this->expressionCompiler->preamble());

        foreach ($nodes as $node) {
            $node->compile($ctx);
        }

        return $ctx->getCode();
    }
}
