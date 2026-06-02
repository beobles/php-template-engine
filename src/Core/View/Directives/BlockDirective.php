<?php

namespace Beobles\Core\View\Directives;

use Beobles\Core\View\Abstract\AbstractDirective;

class BlockDirective extends AbstractDirective
{
    public function getName(): string
    {
        return 'Block';
    }

    public function parseAttributes(string $attributes): array
    {
        preg_match('/name\s*=\s*["\']([^"\']+)["\']/s', $attributes, $matches);
        return ['name' => trim($matches[1] ?? '')];
    }
}
