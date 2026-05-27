<?php

namespace Core\View;

use Core\View\Exceptions\SyntaxException;

/**
 * Tokenizador do template
 * Converte string do template em tokens
 */
class Lexer
{
    private const TOKEN_TEXT = 'TEXT';
    private const TOKEN_TAG_OPEN = 'TAG_OPEN';
    private const TOKEN_TAG_CLOSE = 'TAG_CLOSE';
    private const TOKEN_EXPRESSION = 'EXPRESSION';
    private const TOKEN_ATTRIBUTE = 'ATTRIBUTE';
    private const TOKEN_KEYWORD = 'KEYWORD';

    /**
     * Tokeniza o conteúdo do template
     * 
     * @param string $content Conteúdo do template
     * @return array Tokens
     */
    public function tokenize(string $content): array
    {
        $tokens = [];
        $length = strlen($content);
        $pos = 0;

        while ($pos < $length) {
            $slice = substr($content, $pos);

            // Detectar diretivas por keyword apenas no início da linha.
            if ($this->isDirectiveLineStart($content, $pos)) {
                $directive = $this->extractKeywordDirective($content, $pos);
                if ($directive !== null) {
                    $tokens[] = $directive;
                    $pos += $directive['length'];
                    continue;
                }
            }

            // Detectar tag de fechamento
            if ($content[$pos] === '<' && preg_match('/^<\/([A-Z][a-zA-Z0-9]*)\s*>/', $slice, $matches)) {
                $fullMatch = $matches[0];
                $tokens[] = [
                    'type' => 'TAG_CLOSE',
                    'name' => $matches[1],
                    'length' => strlen($fullMatch),
                    'value' => $fullMatch
                ];
                $pos += strlen($fullMatch);
                continue;
            }

            // Detectar tag de abertura
            if ($content[$pos] === '<' && preg_match('/^<([A-Z][a-zA-Z0-9]*)/', $slice, $matches)) {
                // Isso é uma tag customizada
                $token = $this->extractTag($content, $pos);
                $tokens[] = $token;
                $pos += $token['length'];
                continue;
            }

            // Detectar expressão {{ }}
            if (strpos($content, '{{', $pos) === $pos) {
                $token = $this->extractExpression($content, $pos);
                $tokens[] = $token;
                $pos += $token['length'];
                continue;
            }

            // Detectar raw output {! !}
            if (strpos($content, '{!', $pos) === $pos) {
                $token = $this->extractRaw($content, $pos);
                $tokens[] = $token;
                $pos += $token['length'];
                continue;
            }

            // Texto normal
            $textLength = 0;
            while ($pos + $textLength < $length) {
                $cursor = $pos + $textLength;
                $char = $content[$cursor];

                if ($char === '<') {
                    $next = $content[$cursor + 1] ?? '';
                    $next2 = $content[$cursor + 2] ?? '';

                    if ($next !== '' && ctype_upper($next)) {
                        break;
                    }
                    if ($next === '/' && $next2 !== '' && ctype_upper($next2)) {
                        break;
                    }
                }

                if ($char === '{') {
                    $next = $content[$cursor + 1] ?? '';
                    if ($next === '{' || $next === '!') {
                        break;
                    }
                }
                $textLength++;
            }

            if ($textLength > 0) {
                $tokens[] = [
                    'type' => 'TEXT',
                    'value' => substr($content, $pos, $textLength),
                    'length' => $textLength
                ];
                $pos += $textLength;
            }
        }

        return $tokens;
    }

    private function isDirectiveLineStart(string $content, int $pos): bool
    {
        for ($i = $pos - 1; $i >= 0; $i--) {
            $char = $content[$i];
            if ($char === "\n" || $char === "\r") {
                return true;
            }
            if ($char !== ' ' && $char !== "\t") {
                return false;
            }
        }

        return true;
    }

    private function extractKeywordDirective(string $content, int $pos): ?array
    {
        $length = strlen($content);
        $cursor = $pos;
        while ($cursor < $length && ($content[$cursor] === ' ' || $content[$cursor] === "\t")) {
            $cursor++;
        }

        if (preg_match('/\G(extends|import)\b/A', $content, $m, 0, $cursor) !== 1) {
            return null;
        }

        $cursor += strlen($m[1]);
        $quote = null;

        while ($cursor < $length) {
            $char = $content[$cursor];

            if ($quote !== null) {
                if ($char === $quote && ($cursor === 0 || $content[$cursor - 1] !== '\\')) {
                    $quote = null;
                }
                $cursor++;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $cursor++;
                continue;
            }

            if ($char === ';') {
                $directive = substr($content, $pos, ($cursor - $pos) + 1);
                return [
                    'type' => self::TOKEN_KEYWORD,
                    'value' => trim($directive),
                    'length' => strlen($directive),
                ];
            }

            if ($char === "\n" || $char === "\r") {
                return null;
            }

            $cursor++;
        }

        throw new SyntaxException("Unclosed keyword directive at position $pos");
    }

    /**
     * Extrai uma tag customizada
     * 
     * @param string $content Conteúdo
     * @param int $pos Posição atual
     * @return array Token da tag
     */
    private function extractTag(string $content, int $pos): array
    {
        preg_match('/^<([A-Z][a-zA-Z0-9]*)([^>]*)\s*\/?>/s', substr($content, $pos), $matches);

        if (empty($matches)) {
            throw new SyntaxException("Invalid tag at position $pos");
        }

        $tagName = $matches[1];
        $attributes = trim($matches[2]);
        $fullMatch = $matches[0];
        $selfClosing = str_ends_with($fullMatch, '/>');

        return [
            'type' => 'TAG',
            'name' => $tagName,
            'attributes' => $attributes,
            'self_closing' => $selfClosing,
            'length' => strlen($fullMatch),
            'value' => $fullMatch
        ];
    }

    /**
     * Extrai uma expressão {{ }}
     * 
     * @param string $content Conteúdo
     * @param int $pos Posição atual
     * @return array Token da expressão
     */
    private function extractExpression(string $content, int $pos): array
    {
        $start = $pos + 2;
        $end = strpos($content, '}}', $start);

        if ($end === false) {
            throw new SyntaxException("Unclosed expression at position $pos");
        }

        $expression = substr($content, $start, $end - $start);
        $length = $end - $pos + 2;

        return [
            'type' => 'EXPRESSION',
            'value' => trim($expression),
            'length' => $length
        ];
    }

    /**
     * Extrai raw output {! !}
     * 
     * @param string $content Conteúdo
     * @param int $pos Posição atual
     * @return array Token raw
     */
    private function extractRaw(string $content, int $pos): array
    {
        $start = $pos + 2;
        $end = strpos($content, '!}', $start);

        if ($end === false) {
            throw new SyntaxException("Unclosed raw output at position $pos");
        }

        $expression = substr($content, $start, $end - $start);
        $length = $end - $pos + 2;

        return [
            'type' => 'RAW',
            'value' => trim($expression),
            'length' => $length
        ];
    }
}
