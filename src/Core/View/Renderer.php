<?php

namespace Beobles\Core\View;

/**
 * Renderizador de templates compilados.
 */
class Renderer
{
    public function render(string $compiledCode, array $data = [], Engine $engine = null): string
    {
        $__data = $data;
        $__engine = $engine;

        ob_start();
        try {
            (static function () use ($compiledCode, $__data, $__engine): void {
                extract($__data, EXTR_SKIP);
                eval('?>' . $compiledCode);
            })();
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
