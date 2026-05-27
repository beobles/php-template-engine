<?php

namespace Beobles\Core\View;

/**
 * Compilador de AST para código PHP
 * Transforma nós da AST em código PHP otimizado
 */
class Compiler
{
    /**
     * Compila AST em código PHP
     * 
     * @param array $nodes Nós da AST
     * @return string Código PHP compilado
     */
    public function compile(array $nodes): string
    {
        $code = "<?php\n\n";
        $code .= "if (!function_exists('__tpl_get')) {\n";
        $code .= "    function __tpl_get(array \$scope, string \$path) {\n";
        $code .= "        \$segments = explode('.', \$path);\n";
        $code .= "        \$value = \$scope[\$segments[0]] ?? null;\n";
        $code .= "        foreach (array_slice(\$segments, 1) as \$segment) {\n";
        $code .= "            if (is_array(\$value) && array_key_exists(\$segment, \$value)) {\n";
        $code .= "                \$value = \$value[\$segment];\n";
        $code .= "                continue;\n";
        $code .= "            }\n";
        $code .= "            if (is_object(\$value)) {\n";
        $code .= "                if (isset(\$value->{\$segment})) {\n";
        $code .= "                    \$value = \$value->{\$segment};\n";
        $code .= "                    continue;\n";
        $code .= "                }\n";
        $code .= "                \$getter = 'get' . ucfirst(\$segment);\n";
        $code .= "                if (method_exists(\$value, \$getter)) {\n";
        $code .= "                    \$value = \$value->{\$getter}();\n";
        $code .= "                    continue;\n";
        $code .= "                }\n";
        $code .= "            }\n";
        $code .= "            return null;\n";
        $code .= "        }\n";
        $code .= "        return \$value;\n";
        $code .= "    }\n";
        $code .= "}\n\n";

        foreach ($nodes as $node) {
            $code .= $this->compileNode($node);
        }

        return $code;
    }

    /**
     * Compila um nó individual
     * 
     * @param object $node Nó para compilar
     * @return string Código PHP
     */
    private function compileNode(object $node): string
    {
        $class = (new \ReflectionClass($node))->getShortName();

        return match ($class) {
            'TextNode' => $this->compileText($node),
            'ExpressionNode' => $this->compileExpression($node),
            'RawNode' => $this->compileRaw($node),
            'ComponentNode' => $this->compileComponent($node),
            'IfNode' => $this->compileIf($node),
            'ElseNode' => $this->compileElse(),
            'ElseIfNode' => $this->compileElseIf($node),
            'BlockNode' => $this->compileBlock($node),
            'ForeachNode' => $this->compileForeach($node),
            'CloseTagNode' => $this->compileCloseTag($node),
            default => ''
        };
    }

    /**
     * Compila texto
     * 
     * @param object $node TextNode
     * @return string
     */
    private function compileText(object $node): string
    {
        return 'echo ' . var_export($node->value, true) . ";\n";
    }

    /**
     * Compila expressão {{ }}
     * 
     * @param object $node ExpressionNode
     * @return string
     */
    private function compileExpression(object $node): string
    {
        [$expression, $isRaw] = $this->compileExpressionChain($node->value);
        $rendered = $isRaw
            ? $expression
            : 'htmlspecialchars((string)(' . $expression . '), ENT_QUOTES, "UTF-8")';

        return 'echo ' . $rendered . ";\n";
    }

    /**
     * Compila raw output {! !}
     * 
     * @param object $node RawNode
     * @return string
     */
    private function compileRaw(object $node): string
    {
        return 'echo (string)(' . $this->compilePhpExpression($node->value) . ");\n";
    }

    /**
     * Compila componente
     * 
     * @param object $node ComponentNode
     * @return string
     */
    private function compileComponent(object $node): string
    {
        $props = 'array(' . implode(', ', array_map(
            fn($k, $v) => "'" . $k . "' => " . $this->compilePhpExpression((string) $v),
            array_keys($node->attributes),
            $node->attributes
        )) . ')';

        return 'echo $__engine->renderComponent(' . var_export($node->name, true) . ', ' . $props . ");\n";
    }

    /**
     * Compila If
     * 
     * @param object $node IfNode
     * @return string
     */
    private function compileIf(object $node): string
    {
        return 'if (' . $this->compilePhpExpression($node->condition) . ") {\n";
    }

    private function compileElse(): string
    {
        return "} else {\n";
    }

    private function compileElseIf(object $node): string
    {
        return '} elseif (' . $this->compilePhpExpression($node->condition) . ") {\n";
    }

    /**
     * Compila Block
     * 
     * @param object $node BlockNode
     * @return string
     */
    private function compileBlock(object $node): string
    {
        return 'ob_start();' . "\n";
    }

    /**
     * Compila Foreach
     * 
     * @param object $node ForeachNode
     * @return string
     */
    private function compileForeach(object $node): string
    {
        $items = $this->compilePhpExpression($node->items);
        $vars = array_values(array_filter(array_map('trim', explode(',', $node->as))));

        if (count($vars) >= 2) {
            $valueVar = '$' . ltrim($vars[0], '$');
            $keyVar = '$' . ltrim($vars[1], '$');
            return 'foreach ((array)(' . $items . ') as ' . $keyVar . ' => ' . $valueVar . ") {\n";
        }

        $valueVar = '$' . ltrim($vars[0] ?? 'item', '$');
        return 'foreach ((array)(' . $items . ') as ' . $valueVar . ") {\n";
    }

