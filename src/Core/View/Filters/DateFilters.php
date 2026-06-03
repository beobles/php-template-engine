<?php

namespace Core\View\Filters;

class DateFilters
{
    public static function definitions(): array
    {
        return [
            'date' => fn($v, $format = 'd/m/Y') => date((string) $format, is_numeric($v) ? (int) $v : strtotime((string) $v)),
        ];
    }
}
