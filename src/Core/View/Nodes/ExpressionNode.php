<?php

namespace Beobles\Core\View\Nodes;

use Beobles\Core\View\Compilation\CompilationContext;

class ExpressionNode implements NodeInterface
{
    public function __construct(public readonly string $value) {}

    public function compile(CompilationContext $ctx): void
    {
        [$code, $isRaw] = $ctx->exprChain($this->value);

        $output = $isRaw
            ? '(string)(' . $code . ')'
            : 'htmlspecialchars((string)(' . $code . '), ENT_QUOTES, "UTF-8")';

        $ctx->writeLine('echo ' . $output . ';');
    }

    public function __toString(): string
    {
        return 'EXPRESSION: {{ ' . $this->value . ' }}';
    }
}
