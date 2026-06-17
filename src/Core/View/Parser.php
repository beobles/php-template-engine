<?php

namespace Beobles\Core\View;

use Beobles\Core\View\Nodes\TextNode;
use Beobles\Core\View\Nodes\ExpressionNode;
use Beobles\Core\View\Nodes\RawNode;
use Beobles\Core\View\Nodes\ComponentNode;
use Beobles\Core\View\Nodes\IfNode;
use Beobles\Core\View\Exceptions\ParserException;

/**
 * Parser de AST (Abstract Syntax Tree)
 * Converte tokens em nós para compilação
 */
class Parser
{
    private array $tokens;
    private int $position = 0;

    /**
     * Faz parse dos tokens
     * 
     * @param array $tokens Tokens da lexer
     * @return array AST (nodes)
     */
    public function parse(array $tokens): array
    {
        $this->tokens = $tokens;
        $this->position = 0;
        $nodes = [];

        while ($this->position < count($tokens)) {
            $token = $this->current();

            if ($token['type'] === 'TEXT') {
                $nodes[] = new TextNode($token['value']);
                $this->advance();
            } elseif ($token['type'] === 'EXPRESSION') {
                $nodes[] = new ExpressionNode($token['value']);
                $this->advance();
            } elseif ($token['type'] === 'RAW') {
                $nodes[] = new RawNode($token['value']);
                $this->advance();
            } elseif ($token['type'] === 'TAG_CLOSE') {
                $nodes[] = new EndNode($token['name']);
                $this->advance();
            } elseif ($token['type'] === 'TAG') {
                $node = $this->parseTag();
                if ($node) {
                    $nodes[] = $node;
                }
            } elseif ($token['type'] === 'KEYWORD') {
                $node = $this->parseKeyword();
                if ($node) {
                    $nodes[] = $node;
                }
            } else {
                $this->advance();
            }
        }

        return $nodes;
    }

    /**
     * Parse de uma tag customizada
     * 
     * @return object Node
     */
    private function parseTag(): ?object
    {
        $token = $this->current();
        $tagName = $token['name'];

        $this->advance();

        switch ($tagName) {
            case 'If':
                return $this->parseIfTag($token);
            case 'Block':
                return $this->parseBlockTag($token);
            case 'Foreach':
                return $this->parseForEachTag($token);
            case 'Else':
                return new ElseNode();
            case 'Component':
            case preg_match('/^[A-Z]/', $tagName) ? $tagName : null:
                return $this->parseComponentTag($token);
            default:
                return null;
        }
    }

    /**
     * Parse de keyword (extends, import)
     * 
     * @return object Node
     */
    private function parseKeyword(): ?object
    {
        $token = $this->current();
        $keyword = $token['value'];

        $this->advance();

        // Implementar parsing de keywords conforme necessário
        return null;
    }

    /**
     * Parse de tag If
     * 
     * @param array $token Token da tag
     * @return IfNode
     */
    private function parseIfTag(array $token): IfNode
    {
        // Extrair condition do atributo
        preg_match('/condition\s*=\s*["\']?\{\{(.+?)\}\}["\']?/', $token['attributes'], $matches);
        $condition = $matches[1] ?? '';

        return new IfNode($condition);
    }

    /**
     * Parse de tag Block
     * 
     * @param array $token Token da tag
     * @return BlockNode
     */
    private function parseBlockTag(array $token): BlockNode
    {
        preg_match('/name\s*=\s*["\']([^"\']*)["\']/s', $token['attributes'], $matches);
        $name = $matches[1] ?? '';

        return new BlockNode($name);
    }

    /**
     * Parse de tag Foreach
     * 
     * @param array $token Token da tag
     * @return ForeachNode
     */
    private function parseForEachTag(array $token): ForeachNode
    {
        preg_match('/items\s*=\s*\{\{(.+?)\}\}/', $token['attributes'], $itemsMatches);
        preg_match('/as\s*=\s*["\']([^"\']*)["\']/s', $token['attributes'], $asMatches);

        $items = $itemsMatches[1] ?? '';
        $as = $asMatches[1] ?? '';

        return new ForeachNode($items, $as);
    }

    /**
     * Parse de tag de componente
     * 
     * @param array $token Token da tag
     * @return ComponentNode
     */
    private function parseComponentTag(array $token): ComponentNode
    {
        $name = $token['name'];
        $attributes = $this->parseAttributes($token['attributes']);

        return new ComponentNode($name, $attributes);
    }

    /**
     * Parse de atributos
     * 
     * @param string $attributesStr String de atributos
     * @return array Atributos parseados
     */
    private function parseAttributes(string $attributesStr): array
    {
        $attributes = [];
        preg_match_all('/(\w+)\s*=\s*(?:\{\{(.+?)\}\}|["\']([^"\']*)["\']/s', $attributesStr, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = $match[1];
            $value = $match[2] ?? $match[3] ?? '';
            $attributes[$name] = $value;
        }

        return $attributes;
    }

    /**
     * Obtém token atual
     * 
     * @return array Token
     */
    private function current(): array
    {
        return $this->tokens[$this->position] ?? [];
    }

    /**
     * Avança para próximo token
     * 
     * @return void
     */
    private function advance(): void
    {
        $this->position++;
    }
}

// Node classes
class BlockNode
{
    public function __construct(
        public string $name
    ) {}
}

class ForeachNode
{
    public function __construct(
        public string $items,
        public string $as
    ) {}
}


class ElseNode
{
}

class EndNode
{
    public function __construct(
        public string $name
    ) {}
}
