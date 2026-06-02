<?php

namespace Core\View\Filters;

class StringFilters
{
    public static function definitions(): array
    {
        return [
            'uppercase' => fn($v) => self::toUpper((string) $v),
            'lowercase' => fn($v) => self::toLower((string) $v),
            'ucfirst' => fn($v) => self::mbUcfirst((string) $v),
            'trim' => fn($v) => trim((string) $v),
            'truncate' => fn($v, $len = 50, $suffix = '...') => mb_strlen((string) $v) > (int) $len ? mb_substr((string) $v, 0, (int) $len) . $suffix : (string) $v,
            'slug' => function ($v): string {
                $v = self::toLower((string) $v);
                if (function_exists('iconv')) {
                    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
                    if (is_string($transliterated) && $transliterated !== '') {
                        $v = $transliterated;
                    }
                }
                $v = preg_replace('/[^a-z0-9]+/', '-', $v) ?? '';
                return trim($v, '-');
            },
        ];
    }

    private static function toUpper(string $value): string
    {
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($value, 'UTF-8');
        }

        return strtoupper($value);
    }

    private static function toLower(string $value): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtolower($value);
    }

    private static function mbUcfirst(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            $firstChar = mb_substr($value, 0, 1, 'UTF-8');
            $remaining = mb_substr($value, 1, null, 'UTF-8');
            return self::toUpper($firstChar) . $remaining;
        }

        return ucfirst($value);
    }
}
