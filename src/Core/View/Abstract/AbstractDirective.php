<?php

namespace Core\View\Abstract;

abstract class AbstractDirective
{
    abstract public function getName(): string;

    /** @return array<string, mixed> */
    abstract public function parseAttributes(string $attributes): array;
}
