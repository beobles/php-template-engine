<?php

namespace Beobles\Core\View\Nodes;

use Beobles\Core\View\Abstract\AbstractNode;
use Beobles\Core\View\Compiler;

class SetNode extends AbstractNode
{
    public function __construct(
        public string $variable,
        public string $value,
        int $line = 1,
        int $column = 1,
        array $metadata = []
    ) {
        parent::__construct($line, $column, $metadata);
    }

    public function compile(Compiler $compiler): string
    {
        return $compiler->compileSetNode($this);
    }
}
