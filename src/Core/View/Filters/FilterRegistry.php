<?php

namespace Beobles\Core\View\Filters;

use Beobles\Core\View\Exceptions\ViewException;

/**
 * Registro de filtros
 */
class FilterRegistry
{
    private array $filters = [];

    public function __construct()
    {
        $this->registerDefaultFilters();
    }

    /**
     * Registra um filtro
     * 
     * @param string $name Nome do filtro
     * @param callable $callback Callback do filtro
     * @return void
     */
    public function register(string $name, callable $callback): void
    {
        $this->filters[$name] = $callback;
    }

    /**
     * Aplica um filtro
     * 
     * @param mixed $value Valor
     * @param string $filter Nome do filtro
     * @param array $args Argumentos
     * @return mixed Valor filtrado
     */
    public function apply($value, string $filter, array $args = [])
    {
        if (!isset($this->filters[$filter])) {
            throw new ViewException("Filter not found: {$filter}");
        }

        $callback = $this->filters[$filter];
        return $callback($value, ...$args);
    }

    /**
     * Registra filtros padrão
     * 
     * @return void
     */
    private function registerDefaultFilters(): void
    {
        // String filters
        $this->register('uppercase', fn($v) => strtoupper($v));
        $this->register('lowercase', fn($v) => strtolower($v));
        $this->register('ucfirst', fn($v) => ucfirst($v));
        $this->register('reverse', fn($v) => strrev($v));
        $this->register('trim', fn($v) => trim($v));
        $this->register('ltrim', fn($v) => ltrim($v));
        $this->register('rtrim', fn($v) => rtrim($v));

        // Truncate
        $this->register('truncate', fn($v, $len = 50, $suffix = '...') => 
            strlen($v) > $len ? substr($v, 0, $len) . $suffix : $v
        );

        // Number filters
        $this->register('currency', fn($v, $currency = 'BRL') => 
            $currency === 'BRL' ? 'R\$ ' . number_format($v, 2, ',', '.') : '$' . number_format($v, 2)
        );
        $this->register('number_format', fn($v, $decimals = 0) => number_format($v, $decimals, ',', '.'));
        $this->register('abs', fn($v) => abs($v));

        // Date filter
        $this->register('date', fn($v, $format = 'd/m/Y') => 
            is_numeric($v) ? date($format, $v) : date($format, strtotime($v))
        );

        // Array filters
        $this->register('count', fn($v) => count($v));
        $this->register('first', fn($v) => $v[0] ?? null);
        $this->register('last', fn($v) => end($v));
        $this->register('reverse', fn($v) => array_reverse($v));
        $this->register('join', fn($v, $sep = ',') => implode($sep, $v));

        // JSON
        $this->register('json', fn($v) => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Raw output
        $this->register('raw', fn($v) => $v);

        // Escape
        $this->register('escape', fn($v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
        $this->register('htmlentities', fn($v) => htmlentities($v, ENT_QUOTES, 'UTF-8'));

        // Slug
        $this->register('slug', function($v) {
            $v = strtolower($v);
            $v = preg_replace('/[^a-z0-9]+/', '-', $v);
            return trim($v, '-');
        });
    }
}
