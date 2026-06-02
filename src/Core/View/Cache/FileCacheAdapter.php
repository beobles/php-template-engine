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

        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $payload = @unserialize($content, ['allowed_classes' => false]);
        if (!is_array($payload) || !array_key_exists('value', $payload) || !array_key_exists('expires_at', $payload)) {
            return $content;
        }

        $expiresAt = $payload['expires_at'];
        if (is_int($expiresAt) && $expiresAt > 0 && $expiresAt < time()) {
            $this->delete($key);
            return null;
        }

        return $payload['value'];
    }

    public function set(string $key, $value, int $ttl = 3600): void
    {
        $file = $this->getFilePath($key);
        $expiresAt = $ttl > 0 ? time() + $ttl : null;
        $payload = serialize([
            'value' => $value,
            'expires_at' => $expiresAt,
        ]);
        file_put_contents($file, $payload);
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
