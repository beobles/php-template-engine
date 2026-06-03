<?php

namespace Core\View\Debug;

class ErrorFormatter
{
    public function format(string $message, ?array $location = null): string
    {
        if ($location === null) {
            return $message;
        }

        return sprintf('%s at %s:%d:%d', $message, $location['file'], $location['line'], $location['column']);
    }
}
