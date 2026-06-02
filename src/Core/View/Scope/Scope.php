<?php

namespace Beobles\Core\View\Scope;

class Scope
{
    /** @var array<string, mixed> */
    private array $variables = [];

    public function set(string $name, mixed $value): void
    {
        $this->variables[$name] = $value;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->variables);
    }

    public function get(string $name, mixed $default = null): mixed
    {
        return $this->variables[$name] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->variables;
    }
}
