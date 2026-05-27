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
        $class = class_basename($node);

        return match ($class) {
            'TextNode' => $this->compileText($node),
            'ExpressionNode' => $this->compileExpression($node),
            'RawNode' => $this->compileRaw($node),
            'ComponentNode' => $this->compileComponent($node),
            'IfNode' => $this->compileIf($node),
            'BlockNode' => $this->compileBlock($node),
            'ForeachNode' => $this->compileForeach($node),
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
        $escaped = 'htmlspecialchars(' . $node->value . ', ENT_QUOTES, "UTF-8")';
        return 'echo ' . $escaped . ";\n";
    }

    /**
     * Compila raw output {! !}
     * 
     * @param object $node RawNode
     * @return string
     */
    private function compileRaw(object $node): string
    {
        return 'echo ' . $node->value . ";\n";
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
            fn($k, $v) => "'" . $k . "' => " . $v,
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
        return 'if (' . $node->condition . ") {\n";
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
        return 'foreach (' . $node->items . ' as ' . $node->as . ") {\n";
    }
}
