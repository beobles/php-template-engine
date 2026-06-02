<?php

namespace Beobles\Core\View\Validation;

class TemplateValidator
{
    public function __construct(private ?SyntaxValidator $syntaxValidator = null)
    {
        $this->syntaxValidator ??= new SyntaxValidator();
    }

    public function validate(string $template): void
    {
        $this->syntaxValidator->validate($template);
    }
}
