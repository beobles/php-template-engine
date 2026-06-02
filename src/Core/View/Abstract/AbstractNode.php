<?php

namespace Core\View\Abstract;

use Core\View\Compiler;
use Core\View\Nodes\NodeInterface;

abstract class AbstractNode implements NodeInterface
{
    protected int $line;
    protected int $column;
    protected array $metadata;

    public function __construct(int $line = 1, int $column = 1, array $metadata = [])
    {
        $this->line = $line;
        $this->column = $column;
        $this->metadata = $metadata;
    }

    abstract public function compile(Compiler $compiler): string;

    public function accept(callable $visitor)
    {
        return $visitor($this);
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getColumn(): int
    {
        return $this->column;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
