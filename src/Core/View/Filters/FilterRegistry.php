<?php

namespace Core\View\Filters;

use Core\View\Exceptions\ViewException;

class FilterRegistry
{
    /** @var array<string, callable> */
    private array $filters = [];

    public function __construct()
    {
        $this->registerDefaultFilters();
    }

    public function register(string $name, callable $callback): void
    {
        $this->filters[strtolower($name)] = $callback;
    }

    public function apply(mixed $value, string $filter, array $args = []): mixed
    {
        $normalized = strtolower($filter);
        if (!isset($this->filters[$normalized])) {
            throw new ViewException("Filter not found: {$filter}");
        }

        return ($this->filters[$normalized])($value, ...$args);
    }

    private function registerDefaultFilters(): void
    {
        foreach ([
            StringFilters::definitions(),
            NumberFilters::definitions(),
            DateFilters::definitions(),
            ArrayFilters::definitions(),
        ] as $group) {
            foreach ($group as $name => $callable) {
                $this->register($name, $callable);
            }
        }

        $this->register('reverse', fn($v) => is_array($v) ? array_reverse($v) : strrev((string) $v));
        $this->register('raw', fn($v) => $v);
        $this->register('escape', fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
}
