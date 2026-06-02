<?php

namespace Beobles\Core\View\Filters;

class NumberFilters
{
    public static function definitions(): array
    {
        return [
            'currency' => fn($v, $currency = 'BRL') => $currency === 'BRL' ? 'R$ ' . number_format((float) $v, 2, ',', '.') : '$' . number_format((float) $v, 2, '.', ','),
            'number_format' => fn($v, $decimals = 0) => number_format((float) $v, (int) $decimals, ',', '.'),
            'abs' => fn($v) => abs((float) $v),
        ];
    }
}
