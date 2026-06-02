<?php

namespace Beobles\Core\View\Scope;

class Variable
{
    public function __construct(
        public string $name,
        public mixed $value
    ) {}
}
