<?php

namespace Beobles\Core\View;

abstract class CompiledTemplate
{
    abstract public function render(array $data = [], ?Engine $engine = null): string;
}

