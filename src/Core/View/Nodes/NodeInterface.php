<?php

namespace Beobles\Core\View\Nodes;

use Beobles\Core\View\Compiler;

interface NodeInterface
{
    public function compile(Compiler $compiler): string;

    public function accept(callable $visitor);

    public function getLine(): int;

    public function getColumn(): int;
}
