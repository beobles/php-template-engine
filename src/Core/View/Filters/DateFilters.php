<?php

namespace Core\View\Filters;

class DateFilters
{
    public static function definitions(): array
    {
        return [
            'date' => function ($v, $format = 'd/m/Y'): string {
                $timestamp = is_numeric($v) ? (int) $v : strtotime((string) $v);
                return date((string) $format, $timestamp !== false ? $timestamp : time());
            },
        ];
    }
}
