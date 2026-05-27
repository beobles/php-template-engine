<?php

namespace Core\View;

/**
 * Renderizador de templates compilados
 */
class Renderer
{
    private string $compiledTemplatesDir;

    public function __construct(?string $compiledTemplatesDir = null)
    {
        $baseDir = $compiledTemplatesDir ?? (sys_get_temp_dir() . '/php-template-engine');
        $this->compiledTemplatesDir = rtrim($baseDir, '/');
    }

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
        $templateFile = $this->materializeCompiledTemplate($compiledCode);
        $renderer = static function (string $__templateFile, array $__data, ?Engine $__engine): string {
            extract($__data, EXTR_SKIP);

            ob_start();
            try {
                include $__templateFile;
                return ob_get_clean();
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
        };

        return $renderer($templateFile, $data, $engine);
    }

    private function materializeCompiledTemplate(string $compiledCode): string
    {
        if (!is_dir($this->compiledTemplatesDir)) {
            if (!mkdir($this->compiledTemplatesDir, 0755, true) && !is_dir($this->compiledTemplatesDir)) {
                throw new \RuntimeException("Unable to create compiled template directory: {$this->compiledTemplatesDir}");
            }
        }

        $hash = hash('sha256', $compiledCode);
        $targetFile = $this->compiledTemplatesDir . '/tpl_' . $hash . '.php';

        if (!is_file($targetFile)) {
            $tmpFile = $targetFile . '.tmp.' . bin2hex(random_bytes(6));
            $bytes = file_put_contents($tmpFile, $compiledCode, LOCK_EX);
            if ($bytes === false) {
                throw new \RuntimeException("Unable to write compiled template file: {$targetFile}");
            }

            if (!@rename($tmpFile, $targetFile)) {
                @unlink($tmpFile);
                if (!is_file($targetFile)) {
                    throw new \RuntimeException("Unable to finalize compiled template file: {$targetFile}");
                }
            }
        }

        return $targetFile;
    }
}
