<?php

namespace Beobles\Core\View;

use Beobles\Core\View\Nodes\BlockNode;
use Beobles\Core\View\Nodes\CloseTagNode;
use Beobles\Core\View\Nodes\ComponentNode;
use Beobles\Core\View\Nodes\ElseIfNode;
use Beobles\Core\View\Nodes\ElseNode;
use Beobles\Core\View\Nodes\ExpressionNode;
use Beobles\Core\View\Nodes\ForeachNode;
use Beobles\Core\View\Nodes\IfNode;
use Beobles\Core\View\Nodes\RawNode;
use Beobles\Core\View\Nodes\TextNode;

/**
 * Converte tokens em nós (AST plana) para compilação posterior.
 */
class Parser
{
    private array $tokens;
    private int $position = 0;

    /**
     * @param  array $tokens Tokens produzidos pela Lexer
     * @return array<\Beobles\Core\View\Nodes\NodeInterface>
     */
    public function parse(array $tokens): array
    {
        $this->tokens = $tokens;
        $this->position = 0;
        $nodes = [];

        while ($this->position < count($this->tokens)) {
            $token = $this->current();
            $node = match ($token['type'] ?? '') {
                'TEXT'       => $this->parseText(),
                'EXPRESSION' => $this->parseExpression(),
                'RAW'        => $this->parseRaw(),
                'TAG'        => $this->parseTag(),
                'TAG_CLOSE'  => $this->parseCloseTag(),
                'KEYWORD'    => $this->parseKeyword(),
                default      => null,
            };

            if ($node !== null) {
                $nodes[] = $node;
            }

            $this->advance();
        }

        return $nodes;
    }

    // -------------------------------------------------------------------------
    // Token-type handlers
    // -------------------------------------------------------------------------

    private function parseText(): TextNode
    {
        return new TextNode($this->current()['value']);
    }

    private function parseExpression(): ExpressionNode
    {
        return new ExpressionNode($this->current()['value']);
    }

    private function parseRaw(): RawNode
    {
        return new RawNode($this->current()['value']);
    }

    private function parseTag(): ?\Beobles\Core\View\Nodes\NodeInterface
    {
        $token = $this->current();
        $tagName = $token['name'];

        return match ($tagName) {
            'If'        => new IfNode($this->extractAttributeValue($token['attributes'], 'condition')),
            'ElseIf'    => new ElseIfNode($this->extractAttributeValue($token['attributes'], 'condition')),
            'Else'      => new ElseNode(),
            'Block'     => new BlockNode($this->extractAttributeValue($token['attributes'], 'name')),
            'Foreach'   => new ForeachNode(
                $this->extractAttributeValue($token['attributes'], 'items'),
                $this->extractAttributeValue($token['attributes'], 'as')
            ),
            'Component' => $this->parseComponentTag($token),
            default     => preg_match('/^[A-Z]/', $tagName) === 1
                ? $this->parseComponentTag($token)
                : null,
        };
    }

    private function parseCloseTag(): ?CloseTagNode
    {
        $token = $this->current();

        return match ($token['name']) {
            'If', 'Foreach', 'Block' => new CloseTagNode($token['name']),
            default                  => null,
        };
    }

    private function parseKeyword(): null
    {
        // Keywords (extends, import) reserved for future implementation
        return null;
    }

    // -------------------------------------------------------------------------
    // Component helper
    // -------------------------------------------------------------------------

    private function parseComponentTag(array $token): ComponentNode
    {
        return new ComponentNode($token['name'], $this->parseAttributes($token['attributes']));
    }

    // -------------------------------------------------------------------------
    // Attribute helpers
    // -------------------------------------------------------------------------

    /**
     * Extracts all key=>value attribute pairs from the raw attributes string.
     */
    private function parseAttributes(string $attributesStr): array
    {
        $attributes = [];
        if (trim($attributesStr) === '') {
            return $attributes;
        }

        preg_match_all('/(\w+)\s*=\s*(?:\{\{\s*(.+?)\s*\}\}|["\']([^"\']*)["\'])/s', $attributesStr, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name  = $match[1];
            $value = isset($match[2]) && $match[2] !== ''
                ? trim($match[2])
                : var_export($match[3] ?? '', true);
            $attributes[$name] = trim($value);
        }

        return $attributes;
    }

    /**
     * Extracts the value of a single named attribute from the raw attributes string.
     * Supports {{ expression }}, "string", and 'string' formats.
     * When a quoted value itself contains {{ }}, the delimiters are stripped.
     */
    private function extractAttributeValue(string $attributes, string $name): string
    {
        $qName = preg_quote($name, '/');

        // attribute="{{ expr }}" — expression in curly delimiters
        if (preg_match('/' . $qName . '\s*=\s*\{\{\s*(.*?)\s*\}\}/s', $attributes, $m) === 1) {
            return trim($m[1]);
        }
        // attribute="..." or attribute='...'
        if (preg_match('/' . $qName . '\s*=\s*"([^"]*)"/s', $attributes, $m) === 1) {
            return $this->unwrapCurly(trim($m[1]));
        }
        if (preg_match('/' . $qName . '\s*=\s*\'([^\']*)\'/s', $attributes, $m) === 1) {
            return $this->unwrapCurly(trim($m[1]));
        }

        return '';
    }

    /**
     * If the value is wrapped in {{ }}, strips the delimiters and returns the inner expression.
     */
    private function unwrapCurly(string $value): string
    {
        if (preg_match('/^\{\{\s*(.*?)\s*\}\}$/s', $value, $m) === 1) {
            return trim($m[1]);
        }
        return $value;
    }

    // -------------------------------------------------------------------------
    // Cursor helpers
    // -------------------------------------------------------------------------

    private function current(): array
    {
        return $this->tokens[$this->position] ?? ['type' => '', 'value' => '', 'name' => '', 'attributes' => ''];
    }

    private function advance(): void
    {
        $this->position++;
    }
}

