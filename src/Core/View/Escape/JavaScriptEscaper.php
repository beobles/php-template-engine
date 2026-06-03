<?php

namespace Core\View\Escape;

class JavaScriptEscaper
{
    public function escape(string $value): string
    {
        return str_replace(
            ["\\", "\n", "\r", "\t", "\f", "\b", "\"", "'", "<", ">", "&"],
            ["\\\\", "\\n", "\\r", "\\t", "\\f", "\\b", "\\\"", "\\'", "\\x3C", "\\x3E", "\\x26"],
            $value
        );
    }
}
