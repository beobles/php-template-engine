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
            'truncate' => fn($v, $len = 50, $suffix = '...') => self::truncate((string) $v, (int) $len, (string) $suffix),
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

    private static function truncate(string $value, int $length, string $suffix): string
    {
        if ($length < 0) {
            $length = 0;
        }

        if (self::strLength($value) <= $length) {
            return $value;
        }

        return self::strSlice($value, 0, $length) . $suffix;
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

        $firstChar = self::strSlice($value, 0, 1);
        $remaining = self::strSlice($value, 1);
        if ($firstChar === '') {
            return '';
        }

        return self::toUpper($firstChar) . $remaining;
    }

    private static function strLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }

    private static function strSlice(string $value, int $start, ?int $length = null): string
    {
        if (function_exists('mb_substr')) {
            $result = mb_substr($value, $start, $length, 'UTF-8');
            return $result === false ? '' : $result;
        }

        $result = $length === null ? substr($value, $start) : substr($value, $start, $length);
        return $result === false ? '' : $result;
    }
}
