<?php

namespace Beobles\Core\View\Nodes;

use Beobles\Core\View\Compilation\CompilationContext;

class RawNode implements NodeInterface
{
    public function __construct(public readonly string $value) {}

    public function compile(CompilationContext $ctx): void
    {
        $ctx->writeLine('echo (string)(' . $ctx->expr($this->value) . ');');
    }

    public function __toString(): string
    {
        return 'RAW: {! ' . $this->value . ' !}';
    }
}
