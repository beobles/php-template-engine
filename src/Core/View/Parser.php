<?php

namespace Core\View;

use Core\View\Exceptions\ParserException;
use Core\View\Nodes\BlockNode;
use Core\View\Nodes\CloseTagNode;
use Core\View\Nodes\ComponentNode;
use Core\View\Nodes\ElseIfNode;
use Core\View\Nodes\ElseNode;
use Core\View\Nodes\ExpressionNode;
use Core\View\Nodes\ForeachNode;
use Core\View\Nodes\IfNode;
use Core\View\Nodes\RawNode;
use Core\View\Nodes\TextNode;

/**
 * Converte tokens em nós (AST plana) para compilação posterior.
 */
class Parser
{
    private array $tokens;
    private int $position = 0;
    /** @var array<int, array{tag: string, hasElse: bool}> */
    private array $controlStack = [];

    /**
     * @param  array $tokens Tokens produzidos pela Lexer
     * @return array<\Core\View\Nodes\NodeInterface>
     */
    public function parse(array $tokens): array
    {
        $this->tokens = $tokens;
        $this->position = 0;
        $this->controlStack = [];
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

        if ($this->controlStack !== []) {
            $openTags = implode(', ', array_map(static fn(array $entry): string => $entry['tag'], $this->controlStack));
            throw new ParserException("Unclosed control tags: {$openTags}");
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

    private function parseTag(): ?\Core\View\Nodes\NodeInterface
    {
        $token = $this->current();
        $tagName = $token['name'];
        $isSelfClosing = (bool) ($token['self_closing'] ?? false);

        return match ($tagName) {
            'If'        => $this->parseIfTag($token['attributes'], $isSelfClosing),
            'ElseIf'    => $this->parseElseIfTag($token['attributes'], $isSelfClosing),
            'Else'      => $this->parseElseTag($isSelfClosing),
            'Block'     => $this->parseBlockTag($token['attributes'], $isSelfClosing),
            'Foreach'   => $this->parseForeachTag($token['attributes'], $isSelfClosing),
            'Component' => $this->parseComponentTag($token),
            default     => preg_match('/^[A-Z]/', $tagName) === 1
                ? $this->parseComponentTag($token)
                : null,
        };
    }

    private function parseCloseTag(): ?CloseTagNode
    {
        $token = $this->current();
        $name = $token['name'] ?? '';

        if ($name === 'Else') {
            $current = end($this->controlStack);
            if ($current === false || $current['tag'] !== 'If' || !$current['hasElse']) {
                throw new ParserException('Unexpected closing tag </Else> without an active <Else> block');
            }
            return null;
        }

        if (!in_array($name, ['If', 'Foreach', 'Block'], true)) {
            throw new ParserException("Unexpected closing tag </{$name}>");
        }

        $current = end($this->controlStack);
        if ($current === false || $current['tag'] !== $name) {
            $openTag = $current['tag'] ?? 'none';
            throw new ParserException("Mismatched closing tag </{$name}>. Current open tag: {$openTag}");
        }

        array_pop($this->controlStack);

        return new CloseTagNode($name);
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
        if (!(bool) ($token['self_closing'] ?? false)) {
            throw new ParserException(
                "Component tag <{$token['name']}> must be self-closing (use <{$token['name']} ... />)"
            );
        }

        return new ComponentNode($token['name'], $this->parseAttributes($token['attributes']));
    }

    private function parseIfTag(string $attributes, bool $isSelfClosing): IfNode
    {
        if ($isSelfClosing) {
            throw new ParserException('<If> cannot be self-closing');
        }

        $condition = $this->extractAttributeValue($attributes, 'condition');
        if ($condition === '') {
            throw new ParserException('<If> requires a non-empty condition attribute');
        }

        $this->controlStack[] = ['tag' => 'If', 'hasElse' => false];

        return new IfNode($condition);
    }

    private function parseElseIfTag(string $attributes, bool $isSelfClosing): ElseIfNode
    {
        if ($isSelfClosing) {
            throw new ParserException('<ElseIf> cannot be self-closing');
        }

        $current = end($this->controlStack);
        if ($current === false || $current['tag'] !== 'If') {
            throw new ParserException('<ElseIf> must be inside an <If> block');
        }

        if ($current['hasElse']) {
            throw new ParserException('<ElseIf> cannot appear after <Else> in the same <If> block');
        }

        $condition = $this->extractAttributeValue($attributes, 'condition');
        if ($condition === '') {
            throw new ParserException('<ElseIf> requires a non-empty condition attribute');
        }

        return new ElseIfNode($condition);
    }

    private function parseElseTag(bool $isSelfClosing): ElseNode
    {
        if ($isSelfClosing) {
            throw new ParserException('<Else> cannot be self-closing');
        }

        $stackIndex = array_key_last($this->controlStack);
        if ($stackIndex === null || $this->controlStack[$stackIndex]['tag'] !== 'If') {
            throw new ParserException('<Else> must be inside an <If> block');
        }

        if ($this->controlStack[$stackIndex]['hasElse']) {
            throw new ParserException('Only one <Else> is allowed per <If> block');
        }

        $this->controlStack[$stackIndex]['hasElse'] = true;

        return new ElseNode();
    }

    private function parseBlockTag(string $attributes, bool $isSelfClosing): BlockNode
    {
        if ($isSelfClosing) {
            throw new ParserException('<Block> cannot be self-closing');
        }

        $name = $this->extractAttributeValue($attributes, 'name');
        if ($name === '') {
            throw new ParserException('<Block> requires a non-empty name attribute');
        }

        $this->controlStack[] = ['tag' => 'Block', 'hasElse' => false];

        return new BlockNode($name);
    }

    private function parseForeachTag(string $attributes, bool $isSelfClosing): ForeachNode
    {
        if ($isSelfClosing) {
            throw new ParserException('<Foreach> cannot be self-closing');
        }

        $items = $this->extractAttributeValue($attributes, 'items');
        $as = $this->extractAttributeValue($attributes, 'as');

        if ($items === '') {
            throw new ParserException('<Foreach> requires a non-empty items attribute');
        }
        if ($as === '') {
            throw new ParserException('<Foreach> requires a non-empty as attribute');
        }

        $this->controlStack[] = ['tag' => 'Foreach', 'hasElse' => false];

        return new ForeachNode($items, $as);
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
