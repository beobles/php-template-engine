<?php

namespace Beobles\Core\View\Nodes;

use Beobles\Core\View\Abstract\AbstractNode;
use Beobles\Core\View\Compiler;

class IncludeNode extends AbstractNode
{
    public function __construct(
        public string $path,
        public ?string $dataExpression = null,
        int $line = 1,
        int $column = 1,
        array $metadata = []
    ) {
        parent::__construct($line, $column, $metadata);
    }

    public function compile(Compiler $compiler): string
    {
        return $compiler->compileIncludeNode($this);
    }
}
