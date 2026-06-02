<?php

namespace Core\View\Filters;

class ArrayFilters
{
    public static function definitions(): array
    {
        return [
            'count' => fn($v) => is_countable($v) ? count($v) : 0,
            'first' => fn($v) => is_array($v) ? ($v[array_key_first($v)] ?? null) : null,
            'last' => fn($v) => is_array($v) ? ($v[array_key_last($v)] ?? null) : null,
            'join' => fn($v, $sep = ', ') => is_array($v) ? implode((string) $sep, array_map([self::class, 'stringify'], $v)) : (string) $v,
            'json' => fn($v) => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '',
        ];
    }

    private static function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return is_string($json) ? $json : '';
    }
}
