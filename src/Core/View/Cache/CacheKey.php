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
                $mtime = filemtime($dep);
                $signature .= ':' . ($mtime !== false ? (string) $mtime : '0');
            }
        }

        return 'template_' . md5($signature);
    }
}
