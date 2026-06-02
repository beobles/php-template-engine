<?php

namespace Beobles\Core\View\Directives;

use Beobles\Core\View\Abstract\AbstractDirective;

class IfDirective extends AbstractDirective
{
    public function getName(): string
    {
        return 'If';
    }

    public function parseAttributes(string $attributes): array
    {
        preg_match('/condition\s*=\s*["\']?\{\{(.+?)\}\}["\']?/s', $attributes, $matches);
        return ['condition' => trim($matches[1] ?? '')];
    }
}
