<?php

namespace Beobles\Core\View;

use Beobles\Core\View\Nodes\TextNode;
use Beobles\Core\View\Nodes\ExpressionNode;
use Beobles\Core\View\Nodes\RawNode;
use Beobles\Core\View\Nodes\ComponentNode;
use Beobles\Core\View\Nodes\IfNode;

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
            } elseif ($token['type'] === 'TAG') {
                $node = $this->parseTag();
                if ($node) {
                    $nodes[] = $node;
                }
            } elseif ($token['type'] === 'TAG_CLOSE') {
                $node = $this->parseCloseTag();
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
            case 'Else':
                return new ElseNode();
            case 'ElseIf':
                return $this->parseElseIfTag($token);
            case 'Block':
                return $this->parseBlockTag($token);
            case 'Foreach':
                return $this->parseForEachTag($token);
            case 'Component':
                return $this->parseComponentTag($token);
            default:
                if (preg_match('/^[A-Z]/', $tagName) === 1) {
                    return $this->parseComponentTag($token);
                }
                return null;
        }
    }

    /**
     * Parse de tag de fechamento
     */
    private function parseCloseTag(): ?object
    {
        $token = $this->current();
        $this->advance();

        return match ($token['name']) {
            'If', 'Foreach', 'Block' => new CloseTagNode($token['name']),
            default => null,
        };
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
        return new IfNode($this->extractAttributeValue($token['attributes'], 'condition'));
    }

    /**
     * Parse de tag ElseIf
     */
    private function parseElseIfTag(array $token): ElseIfNode
    {
        return new ElseIfNode($this->extractAttributeValue($token['attributes'], 'condition'));
    }

    /**
     * Parse de tag Block
     * 
     * @param array $token Token da tag
     * @return BlockNode
     */
    private function parseBlockTag(array $token): BlockNode
    {
        return new BlockNode($this->extractAttributeValue($token['attributes'], 'name'));
    }

    /**
     * Parse de tag Foreach
     * 
     * @param array $token Token da tag
     * @return ForeachNode
     */
    private function parseForEachTag(array $token): ForeachNode
    {
        $items = $this->extractAttributeValue($token['attributes'], 'items');
        $as = $this->extractAttributeValue($token['attributes'], 'as');

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
        if (trim($attributesStr) === '') {
            return $attributes;
        }

        preg_match_all('/(\w+)\s*=\s*(?:\{\{\s*(.+?)\s*\}\}|["\']([^"\']*)["\'])/s', $attributesStr, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = $match[1];
            $value = isset($match[2]) && $match[2] !== ''
                ? trim($match[2])
                : var_export($match[3] ?? '', true);
            $attributes[$name] = trim($value);
        }

        return $attributes;
    }

    private function extractAttributeValue(string $attributes, string $name): string
    {
        $value = '';

        if (preg_match('/' . preg_quote($name, '/') . '\s*=\s*\{\{\s*(.*?)\s*\}\}/s', $attributes, $matches) === 1) {
            $value = trim($matches[1]);
        } elseif (preg_match('/' . preg_quote($name, '/') . '\s*=\s*"([^"]*)"/s', $attributes, $matches) === 1) {
            $value = trim($matches[1]);
        } elseif (preg_match('/' . preg_quote($name, '/') . '\s*=\s*\'([^\']*)\'/s', $attributes, $matches) === 1) {
            $value = trim($matches[1]);
        }

        if (preg_match('/^\{\{\s*(.*?)\s*\}\}$/s', $value, $dynamic) === 1) {
            return trim($dynamic[1]);
        }

        return $value;
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

class ElseIfNode
{
    public function __construct(
        public string $condition
    ) {}
}

class CloseTagNode
{
    public function __construct(
        public string $name
    ) {}
}
