<?php

namespace Beobles\Core\View\Cache;

/**
 * Adaptador de cache em arquivo com TTL, escrita atômica e permissões seguras.
 */
class FileCacheAdapter implements CacheInterface
{
    public function __construct(
        private string $cacheDir
    ) {
        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0755, true) && !is_dir($this->cacheDir)) {
            throw new \RuntimeException("Unable to create cache directory: {$this->cacheDir}");
        }
    }

    public function get(string $key)
    {
        $file = $this->getFilePath($key);

        if (!is_file($file)) {
            return null;
        }

        $payload = file_get_contents($file);
        if ($payload === false) {
            return null;
        }

        $entry = unserialize($payload, ['allowed_classes' => false]);
        if (!is_array($entry) || !array_key_exists('value', $entry) || !isset($entry['expires_at'])) {
            $this->delete($key);
            return null;
        }

        if ($entry['expires_at'] !== 0 && $entry['expires_at'] < time()) {
            $this->delete($key);
            return null;
        }

        return $entry['value'];
    }

    public function set(string $key, $value, int $ttl = 3600): void
    {
        $file = $this->getFilePath($key);
        $tmp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $entry = [
            'expires_at' => $ttl > 0 ? time() + $ttl : 0,
            'value' => $value,
        ];

        if (file_put_contents($tmp, serialize($entry), LOCK_EX) === false) {
            throw new \RuntimeException("Unable to write cache file: {$file}");
        }

        chmod($tmp, 0644);
        rename($tmp, $file);
    }

    public function delete(string $key): void
    {
        $file = $this->getFilePath($key);
        if (is_file($file)) {
            unlink($file);
        }
    }

    public function clear(): void
    {
        foreach (glob($this->cacheDir . '/*.cache') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function getFilePath(string $key): string
    {
        return rtrim($this->cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.cache';
    }
}
