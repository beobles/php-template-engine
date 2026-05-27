<?php

namespace Beobles\Core\View\Nodes;

use Beobles\Core\View\Compilation\CompilationContext;

class TextNode implements NodeInterface
{
    public function __construct(public readonly string $value) {}

    public function compile(CompilationContext $ctx): void
    {
        $ctx->writeLine('echo ' . var_export($this->value, true) . ';');
    }

    public function __toString(): string
    {
        return 'TEXT: ' . substr($this->value, 0, 50);
    }
}
