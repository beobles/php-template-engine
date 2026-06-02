<?php

namespace Beobles\Core\View\Directives;

use Beobles\Core\View\Abstract\AbstractDirective;

class DirectiveRegistry
{
    /** @var array<string, AbstractDirective> */
    private array $directives = [];

    public function register(AbstractDirective $directive): void
    {
        $this->directives[strtolower($directive->getName())] = $directive;
    }

    public function has(string $name): bool
    {
        return isset($this->directives[strtolower($name)]);
    }

    public function get(string $name): ?AbstractDirective
    {
        return $this->directives[strtolower($name)] ?? null;
    }
}
