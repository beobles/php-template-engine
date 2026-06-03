<?php

namespace Core\View;

use Core\View\Nodes\BlockNode;
use Core\View\Nodes\ComponentNode;
use Core\View\Nodes\ExpressionNode;
use Core\View\Nodes\ForeachNode;
use Core\View\Nodes\IfNode;
use Core\View\Nodes\IncludeNode;
use Core\View\Nodes\NodeInterface;
use Core\View\Nodes\RawNode;
use Core\View\Nodes\SetNode;
use Core\View\Nodes\TextNode;

class Compiler
{
    /** @param array<int, NodeInterface> $nodes */
    public function compile(array $nodes): string
    {
        $code = "<?php\n";
        $code .= '$__loop_stack = $__loop_stack ?? [];'."\n";

        foreach ($nodes as $node) {
            $code .= $this->compileNode($node);
        }

        return $code;
    }

    public function compileNode(NodeInterface $node): string
    {
        return $node->compile($this);
    }

    public function compileTextNode(TextNode $node): string
    {
        return 'echo ' . var_export($node->value, true) . ";\n";
    }

    public function compileExpressionNode(ExpressionNode $node): string
    {
        $expression = $this->compileExpression($node->value);
        return 'echo $__engine->escape(' . $expression . ", 'html');\n";
    }

    public function compileRawNode(RawNode $node): string
    {
        return 'echo ' . $this->compileExpression($node->value) . ";\n";
    }

    public function compileComponentNode(ComponentNode $node): string
    {
        $props = [];
        foreach ($node->attributes as $key => $value) {
            $props[] = var_export((string) $key, true) . ' => ' . $this->compileExpression($value);
        }

        return 'echo $__engine->renderComponent(' . var_export($node->name, true) . ', [' . implode(', ', $props) . "]);\n";
    }

    public function compileIfNode(IfNode $node): string
    {
        $code = '';
        foreach ($node->branches as $index => $branch) {
            $childrenCode = $this->compileChildren($branch['nodes']);
            if ($index === 0) {
                $code .= 'if (' . $this->compileExpression((string) $branch['condition']) . ") {\n";
                $code .= $childrenCode;
                $code .= "}\n";
                continue;
            }

            if ($branch['condition'] === null) {
                $code .= "else {\n";
                $code .= $childrenCode;
                $code .= "}\n";
            } else {
                $code .= 'elseif (' . $this->compileExpression((string) $branch['condition']) . ") {\n";
                $code .= $childrenCode;
                $code .= "}\n";
            }
        }

        return $code;
    }

    public function compileForeachNode(ForeachNode $node): string
    {
        [$valueVar, $indexVar] = $this->parseForeachAs($node->as);
        $itemsExpr = $this->compileExpression($node->items);
        $loopIndexVar = '$__loop_index';
        $loopItemsVar = '$__loop_items';

        $code = "{$loopItemsVar} = {$itemsExpr};\n";
        $code .= "if (is_iterable({$loopItemsVar})) {\n";
        $code .= "    {$loopItemsVar} = is_array({$loopItemsVar}) ? {$loopItemsVar} : iterator_to_array({$loopItemsVar}, false);\n";
        $code .= "    {$loopIndexVar} = -1;\n";
        $code .= '    $__loop_stack[] = $__loop ?? null;' . "\n";
        $code .= "    foreach ({$loopItemsVar} as " . ($indexVar !== null ? "{$indexVar} => {$valueVar}" : "{$valueVar}") . ") {\n";
        $code .= "        {$loopIndexVar}++;\n";
        $code .= '        $__loop = [' . "\n";
        $code .= "            'index' => {$loopIndexVar},\n";
        $code .= "            'count' => {$loopIndexVar} + 1,\n";
        $code .= "            'total' => count({$loopItemsVar}),\n";
        $code .= "            'first' => {$loopIndexVar} === 0,\n";
        $code .= "            'last' => {$loopIndexVar} === count({$loopItemsVar}) - 1,\n";
        $code .= "            'even' => ({$loopIndexVar} + 1) % 2 === 0,\n";
        $code .= "            'odd' => ({$loopIndexVar} + 1) % 2 !== 0,\n";
        $code .= "            'percentage' => count({$loopItemsVar}) > 0 ? (int) round((({$loopIndexVar} + 1) / count({$loopItemsVar})) * 100) : 0,\n";
        $code .= "        ];\n";
        $code .= $this->indent($this->compileChildren($node->children));
        $code .= "    }\n";
        $code .= '    $__loop = array_pop($__loop_stack);' . "\n";
        $code .= "}\n";

        return $code;
    }

    public function compileBlockNode(BlockNode $node): string
    {
        return $this->compileChildren($node->children);
    }

    public function compileSetNode(SetNode $node): string
    {
        $variable = '$' . ltrim($node->variable, '$');
        return $variable . ' = ' . $this->compileExpression($node->value) . ";\n";
    }

    public function compileIncludeNode(IncludeNode $node): string
    {
        $dataExpr = $node->dataExpression ? $this->compileExpression($node->dataExpression) : '[]';
        return 'echo $__engine->render(' . var_export($node->path, true) . ', array_merge(get_defined_vars(), (array) (' . $dataExpr . ")));\n";
    }

