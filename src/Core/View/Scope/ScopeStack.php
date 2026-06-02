<?php

namespace Beobles\Core\View\Scope;

class ScopeStack
{
    /** @var array<int, Scope> */
    private array $stack = [];

    public function __construct()
    {
        $this->push(new Scope());
    }

    public function push(?Scope $scope = null): Scope
    {
        $scope ??= new Scope();
        $this->stack[] = $scope;

        return $scope;
    }

    public function pop(): ?Scope
    {
        if (count($this->stack) <= 1) {
            return $this->stack[0] ?? null;
        }

        return array_pop($this->stack);
    }

    public function set(string $name, mixed $value): void
    {
        $current = $this->current();
        if ($current) {
            $current->set($name, $value);
        }
    }

    public function get(string $name, mixed $default = null): mixed
    {
        for ($i = count($this->stack) - 1; $i >= 0; $i--) {
            if ($this->stack[$i]->has($name)) {
                return $this->stack[$i]->get($name);
            }
        }

        return $default;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $merged = [];
        foreach ($this->stack as $scope) {
            $merged = array_merge($merged, $scope->all());
        }

        return $merged;
    }

    private function current(): ?Scope
    {
        if ($this->stack === []) {
            return null;
        }

        return $this->stack[count($this->stack) - 1];
    }
}
