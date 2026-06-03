<?php

namespace Core\View\Middleware;

use Core\View\Abstract\AbstractMiddleware;

class ProfilingMiddleware extends AbstractMiddleware
{
    public function handle(array $context, callable $next): string
    {
        $start = microtime(true);
        $result = $next($context);
        $context['__profile_ms'] = (microtime(true) - $start) * 1000;

        return $result;
    }
}
