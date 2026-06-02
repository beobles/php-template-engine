<?php

namespace Core\View\Debug;

class RuntimeContext
{
    /** @param array<string, mixed> $variables */
    public function __construct(
        private array $variables,
        private float $startTime
    ) {
    }

    /** @return array<string, mixed> */
    public function variables(): array
    {
        return $this->variables;
    }

    public function elapsedMs(): float
    {
        return (microtime(true) - $this->startTime) * 1000;
    }
}
