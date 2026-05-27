<?php

namespace Core\View\Components;

use Core\View\Exceptions\ViewException;

/**
 * Registro de componentes
 */
class ComponentRegistry
{
    private array $components = [];

    /**
     * Registra um componente
     * 
     * @param string $name Nome do componente
     * @param string $path Caminho do arquivo
     * @return void
     */
    public function register(string $name, string $path): void
    {
        $this->components[$name] = $path;
    }

    /**
     * Resolve o caminho de um componente
     * 
     * @param string $name Nome do componente
     * @return string Caminho do arquivo
     */
    public function resolve(string $name): string
    {
        if (!isset($this->components[$name])) {
            throw new ViewException("Component not found: {$name}");
        }

        return $this->components[$name];
    }
}
