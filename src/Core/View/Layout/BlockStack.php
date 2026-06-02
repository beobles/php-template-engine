<?php

namespace Core\View\Layout;

class BlockStack
{
    /** @var array<string, string> */
    private array $blocks = [];

    public function set(string $name, string $content): void
    {
        $this->blocks[$name] = $content;
    }

    public function get(string $name, string $default = ''): string
    {
        return $this->blocks[$name] ?? $default;
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return $this->blocks;
    }
}
