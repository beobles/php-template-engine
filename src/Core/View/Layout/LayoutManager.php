<?php

namespace Beobles\Core\View\Layout;

use Beobles\Core\View\TemplateResolver;

class LayoutManager
{
    private LayoutResolver $layoutResolver;

    public function __construct(private TemplateResolver $templateResolver)
    {
        $this->layoutResolver = new LayoutResolver();
    }

    public function merge(string $templatePath, string $content): string
    {
        $parent = $this->layoutResolver->extractExtendsPath($content);
        if ($parent === null) {
            return $content;
        }

        $childBlocks = $this->extractBlocks($content);
        $parentPath = $this->templateResolver->resolve($parent);

        if (!is_file($parentPath)) {
            return $this->layoutResolver->stripExtendsStatement($content);
        }

        $parentContent = (string) file_get_contents($parentPath);

        $merged = preg_replace_callback(
            '/<Block\s+name\s*=\s*["\']([^"\']+)["\']\s*>(.*?)<\/Block>/si',
            function (array $matches) use ($childBlocks): string {
                $name = $matches[1];
                return $childBlocks[$name] ?? $matches[2];
            },
            $parentContent
        );

        return $merged ?? $parentContent;
    }

    /** @return array<string, string> */
    private function extractBlocks(string $content): array
    {
        $content = $this->layoutResolver->stripExtendsStatement($content);
        preg_match_all('/<Block\s+name\s*=\s*["\']([^"\']+)["\']\s*>(.*?)<\/Block>/si', $content, $matches, PREG_SET_ORDER);
        $blocks = [];

        foreach ($matches as $match) {
            $blocks[$match[1]] = $match[2];
        }

        return $blocks;
    }
}
