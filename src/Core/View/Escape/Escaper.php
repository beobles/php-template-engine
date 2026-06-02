<?php

namespace Beobles\Core\View\Escape;

class Escaper
{
    public function __construct(
        private ?HtmlEscaper $htmlEscaper = null,
        private ?JavaScriptEscaper $jsEscaper = null,
        private ?UriEscaper $uriEscaper = null
    ) {
        $this->htmlEscaper ??= new HtmlEscaper();
        $this->jsEscaper ??= new JavaScriptEscaper();
        $this->uriEscaper ??= new UriEscaper();
    }

    public function escape(mixed $value, string $context = 'html'): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        $value = (string) $value;

        return match (strtolower($context)) {
            'js', 'javascript' => $this->jsEscaper->escape($value),
            'uri', 'url' => $this->uriEscaper->escape($value),
            'css' => addcslashes($value, "\0..\37\\\"'<>"),
            default => $this->htmlEscaper->escape($value),
        };
    }
}
