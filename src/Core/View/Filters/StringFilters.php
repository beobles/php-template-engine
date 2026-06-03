<?php

namespace Core\View\Filters;

class StringFilters
{
    public static function definitions(): array
    {
        return [
            'uppercase' => fn($v) => strtoupper((string) $v),
            'lowercase' => fn($v) => strtolower((string) $v),
            'ucfirst' => fn($v) => ucfirst((string) $v),
            'trim' => fn($v) => trim((string) $v),
            'truncate' => fn($v, $len = 50, $suffix = '...') => strlen((string) $v) > (int) $len ? substr((string) $v, 0, (int) $len) . $suffix : (string) $v,
            'slug' => function ($v) {
                $v = strtolower((string) $v);
                $v = preg_replace('/[^a-z0-9]+/', '-', $v);
                return trim((string) $v, '-');
            },
        ];
    }
}
