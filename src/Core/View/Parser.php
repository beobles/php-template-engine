<?php

namespace Core\View;

use Core\View\Directives\BlockDirective;
use Core\View\Directives\DirectiveRegistry;
use Core\View\Directives\ForeachDirective;
use Core\View\Directives\IfDirective;
use Core\View\Directives\IncludeDirective;
use Core\View\Directives\SetDirective;
use Core\View\Exceptions\ParserException;
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

class Parser
{
    /** @var array<int, array<string, mixed>> */
    private array $tokens = [];
    private int $position = 0;
    private DirectiveRegistry $directives;

    public function __construct(?DirectiveRegistry $directiveRegistry = null)
    {
        $this->directives = $directiveRegistry ?? new DirectiveRegistry();
        $this->registerDefaults();
    }

    /** @param array<int, array<string, mixed>> $tokens
     *  @return array<int, NodeInterface>
     */
    public function parse(array $tokens): array
    {
        $this->tokens = $tokens;
        $this->position = 0;

        return $this->parseNodes();
    }

    /** @return array<int, NodeInterface> */
    private function parseNodes(?string $stopTag = null): array
    {
        $nodes = [];

        while (($token = $this->current()) !== null) {
            if ($token['type'] === 'TAG_CLOSE') {
                if ($stopTag !== null && strcasecmp($token['name'], $stopTag) === 0) {
                    $this->advance();
                    return $nodes;
                }

                $this->error("Unexpected closing tag </{$token['name']}>", $token);
            }

            $nodes[] = $this->parseNode();
        }

        if ($stopTag !== null) {
            $this->error("Unclosed tag <{$stopTag}>", $this->tokens[count($this->tokens) - 1] ?? ['line' => 1, 'column' => 1, 'source' => 'template']);
        }

        return $nodes;
    }

    private function parseNode(): NodeInterface
    {
        $token = $this->current();
        if ($token === null) {
            $this->error('Unexpected end of template', ['line' => 1, 'column' => 1, 'source' => 'template']);
        }

        return match ($token['type']) {
            'TEXT' => $this->parseTextNode(),
            'EXPRESSION' => $this->parseExpressionNode(),
            'RAW' => $this->parseRawNode(),
            'TAG_OPEN' => $this->parseTagNode($token),
            default => $this->error('Unexpected token type: ' . $token['type'], $token),
        };
    }

    private function parseTextNode(): TextNode
    {
        $token = $this->consume('TEXT');
        return new TextNode($token['value'], $token['line'], $token['column']);
    }

    private function parseExpressionNode(): ExpressionNode
    {
        $token = $this->consume('EXPRESSION');
        return new ExpressionNode($token['value'], $token['line'], $token['column']);
    }

    private function parseRawNode(): RawNode
    {
        $token = $this->consume('RAW');
        return new RawNode($token['value'], $token['line'], $token['column']);
    }

    private function parseTagNode(array $token): NodeInterface
    {
        $name = $token['name'];

        return match (strtolower($name)) {
            'if' => $this->parseIfNode(),
            'foreach' => $this->parseForeachNode(),
            'block' => $this->parseBlockNode(),
            'set' => $this->parseSetNode(),
            'include' => $this->parseIncludeNode(),
            'elseif', 'else' => $this->error("Unexpected <{$name}> without matching <If>", $token),
            default => $this->parseComponentNode(),
        };
    }

    private function parseIfNode(): IfNode
    {
        $ifToken = $this->consume('TAG_OPEN');
        $directive = $this->directives->get('if');
        $attrs = $directive ? $directive->parseAttributes($ifToken['attributes']) : [];
        $condition = trim((string) ($attrs['condition'] ?? ''));

        if ($condition === '') {
            $this->error('<If> requires condition attribute', $ifToken);
        }

        $branches = [];
        $branches[] = ['condition' => $condition, 'nodes' => $this->parseUntilIfBoundary()];

        while (($token = $this->current()) !== null && $token['type'] === 'TAG_OPEN') {
            if (strcasecmp($token['name'], 'ElseIf') === 0) {
                $elseifToken = $this->consume('TAG_OPEN');
                $attrs = $directive ? $directive->parseAttributes($elseifToken['attributes']) : [];
                $elseifCondition = trim((string) ($attrs['condition'] ?? ''));
                if ($elseifCondition === '') {
                    $this->error('<ElseIf> requires condition attribute', $elseifToken);
                }

                $branches[] = ['condition' => $elseifCondition, 'nodes' => $this->parseUntilIfBoundary()];
                continue;
            }

            if (strcasecmp($token['name'], 'Else') === 0) {
                $this->consume('TAG_OPEN');
                $branches[] = ['condition' => null, 'nodes' => $this->parseNodes('Else')];
                break;
            }

            break;
        }

        $this->consumeWhitespaceText();

        $closeIf = $this->current();
        if ($closeIf === null || $closeIf['type'] !== 'TAG_CLOSE' || strcasecmp($closeIf['name'], 'If') !== 0) {
            $this->error('Missing closing </If>', $ifToken);
        }

        $this->advance();

        return new IfNode($branches, $ifToken['line'], $ifToken['column']);
    }

