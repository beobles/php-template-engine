<?php

namespace Beobles\Core\View\Cache;

/**
 * Gerenciador de cache
 */
class CacheManager
{
    public function __construct(
        private CacheInterface $adapter
    ) {}

    public function get(string $key)
    {
        return $this->adapter->get($key);
    }

    public function set(string $key, $value, int $ttl = 3600): void
    {
        $this->adapter->set($key, $value, $ttl);
    }

    public function delete(string $key): void
    {
        $this->adapter->delete($key);
    }

    public function clear(): void
    {
        $this->adapter->clear();
    }
}
