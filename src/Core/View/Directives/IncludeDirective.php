<?php

namespace Beobles\Core\View\Directives;

use Beobles\Core\View\Abstract\AbstractDirective;

class IncludeDirective extends AbstractDirective
{
    public function getName(): string
    {
        return 'Include';
    }

    public function parseAttributes(string $attributes): array
    {
        preg_match('/path\s*=\s*["\']([^"\']+)["\']/s', $attributes, $pathMatches);
        preg_match('/data\s*=\s*\{\{(.+?)\}\}/s', $attributes, $dataMatches);

        return [
            'path' => trim($pathMatches[1] ?? ''),
            'data' => isset($dataMatches[1]) ? trim($dataMatches[1]) : null,
        ];
    }
}
