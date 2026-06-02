<?php

namespace Beobles\Core\View\Escape;

class UriEscaper
{
    public function escape(string $value): string
    {
        return rawurlencode($value);
    }
}
