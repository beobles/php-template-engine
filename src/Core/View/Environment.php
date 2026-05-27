<?php

namespace Beobles\Core\View;

/**
 * Configuração e contexto do template
 */
class Environment
{
    private array $config;
    private array $globals = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
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
}
