<?php

namespace Core\View\Abstract;

abstract class AbstractFilter
{
    abstract public function getName(): string;

    public function __invoke(mixed $value, mixed ...$args): mixed
    {
        return $this->apply($value, ...$args);
    }

    abstract public function apply(mixed $value, mixed ...$args): mixed;
}
