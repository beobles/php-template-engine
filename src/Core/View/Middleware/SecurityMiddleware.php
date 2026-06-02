<?php

namespace Beobles\Core\View\Middleware;

use Beobles\Core\View\Abstract\AbstractMiddleware;

class SecurityMiddleware extends AbstractMiddleware
{
    public function handle(array $context, callable $next): string
    {
        unset($context['__internal']);
        return $next($context);
    }
}
