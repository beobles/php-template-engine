<?php

namespace Beobles\Core\View;

/**
 * Renderizador de templates compilados
 */
class Renderer
{
    /**
     * Renderiza código PHP compilado
     * 
     * @param string $compiledCode Código PHP compilado
     * @param array $data Dados para o template
     * @param Engine $engine Instância do engine
     * @return string Output renderizado
     */
    public function render(string $compiledCode, array $data = [], Engine $engine = null): string
    {
        // Criar escopo de variáveis
        extract($data, EXTR_SKIP);
        $__engine = $engine;

        // Capturar output
        ob_start();
        try {
            eval('?>' . $compiledCode);
            return ob_get_clean();
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
