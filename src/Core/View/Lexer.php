<?php

namespace Core\View;

use Core\View\Exceptions\SyntaxException;

class Lexer
{
    /** @return array<int, array<string, mixed>> */
    public function tokenize(string $content, string $sourceFile = 'template'): array
    {
        $tokens = [];
        $length = strlen($content);
        $pos = 0;
        $line = 1;
        $column = 1;

        while ($pos < $length) {
            if (substr($content, $pos, 2) === '{{') {
                $tokens[] = $this->extractExpression($content, $pos, $line, $column, '{{', '}}', 'EXPRESSION', $sourceFile);
                continue;
            }

            if (substr($content, $pos, 2) === '{!') {
                $tokens[] = $this->extractExpression($content, $pos, $line, $column, '{!', '!}', 'RAW', $sourceFile);
                continue;
            }

            if ($content[$pos] === '<' && preg_match('/^<\/?[A-Z]/', substr($content, $pos)) === 1) {
                $tokens[] = $this->extractTag($content, $pos, $line, $column, $sourceFile);
                continue;
            }

            $textStart = $pos;
            $textLine = $line;
            $textColumn = $column;

            while ($pos < $length) {
                if (substr($content, $pos, 2) === '{{' || substr($content, $pos, 2) === '{!' || ($content[$pos] === '<' && preg_match('/^<\/?[A-Z]/', substr($content, $pos)) === 1)) {
                    break;
                }

                if ($content[$pos] === "\n") {
                    $line++;
                    $column = 1;
                    $pos++;
                    continue;
                }

                $pos++;
                $column++;
            }

            if ($pos > $textStart) {
                $tokens[] = [
                    'type' => 'TEXT',
                    'value' => substr($content, $textStart, $pos - $textStart),
                    'line' => $textLine,
                    'column' => $textColumn,
                    'source' => $sourceFile,
                ];
            }
        }

        return $tokens;
    }

    /** @return array<string, mixed> */
    private function extractExpression(string $content, int &$pos, int &$line, int &$column, string $startDelim, string $endDelim, string $type, string $sourceFile): array
    {
        $startPos = $pos;
        $startLine = $line;
        $startColumn = $column;

        $pos += strlen($startDelim);
        $column += strlen($startDelim);

        $end = strpos($content, $endDelim, $pos);
        if ($end === false) {
            throw new SyntaxException("Unclosed expression at {$sourceFile}:{$startLine}:{$startColumn}");
        }

        $value = trim(substr($content, $pos, $end - $pos));
        $raw = substr($content, $startPos, ($end + strlen($endDelim)) - $startPos);

        $this->advancePosition($raw, $pos = $end + strlen($endDelim), $line, $column, $startPos);

        return [
            'type' => $type,
            'value' => $value,
            'line' => $startLine,
            'column' => $startColumn,
            'source' => $sourceFile,
        ];
    }

    /** @return array<string, mixed> */
    private function extractTag(string $content, int &$pos, int &$line, int &$column, string $sourceFile): array
    {
        $start = $pos;
        $startLine = $line;
        $startColumn = $column;
        $length = strlen($content);

        $inSingle = false;
        $inDouble = false;
        $mustacheDepth = 0;

        while ($pos < $length) {
            if (!$inSingle && !$inDouble && $pos + 1 < $length) {
                $pair = $content[$pos] . $content[$pos + 1];

                if ($pair === '{{') {
                    $mustacheDepth++;
                    $pos += 2;
                    $column += 2;
                    continue;
                }

                if ($pair === '}}' && $mustacheDepth > 0) {
                    $mustacheDepth--;
                    $pos += 2;
                    $column += 2;
                    continue;
                }
            }

            $char = $content[$pos];

            if ($mustacheDepth === 0 && $char === "'" && !$inDouble) {
                $inSingle = !$inSingle;
            } elseif ($mustacheDepth === 0 && $char === '"' && !$inSingle) {
                $inDouble = !$inDouble;
            } elseif ($char === '>' && !$inSingle && !$inDouble && $mustacheDepth === 0) {
                $pos++;
                $column++;
                break;
            }

            if ($char === "\n") {
                $line++;
                $column = 1;
            } else {
                $column++;
            }

            $pos++;
        }

        $rawTag = substr($content, $start, $pos - $start);
        if (!preg_match('/^<\s*(\/)?\s*([A-Z][A-Za-z0-9]*)\b(.*?)\s*(\/?)>$/s', $rawTag, $matches)) {
            throw new SyntaxException("Invalid tag at {$sourceFile}:{$startLine}:{$startColumn}");
        }

        $isClose = $matches[1] === '/';
        $name = $matches[2];
        $attributes = trim($matches[3] ?? '');
        $selfClosing = !$isClose && ($matches[4] === '/');

        return [
            'type' => $isClose ? 'TAG_CLOSE' : 'TAG_OPEN',
            'name' => $name,
            'attributes' => $attributes,
            'self_closing' => $selfClosing,
            'value' => $rawTag,
            'line' => $startLine,
            'column' => $startColumn,
            'source' => $sourceFile,
        ];
    }

    private function advancePosition(string $text, int $newPos, int &$line, int &$column, int $oldPos): void
    {
        for ($i = 0; $i < strlen($text); $i++) {
            if ($text[$i] === "\n") {
                $line++;
                $column = 1;
            } else {
                $column++;
            }
        }
    }
}
