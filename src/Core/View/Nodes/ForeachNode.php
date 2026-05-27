<?php

namespace Beobles\Core\View\Nodes;

use Beobles\Core\View\Compilation\CompilationContext;

class ForeachNode implements NodeInterface
{
    public function __construct(
        public readonly string $items,
        public readonly string $as
    ) {}

    public function compile(CompilationContext $ctx): void
    {
        $itemsCode = $ctx->expr($this->items);
        $vars      = array_values(array_filter(array_map('trim', explode(',', $this->as))));

        if (count($vars) >= 2) {
            $valueVar = '$' . ltrim($vars[0], '$');
            $keyVar   = '$' . ltrim($vars[1], '$');
            $ctx->writeLine('foreach ((array)(' . $itemsCode . ') as ' . $keyVar . ' => ' . $valueVar . ') {');
        } else {
            $valueVar = '$' . ltrim($vars[0] ?? 'item', '$');
            $ctx->writeLine('foreach ((array)(' . $itemsCode . ') as ' . $valueVar . ') {');
        }
    }

    public function __toString(): string
    {
        return 'FOREACH: ' . $this->items . ' as ' . $this->as;
    }
}
