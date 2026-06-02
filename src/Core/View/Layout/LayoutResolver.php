<?php

namespace Core\View\Layout;

class LayoutResolver
{
    public function extractExtendsPath(string $content): ?string
    {
        if (preg_match('/^\s*extends\s+["\']([^"\']+)["\'];?/m', $content, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    public function stripExtendsStatement(string $content): string
    {
        return preg_replace('/^\s*extends\s+["\']([^"\']+)["\'];?\s*/m', '', $content, 1) ?? $content;
    }
}
