<?php

namespace Core\View\Middleware;

use Core\View\Abstract\AbstractMiddleware;

class MiddlewarePipeline
{
    /** @var array<int, AbstractMiddleware> */
    private array $middlewares = [];

    public function add(AbstractMiddleware $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * @param array<string, mixed> $context
     * @param callable(array<string, mixed>): string $destination
     */
    public function process(array $context, callable $destination): string
    {
        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            fn(callable $next, AbstractMiddleware $middleware) => fn(array $ctx): string => $middleware->handle($ctx, $next),
            $destination
        );

        return $pipeline($context);
    }
}
