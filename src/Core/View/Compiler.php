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
        $class = substr(strrchr('\\' . get_class($node), '\\'), 1);

        return match ($class) {
            'TextNode' => $this->compileText($node),
            'ExpressionNode' => $this->compileExpression($node),
            'RawNode' => $this->compileRaw($node),
            'ComponentNode' => $this->compileComponent($node),
            'IfNode' => $this->compileIf($node),
            'BlockNode' => $this->compileBlock($node),
            'ForeachNode' => $this->compileForeach($node),
            'ElseNode' => '} else {' . "\n",
            'ElseIfNode' => $this->compileElseIf($node),
            'EndNode' => $this->compileEnd($node),
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
        return 'echo htmlspecialchars((string) $__engine->evaluateExpression(' . var_export($node->value, true) . ', $__data), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");' . "\n";
    }

    /**
     * Compila raw output {! !}
     * 
     * @param object $node RawNode
     * @return string
     */
    private function compileRaw(object $node): string
    {
        return 'echo (string) $__engine->evaluateExpression(' . var_export($node->value, true) . ', $__data);' . "\n";
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
            fn($k, $v) => var_export((string) $k, true) . ' => $__engine->evaluateExpression(' . var_export((string) $v, true) . ', $__data)',
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
        return 'if ($__engine->isTruthy(' . var_export($node->condition, true) . ', $__data)) {' . "\n";
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
        [$valueName, $keyName] = array_map('trim', explode(',', $node->as . ','));
        foreach (array_filter([$valueName, $keyName]) as $name) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                throw new \InvalidArgumentException('Invalid foreach variable name: ' . $name);
            }
        }
        $iterable = '$__engine->evaluateExpression(' . var_export($node->items, true) . ', $__data)';
        if ($keyName !== '') {
            return 'foreach ((array) ' . $iterable . ' as $' . $keyName . ' => $' . $valueName . ') { $__data[' . var_export($keyName, true) . '] = $' . $keyName . '; $__data[' . var_export($valueName, true) . '] = $' . $valueName . ';' . "\n";
        }
        return 'foreach ((array) ' . $iterable . ' as $' . $valueName . ') { $__data[' . var_export($valueName, true) . '] = $' . $valueName . ';' . "\n";
    }
    private function compileElseIf(object $node): string
    {
        return '} elseif ($__engine->isTruthy(' . var_export($node->condition, true) . ', $__data)) {' . "\n";
    }

    private function compileEnd(object $node): string
    {
        return in_array($node->name, ['If', 'Unless', 'Foreach'], true) ? '} ' . "\n" : '';
    }
}
