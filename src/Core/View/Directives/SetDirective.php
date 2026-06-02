<?php

namespace Beobles\Core\View\Directives;

use Beobles\Core\View\Abstract\AbstractDirective;

class SetDirective extends AbstractDirective
{
    public function getName(): string
    {
        return 'Set';
    }

    public function parseAttributes(string $attributes): array
    {
        preg_match('/var\s*=\s*["\']([^"\']+)["\']/s', $attributes, $varMatches);
        preg_match('/value\s*=\s*\{\{(.+?)\}\}/s', $attributes, $valueMatches);

        return [
            'var' => trim($varMatches[1] ?? ''),
            'value' => trim($valueMatches[1] ?? ''),
        ];
    }
}
