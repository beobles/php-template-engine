<?php

namespace Core\View\Directives;

use Core\View\Abstract\AbstractDirective;

class ForeachDirective extends AbstractDirective
{
    public function getName(): string
    {
        return 'Foreach';
    }

    public function parseAttributes(string $attributes): array
    {
        preg_match('/items\s*=\s*\{\{(.+?)\}\}/s', $attributes, $items);
        preg_match('/as\s*=\s*["\']([^"\']+)["\']/s', $attributes, $as);

        return [
            'items' => trim($items[1] ?? ''),
            'as' => trim($as[1] ?? ''),
        ];
    }
}
