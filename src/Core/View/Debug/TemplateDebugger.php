<?php

namespace Beobles\Core\View\Debug;

class TemplateDebugger
{
    public function begin(): float
    {
        return microtime(true);
    }

    /** @param array<string, mixed> $variables */
    public function context(array $variables, float $startTime): RuntimeContext
    {
        return new RuntimeContext($variables, $startTime);
    }
}
