<?php

namespace Core\View;

/**
 * Configuração e contexto do template
 */
class Environment
{
    private array $config;
    private array $globals = [];
    private bool $debug;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->debug = $this->resolveDebugMode($config);
    }

    /**
     * Define uma variável global
     * 
     * @param string $name Nome da variável
     * @param mixed $value Valor
     * @return void
     */
    public function setGlobal(string $name, $value): void
    {
        $this->globals[$name] = $value;
    }

    /**
     * Obtém uma variável global
     * 
     * @param string $name Nome da variável
     * @param mixed $default Valor padrão
     * @return mixed
     */
    public function getGlobal(string $name, $default = null)
    {
        return $this->globals[$name] ?? $default;
    }

    /**
     * Obtém todas as variáveis globais
     * 
     * @return array
     */
    public function getGlobals(): array
    {
        return $this->globals;
    }

    /**
     * Obtém uma configuração
     * 
     * @param string $key Chave da configuração
     * @param mixed $default Valor padrão
     * @return mixed
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function isProduction(): bool
    {
        return !$this->debug;
    }

    private function resolveDebugMode(array $config): bool
    {
        if (array_key_exists('debug', $config)) {
            return (bool) $config['debug'];
        }

        $environment = strtolower((string) ($config['environment'] ?? ''));
        if (in_array($environment, ['prod', 'production'], true)) {
            return false;
        }

        return true;
    }
}
