<?php

namespace Core\View;

use Core\View\Exceptions\SyntaxException;

/**
 * Renderizador de templates compilados
 */
class Renderer
{
    private string $compiledTemplatesDir;
    /** @var array<string,bool> */
    private array $validatedTemplates = [];

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

        $this->validateCompiledTemplateSyntax($targetFile);

        return $targetFile;
    }

    private function validateCompiledTemplateSyntax(string $templateFile): void
    {
        if (isset($this->validatedTemplates[$templateFile])) {
            return;
        }

        $command = escapeshellarg(PHP_BINARY) . ' -n -l ' . escapeshellarg($templateFile);
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            throw new SyntaxException("Unable to validate template syntax for '{$templateFile}'.");
        }

        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $output = trim($stderr !== '' ? $stderr : $stdout);

        if ($exitCode === 0) {
            $this->validatedTemplates[$templateFile] = true;
            return;
        }

        [$line, $column, $snippet] = $this->extractErrorLocation($templateFile, $output);

        throw new SyntaxException(
            "Compiled template syntax error in '{$templateFile}' at line {$line}, column {$column}." .
            ($snippet !== '' ? " Snippet: {$snippet}" : '') .
            ($output !== '' ? " PHP lint: {$output}" : '')
        );
    }

    /**
     * @return array{0:int,1:int,2:string}
     */
    private function extractErrorLocation(string $templateFile, string $lintOutput): array
    {
        $line = 1;
        if (preg_match('/on line (\d+)/i', $lintOutput, $lineMatch) === 1) {
            $line = (int) $lineMatch[1];
        }

        $lineContent = '';
        $lines = @file($templateFile);
        if (is_array($lines) && isset($lines[$line - 1])) {
            $lineContent = rtrim($lines[$line - 1], "\r\n");
        }

        $tokenDescriptor = '';
        if (preg_match('/unexpected\s+(.+?)\s+in\s+/i', $lintOutput, $tokenMatch) === 1) {
            $tokenDescriptor = trim($tokenMatch[1]);
        }

        $needle = $this->extractNeedleFromTokenDescriptor($tokenDescriptor);
        $column = 1;
        if ($needle !== '' && $lineContent !== '') {
            $position = strpos($lineContent, $needle);
            if ($position !== false) {
                $column = $position + 1;
            }
        }

        return [$line, $column, $lineContent];
    }

    private function extractNeedleFromTokenDescriptor(string $descriptor): string
    {
        if ($descriptor === '') {
            return '';
        }

        if (preg_match('/double-quoted string "([^"]*)"/', $descriptor, $match) === 1) {
            return $match[1];
        }

        if (preg_match("/single-quoted string '([^']*)'/", $descriptor, $match) === 1) {
            return $match[1];
        }

        if (preg_match('/token\s+([A-Z_]+)/', $descriptor, $match) === 1) {
            return $match[1];
        }

        return trim($descriptor, "\"' ");
    }
}
