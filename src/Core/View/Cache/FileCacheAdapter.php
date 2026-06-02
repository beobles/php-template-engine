<?php

namespace Core\View\Cache;

/**
 * Adaptador de cache em arquivo
 */
class FileCacheAdapter implements CacheInterface
{
    public function __construct(
        private string $cacheDir
    ) {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function get(string $key)
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return null;
        }

        return file_get_contents($file);
    }

    public function set(string $key, $value, int $ttl = 3600): void
    {
        $file = $this->getFilePath($key);
        file_put_contents($file, $value);
    }

    public function delete(string $key): void
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public function clear(): void
    {
        $files = glob($this->cacheDir . '/*');
        if ($files) {
            array_map('unlink', $files);
        }
    }

    private function getFilePath(string $key): string
    {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }
}
