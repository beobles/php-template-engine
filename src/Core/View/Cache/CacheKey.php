<?php

namespace Core\View\Cache;

class CacheKey
{
    /** @param array<int, string> $dependencies */
    public function forTemplate(string $path, array $dependencies = []): string
    {
        $signature = $path;
        foreach ($dependencies as $dep) {
            $signature .= '|' . $dep;
            if (is_file($dep)) {
                $signature .= ':' . (string) filemtime($dep);
            }
        }

        return 'template_' . md5($signature);
    }
}
