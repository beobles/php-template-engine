<?php

namespace Core\View\Cache;

class FileWatcher
{
    /** @var array<string, int|false> */
    private array $mtimes = [];

    /** @param array<int, string> $paths */
    public function hasChanged(array $paths): bool
    {
        $changed = false;

        foreach ($paths as $path) {
            $mtime = is_file($path) ? filemtime($path) : false;
            if (!array_key_exists($path, $this->mtimes) || $this->mtimes[$path] !== $mtime) {
                $changed = true;
                $this->mtimes[$path] = $mtime;
            }
        }

        return $changed;
    }
}
