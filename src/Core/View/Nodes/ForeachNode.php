<?php

namespace Core\View\Nodes;

use Core\View\Compilation\CompilationContext;

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

        // Identificador único para variáveis internas deste loop (suporte a aninhamento)
        $uid      = substr(md5($this->items . ':' . $this->as), 0, 8);
        $totalVar = '$__loop_total_' . $uid;
        $indexVar = '$__loop_idx_' . $uid;

        // Empilha o $__loop do loop pai para restauração após o fechamento
        $ctx->writeLine('$__loop_stack ??= [];');
        $ctx->writeLine('$__loop_stack[] = $__loop ?? null;');
        // Captura o array uma vez para pré-calcular o total
        $ctx->writeLine($totalVar . ' = count((array)(' . $itemsCode . '));');
        $ctx->writeLine($indexVar . ' = 0;');

        if (count($vars) >= 2) {
            $valueVar = '$' . ltrim($vars[0], '$');
            $keyVar   = '$' . ltrim($vars[1], '$');
            $ctx->writeLine('foreach ((array)(' . $itemsCode . ') as ' . $keyVar . ' => ' . $valueVar . ') {');
        } else {
            $valueVar = '$' . ltrim($vars[0] ?? 'item', '$');
            $ctx->writeLine('foreach ((array)(' . $itemsCode . ') as ' . $valueVar . ') {');
        }

        // Injeta o objeto $__loop no início de cada iteração
        $ctx->writeLine('$__loop = (object)[');
        $ctx->writeLine("    'index'      => {$indexVar},");
        $ctx->writeLine("    'count'      => {$indexVar} + 1,");
        $ctx->writeLine("    'total'      => {$totalVar},");
        $ctx->writeLine("    'first'      => {$indexVar} === 0,");
        $ctx->writeLine("    'last'       => {$indexVar} === {$totalVar} - 1,");
        $ctx->writeLine("    'even'       => {$indexVar} % 2 === 0,");
        $ctx->writeLine("    'odd'        => {$indexVar} % 2 !== 0,");
        $ctx->writeLine("    'percentage' => {$totalVar} > 0 ? round(({$indexVar} + 1) / {$totalVar} * 100, 2) : 0.0,");
        $ctx->writeLine('];');
        $ctx->writeLine($indexVar . '++;');
    }

    public function __toString(): string
    {
        return 'FOREACH: ' . $this->items . ' as ' . $this->as;
    }
}
