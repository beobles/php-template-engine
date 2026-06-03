<?php

namespace Core\View\Cache;

/**
 * Interface para adaptadores de cache
 */
interface CacheInterface
{
    /**
     * Obtém valor do cache
     * 
     * @param string $key Chave
     * @return mixed|null
     */
    public function get(string $key);

    /**
     * Define valor no cache
     * 
     * @param string $key Chave
     * @param mixed $value Valor
     * @param int $ttl TTL em segundos
     * @return void
     */
    public function set(string $key, $value, int $ttl = 3600): void;

    /**
     * Remove do cache
     * 
     * @param string $key Chave
     * @return void
     */
    public function delete(string $key): void;

    /**
     * Limpa todo o cache
     * 
     * @return void
     */
    public function clear(): void;
}