    private function compileCloseTag(object $node): string
    {
        return match ($node->name) {
            'If', 'Foreach', 'Block' => "}\n",
            default => '',
        };
    }

    /**
     * @return array{0:string,1:bool}
     */
    private function compileExpressionChain(string $expression): array
    {
        $parts = $this->splitByPipes($expression);
        $baseExpression = array_shift($parts) ?? '';
        $code = $this->compilePhpExpression($baseExpression);
        $isRaw = false;

        foreach ($parts as $filterPart) {
            $filterPart = trim($filterPart);
            if ($filterPart === '') {
                continue;
            }

            if (preg_match('/^(\w+)(?:\((.*)\))?$/s', $filterPart, $matches) !== 1) {
                continue;
            }

            $name = $matches[1];
            $args = trim($matches[2] ?? '');
            $argsCode = $args === '' ? '[]' : '[' . $args . ']';
            $code = '$__engine->applyFilter(' . $code . ', ' . var_export($name, true) . ', ' . $argsCode . ')';
            $isRaw = $name === 'raw';
        }

        return [$code, $isRaw];
    }

    private function splitByPipes(string $value): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $quote = null;
        $len = strlen($value);

        for ($i = 0; $i < $len; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                if ($char === $quote && ($i === 0 || $value[$i - 1] !== '\\')) {
                    $quote = null;
                }
                $buffer .= $char;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if (in_array($char, ['(', '[', '{'], true)) {
                $depth++;
                $buffer .= $char;
                continue;
            }

            if (in_array($char, [')', ']', '}'], true)) {
                $depth = max(0, $depth - 1);
                $buffer .= $char;
                continue;
            }

            if ($char === '|' && $depth === 0) {
                $parts[] = trim($buffer);
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $parts[] = trim($buffer);
        return $parts;
    }

    private function compilePhpExpression(string $expression): string
    {
        $expression = trim($expression);
        if ($expression === '') {
            return 'null';
        }

        $placeholderMap = [];
        $counter = 0;
        $expression = preg_replace_callback('/\b([a-zA-Z_]\w*(?:\.[a-zA-Z_]\w*)+)\b/', function ($matches) use (&$placeholderMap, &$counter) {
            $placeholder = "__TPL_DOT_{$counter}__";
            $placeholderMap[$placeholder] = "__tpl_get(get_defined_vars(), '" . $matches[1] . "')";
            $counter++;
            return $placeholder;
        }, $expression);

        $tokens = token_get_all('<?php ' . $expression);
        array_shift($tokens);

        $result = '';
        $tokenCount = count($tokens);
        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $tokens[$i];

            if (is_string($token)) {
                $result .= $token;
                continue;
            }

            [$type, $text] = $token;
            if ($type !== T_STRING) {
                $result .= $text;
                continue;
            }

            if (isset($placeholderMap[$text])) {
                $result .= $placeholderMap[$text];
                continue;
            }

            if ($this->isReservedIdentifier($text)) {
                $result .= $text;
                continue;
            }

            $next = $this->nextNonWhitespaceToken($tokens, $i + 1);
            if ($next === '(') {
                $result .= $text;
                continue;
            }

            $prev = $this->prevNonWhitespaceToken($tokens, $i - 1);
            if ($prev === '->' || $prev === '::' || $prev === '$') {
                $result .= $text;
                continue;
            }

            $result .= '$' . $text;
        }

        return $result;
    }

    private function isReservedIdentifier(string $value): bool
    {
        if (preg_match('/^[A-Z_][A-Z0-9_]*$/', $value) === 1) {
            return true;
        }

        static $reserved = [
            'true', 'false', 'null', 'and', 'or', 'xor', 'instanceof', 'new',
            'clone', 'match', 'fn', 'array', 'parent', 'self', 'static',
        ];

        return in_array(strtolower($value), $reserved, true);
    }

    /**
     * @param array<int, array{0:int,1:string,2?:int}|string> $tokens
     */
    private function nextNonWhitespaceToken(array $tokens, int $start)
    {
        for ($i = $start, $len = count($tokens); $i < $len; $i++) {
            $token = $tokens[$i];
            if (is_string($token)) {
                if (trim($token) !== '') {
                    return $token;
                }
                continue;
            }

            if ($token[0] !== T_WHITESPACE) {
                return $token[1];
            }
        }

        return null;
    }

    /**
     * @param array<int, array{0:int,1:string,2?:int}|string> $tokens
     */
    private function prevNonWhitespaceToken(array $tokens, int $start)
    {
        for ($i = $start; $i >= 0; $i--) {
            $token = $tokens[$i];
            if (is_string($token)) {
                if (trim($token) !== '') {
                    return $token;
                }
                continue;
            }

            if ($token[0] !== T_WHITESPACE) {
                return $token[1];
            }
        }

        return null;
    }
}
