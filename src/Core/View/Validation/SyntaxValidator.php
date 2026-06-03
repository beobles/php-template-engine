<?php

namespace Core\View\Validation;

use Core\View\Exceptions\SyntaxException;

class SyntaxValidator
{
    public function validate(string $template): void
    {
        $ifOpen = preg_match_all('/<If\b/i', $template);
        $ifClose = preg_match_all('/<\/If>/i', $template);
        if ($ifOpen !== $ifClose) {
            throw new SyntaxException('Unbalanced <If> tags in template');
        }

        $foreachOpen = preg_match_all('/<Foreach\b/i', $template);
        $foreachClose = preg_match_all('/<\/Foreach>/i', $template);
        if ($foreachOpen !== $foreachClose) {
            throw new SyntaxException('Unbalanced <Foreach> tags in template');
        }
    }
}
