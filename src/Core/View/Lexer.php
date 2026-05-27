<?php

namespace Beobles\Core\View;

use Beobles\Core\View\Exceptions\SyntaxException;

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
            // Detectar keywords
            if (preg_match('/^extends\b/', substr($content, $pos)) === 1) {
                $tokens[] = ['type' => 'KEYWORD', 'value' => 'extends'];
                $pos += 7;
                continue;
            }

            if (preg_match('/^import\b/', substr($content, $pos)) === 1) {
                $tokens[] = ['type' => 'KEYWORD', 'value' => 'import'];
                $pos += 6;
                continue;
            }

            // Detectar tag de fechamento
            if ($content[$pos] === '<' && preg_match('/^<\/([A-Z][a-zA-Z0-9]*)\s*>/', substr($content, $pos), $matches)) {
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
            if ($content[$pos] === '<' && preg_match('/^<([A-Z][a-zA-Z0-9]*)/', substr($content, $pos), $matches)) {
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
                if (in_array($content[$pos + $textLength], ['<', '{'])) {
                    // Verifica se é realmente um token
                    if (preg_match('/^<[A-Z]/', substr($content, $pos + $textLength))) {
                        break;
                    }
                    if (preg_match('/^<\/[A-Z]/', substr($content, $pos + $textLength))) {
                        break;
                    }
                    if (strpos($content, '{{', $pos + $textLength) === $pos + $textLength ||
                        strpos($content, '{!', $pos + $textLength) === $pos + $textLength) {
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
