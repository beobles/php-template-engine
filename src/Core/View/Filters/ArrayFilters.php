<?php

namespace Beobles\Core\View\Filters;

class ArrayFilters
{
    public static function definitions(): array
    {
        return [
            'count' => fn($v) => is_countable($v) ? count($v) : 0,
            'first' => fn($v) => is_array($v) ? ($v[array_key_first($v)] ?? null) : null,
            'last' => fn($v) => is_array($v) ? ($v[array_key_last($v)] ?? null) : null,
            'join' => fn($v, $sep = ', ') => is_array($v) ? implode((string) $sep, $v) : (string) $v,
            'json' => fn($v) => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ];
    }
}
