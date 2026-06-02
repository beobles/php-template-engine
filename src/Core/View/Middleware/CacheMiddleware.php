<?php

namespace Beobles\Core\View\Middleware;

use Beobles\Core\View\Abstract\AbstractMiddleware;

class CacheMiddleware extends AbstractMiddleware
{
    public function handle(array $context, callable $next): string
    {
        return $next($context);
    }
}
