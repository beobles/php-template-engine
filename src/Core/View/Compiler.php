<?php

namespace Beobles\Core\View;

use Beobles\Core\View\Nodes\BlockNode;
use Beobles\Core\View\Nodes\ComponentNode;
use Beobles\Core\View\Nodes\ExpressionNode;
use Beobles\Core\View\Nodes\ForeachNode;
use Beobles\Core\View\Nodes\IfNode;
use Beobles\Core\View\Nodes\IncludeNode;
use Beobles\Core\View\Nodes\NodeInterface;
use Beobles\Core\View\Nodes\RawNode;
use Beobles\Core\View\Nodes\SetNode;
use Beobles\Core\View\Nodes\TextNode;

class Compiler
{
    private string $className = '';
    private string $sourceFile = '';

    /** @param array<int, NodeInterface> $nodes */
    public function compile(array $nodes, string $sourceFile = ''): string
    {
        $this->sourceFile = $sourceFile;
        $this->className = $this->generateClassName($sourceFile);

        return $this->generateHeader()
            . $this->generateClassStart()
            . $this->compileChildren($nodes)
            . $this->generateClassEnd();
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

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $base) === 1) {
            $compiled = '$__engine->resolveValue(' . var_export($base, true) . ', get_defined_vars())';
        } else {
            $compiled = $this->normalizeBareVariables($this->transformDotNotation($base));
        }

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

    private function transformDotNotation(string $expression): string
    {
        return preg_replace_callback(
            '/\b([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)+)\b/',
            fn(array $matches): string => '$__engine->resolveValue(' . var_export($matches[1], true) . ', get_defined_vars())',
            $expression
        ) ?? $expression;
    }

    private function normalizeBareVariables(string $expression): string
    {
        $tokens = token_get_all('<?php ' . $expression);
        if (isset($tokens[0]) && is_array($tokens[0]) && $tokens[0][0] === T_OPEN_TAG) {
            array_shift($tokens);
        }

        $result = '';
        $skip = ['true', 'false', 'null'];

        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_STRING) {
                $result .= is_array($token) ? $token[1] : $token;
                continue;
            }

            $text = $token[1];
            $lower = strtolower($text);
            if (in_array($lower, $skip, true)) {
                $result .= $text;
                continue;
            }

            if (preg_match('/^[A-Z_][A-Z0-9_]*$/', $text) === 1) {
                $result .= $text;
                continue;
            }

            $prev = $this->getPreviousSignificantToken($tokens, $index);
            $next = $this->getNextSignificantToken($tokens, $index);
            $prevText = is_array($prev) ? $prev[1] : $prev;
            $nextText = is_array($next) ? $next[1] : $next;
            $prevType = is_array($prev) ? $prev[0] : null;

            if (
                $prevText === '$'
                || $prevText === '->'
                || $prevText === '::'
                || $prevText === '\\'
                || $nextText === '('
                || $prevType === T_NEW
                || $prevType === T_FUNCTION
                || $prevType === T_FN
            ) {
                $result .= $text;
                continue;
            }

            $result .= '$' . $text;
        }

        return $result;
    }

    /** @param array<int, mixed> $tokens */
    private function getPreviousSignificantToken(array $tokens, int $index): mixed
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $token = $tokens[$i];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $token;
        }

        return null;
    }

    /** @param array<int, mixed> $tokens */
    private function getNextSignificantToken(array $tokens, int $index): mixed
    {
        $count = count($tokens);
        for ($i = $index + 1; $i < $count; $i++) {
            $token = $tokens[$i];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $token;
        }

        return null;
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

    private function generateHeader(): string
    {
        $source = $this->sourceFile !== '' ? $this->sourceFile : '[inline-template]';

        return "<?php\n\n"
            . "namespace Beobles\\Core\\View\\Compiled;\n\n"
            . "use Beobles\\Core\\View\\CompiledTemplate;\n"
            . "use Beobles\\Core\\View\\Engine;\n\n"
            . "/**\n"
            . " * Auto-generated compiled template\n"
            . " * Source: {$source}\n"
            . ' * Generated: ' . date('Y-m-d H:i:s') . "\n"
            . " */\n";
    }

    private function generateClassStart(): string
    {
        return "\nclass {$this->className} extends CompiledTemplate\n{\n"
            . "    public function render(array \$data = [], ?Engine \$engine = null): string\n"
            . "    {\n"
            . "        \$__engine = \$engine;\n"
            . "        \$__loop_stack = \$__loop_stack ?? [];\n"
            . "        extract(\$data, EXTR_SKIP);\n"
            . "        ob_start();\n\n";
    }

    private function generateClassEnd(): string
    {
        return "\n        return (string) ob_get_clean();\n"
            . "    }\n"
            . "}\n\n"
            . 'return ' . $this->className . "::class;\n";
    }

    private function generateClassName(string $sourceFile): string
    {
        $seed = $sourceFile !== '' ? $sourceFile : uniqid('inline_', true);
        return 'Template' . strtoupper(hash('crc32b', $seed));
    }
}
