<?php

namespace Core\View\Compilation;

/**
 * Buffer de compilação passado a cada Node durante a compilação.
 *
 * Centraliza:
 *   - A escrita de código PHP em um buffer de string
 *   - A delegação para o ExpressionCompiler (expressões e filtros)
 *
 * Assim cada Node pode chamar $ctx->expr() ou $ctx->exprChain() e depois
 * $ctx->writeLine() sem precisar conhecer os detalhes de compilação.
 */
class CompilationContext
{
    private string $buffer = '';
    private ExpressionCompiler $expressionCompiler;

    public function __construct(ExpressionCompiler $expressionCompiler)
    {
        $this->expressionCompiler = $expressionCompiler;
    }

    // -----------------------------------------------------------------------
    // Buffer de escrita
    // -----------------------------------------------------------------------

    public function write(string $code): void
    {
        $this->buffer .= $code;
    }

    public function writeLine(string $code = ''): void
    {
        $this->buffer .= $code . "\n";
    }

    public function getCode(): string
    {
        return $this->buffer;
    }

    // -----------------------------------------------------------------------
    // Delegação para ExpressionCompiler
    // -----------------------------------------------------------------------

    /**
     * Compila uma expressão simples (sem filtros) para PHP.
     */
    public function expr(string $expression): string
    {
        return $this->expressionCompiler->compile($expression);
    }

    /**
     * Compila uma expressão com possível cadeia de filtros.
     *
     * @return array{0: string, 1: bool}  [código PHP, isRaw]
     */
    public function exprChain(string $expression): array
    {
        return $this->expressionCompiler->compileChain($expression);
    }
}
