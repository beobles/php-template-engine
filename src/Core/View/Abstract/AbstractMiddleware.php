<?php

namespace Core\View\Abstract;

abstract class AbstractMiddleware
{
    /**
     * @param callable(array<string, mixed>): string $next
     * @param array<string, mixed> $context
     */
    abstract public function handle(array $context, callable $next): string;
}