    /** @return array<int, NodeInterface> */
    private function parseUntilIfBoundary(): array
    {
        $nodes = [];

        while (($token = $this->current()) !== null) {
            if ($token['type'] === 'TAG_OPEN' && in_array(strtolower($token['name']), ['elseif', 'else'], true)) {
                return $nodes;
            }

            if ($token['type'] === 'TAG_CLOSE' && strcasecmp($token['name'], 'If') === 0) {
                return $nodes;
            }

            $nodes[] = $this->parseNode();
        }

        return $nodes;
    }

    private function parseForeachNode(): ForeachNode
    {
        $token = $this->consume('TAG_OPEN');
        $directive = $this->directives->get('foreach');
        $attrs = $directive ? $directive->parseAttributes($token['attributes']) : [];

        $items = trim((string) ($attrs['items'] ?? ''));
        $as = trim((string) ($attrs['as'] ?? ''));

        if ($items === '' || $as === '') {
            $this->error('<Foreach> requires items and as attributes', $token);
        }

        $children = $this->parseNodes('Foreach');

        return new ForeachNode($items, $as, $children, $token['line'], $token['column']);
    }

    private function parseBlockNode(): BlockNode
    {
        $token = $this->consume('TAG_OPEN');
        $directive = $this->directives->get('block');
        $attrs = $directive ? $directive->parseAttributes($token['attributes']) : [];
        $name = trim((string) ($attrs['name'] ?? ''));

        if ($name === '') {
            $this->error('<Block> requires name attribute', $token);
        }

        $children = $token['self_closing'] ? [] : $this->parseNodes('Block');

        return new BlockNode($name, $children, $token['line'], $token['column']);
    }

    private function parseSetNode(): SetNode
    {
        $token = $this->consume('TAG_OPEN');
        $directive = $this->directives->get('set');
        $attrs = $directive ? $directive->parseAttributes($token['attributes']) : [];
        $variable = trim((string) ($attrs['var'] ?? ''));
        $value = trim((string) ($attrs['value'] ?? ''));

        if ($variable === '' || $value === '') {
            $this->error('<Set> requires var and value attributes', $token);
        }

        if (!$token['self_closing']) {
            $this->parseNodes('Set');
        }

        return new SetNode($variable, $value, $token['line'], $token['column']);
    }

    private function parseIncludeNode(): IncludeNode
    {
        $token = $this->consume('TAG_OPEN');
        $directive = $this->directives->get('include');
        $attrs = $directive ? $directive->parseAttributes($token['attributes']) : [];
        $path = trim((string) ($attrs['path'] ?? ''));

        if ($path === '') {
            $this->error('<Include> requires path attribute', $token);
        }

        if (!$token['self_closing']) {
            $this->parseNodes('Include');
        }

        return new IncludeNode($path, $attrs['data'] ?? null, $token['line'], $token['column']);
    }

    private function parseComponentNode(): ComponentNode
    {
        $token = $this->consume('TAG_OPEN');
        $attributes = $this->parseAttributes($token['attributes']);

        if (!$token['self_closing']) {
            $this->parseNodes($token['name']);
        }

        return new ComponentNode($token['name'], $attributes, $token['line'], $token['column']);
    }

    /** @return array<string, string> */
    private function parseAttributes(string $attributesStr): array
    {
        if (trim($attributesStr) === '') {
            return [];
        }

        $attributes = [];
        preg_match_all('/([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(?:\{\{(.+?)\}\}|"([^"]*)"|\'([^\']*)\')/s', $attributesStr, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = $match[1];
            $value = $match[2] ?? $match[3] ?? $match[4] ?? '';
            $attributes[$name] = trim($value);
        }

        return $attributes;
    }

    /** @return array<string, mixed> */
    private function consume(string $type): array
    {
        $token = $this->current();
        if ($token === null || $token['type'] !== $type) {
            $this->error("Expected {$type}", $token ?? ['line' => 1, 'column' => 1, 'source' => 'template']);
        }

        $this->advance();

        return $token;
    }

    private function current(): ?array
    {
        return $this->tokens[$this->position] ?? null;
    }

    private function advance(): void
    {
        $this->position++;
    }

    private function consumeWhitespaceText(): void
    {
        while (($token = $this->current()) !== null && $token['type'] === 'TEXT' && trim((string) $token['value']) === '') {
            $this->advance();
        }
    }

    private function registerDefaults(): void
    {
        foreach ([
            new IfDirective(),
            new ForeachDirective(),
            new BlockDirective(),
            new SetDirective(),
            new IncludeDirective(),
        ] as $directive) {
            $this->directives->register($directive);
        }
    }

    private function error(string $message, array $token): never
    {
        $line = (int) ($token['line'] ?? 1);
        $column = (int) ($token['column'] ?? 1);
        $source = (string) ($token['source'] ?? 'template');

        throw new ParserException(sprintf('%s at %s:%d:%d', $message, $source, $line, $column));
    }
}