    private function compileExpression(string $expression): string
    {
        $parts = preg_split('/\|/', $expression) ?: [];
        $base = trim(array_shift($parts) ?? 'null');
        $compiled = $this->transformExpression($base);

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\((.*)\)$/', $part, $matches) === 1) {
                $filter = $matches[1];
                $args = trim($matches[2]);
                $compiledArgs = $args === '' ? '[]' : '[' . $args . ']';
                $compiled = '$__engine->applyFilter(' . $compiled . ', ' . var_export($filter, true) . ', ' . $compiledArgs . ')';
            } else {
                $compiled = '$__engine->applyFilter(' . $compiled . ', ' . var_export($part, true) . ')';
            }
        }

        return $compiled;
    }

    private function transformExpression(string $expression): string
    {
        $expression = trim($expression);
        if ($expression === '') {
            return 'null';
        }

        $expression = $this->transformMemberNotation($expression);
        return $this->prefixBareIdentifiers($expression);
    }

    private function transformMemberNotation(string $expression): string
    {
        $expression = preg_replace(
            '/\b([A-Za-z_][A-Za-z0-9_]*)\.([A-Za-z_][A-Za-z0-9_]*)\s*\(/',
            '\$$1->$2(',
            $expression
        ) ?? $expression;

        return preg_replace_callback(
            '/\b(\$?[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)+)\b/',
            static function (array $matches): string {
                $path = $matches[1];
                $segments = explode('.', ltrim($path, '$'));
                $result = '$' . array_shift($segments);

                foreach ($segments as $segment) {
                    $result .= "['{$segment}']";
                }

                return $result;
            },
            $expression
        ) ?? $expression;
    }

    private function prefixBareIdentifiers(string $expression): string
    {
        $tokens = token_get_all('<?php ' . $expression . ';');
        $compiled = '';

        $count = count($tokens);
        for ($i = 1; $i < $count - 1; $i++) {
            $token = $tokens[$i];
            if (is_string($token)) {
                $compiled .= $token;
                continue;
            }

            [$id, $text] = $token;
            if ($id !== T_STRING || !$this->shouldPrefixIdentifier($text, $tokens, $i)) {
                $compiled .= $text;
                continue;
            }

            $compiled .= '$' . $text;
        }

        return trim($compiled);
    }

    /** @param array<int, mixed> $tokens */
    private function shouldPrefixIdentifier(string $text, array $tokens, int $index): bool
    {
        $lower = strtolower($text);
        if (in_array($lower, [
            'true',
            'false',
            'null',
            'and',
            'or',
            'xor',
            'instanceof',
            'new',
            'clone',
            'default',
            'match',
            'fn',
            'self',
            'static',
            'parent',
        ], true)) {
            return false;
        }

        if (preg_match('/^[A-Z_][A-Z0-9_]*$/', $text) === 1) {
            return false;
        }

        $prev = $this->previousSignificantToken($tokens, $index);
        $next = $this->nextSignificantToken($tokens, $index);

        if (($prev['text'] ?? null) === '->' || ($prev['text'] ?? null) === '::' || ($prev['id'] ?? null) === T_VARIABLE) {
            return false;
        }

        if (($next['text'] ?? null) === '(') {
            return false;
        }

        return true;
    }

    /** @param array<int, mixed> $tokens
     *  @return array{id:int|null,text:string|null}
     */
    private function previousSignificantToken(array $tokens, int $index): array
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $token = $tokens[$i];
            if (is_string($token)) {
                if (trim($token) !== '') {
                    return ['id' => null, 'text' => $token];
                }
                continue;
            }

            if ($token[0] !== T_WHITESPACE) {
                return ['id' => $token[0], 'text' => $token[1]];
            }
        }

        return ['id' => null, 'text' => null];
    }

    /** @param array<int, mixed> $tokens
     *  @return array{id:int|null,text:string|null}
     */
    private function nextSignificantToken(array $tokens, int $index): array
    {
        $count = count($tokens);
        for ($i = $index + 1; $i < $count; $i++) {
            $token = $tokens[$i];
            if (is_string($token)) {
                if (trim($token) !== '') {
                    return ['id' => null, 'text' => $token];
                }
                continue;
            }

            if ($token[0] !== T_WHITESPACE) {
                return ['id' => $token[0], 'text' => $token[1]];
            }
        }

        return ['id' => null, 'text' => null];
    }

    /** @param array<int, NodeInterface> $nodes */
    private function compileChildren(array $nodes): string
    {
        $code = '';
        foreach ($nodes as $node) {
            $code .= $this->compileNode($node);
        }

        return $code;
    }

    /** @return array{0:string,1:?string} */
    private function parseForeachAs(string $as): array
    {
        $parts = array_map('trim', explode(',', $as));
        if (count($parts) === 2) {
            return ['$' . ltrim($parts[0], '$'), '$' . ltrim($parts[1], '$')];
        }

        return ['$' . ltrim($parts[0], '$'), null];
    }

    private function indent(string $code, int $spaces = 8): string
    {
        $indent = str_repeat(' ', $spaces);
        $lines = explode("\n", rtrim($code, "\n"));

        return implode("\n", array_map(static fn(string $line): string => $line === '' ? $line : $indent . $line, $lines)) . "\n";
    }
}
