<?php

namespace Core\View;

use Core\View\Exceptions\RuntimeException;
use Core\View\Exceptions\SyntaxException;

class Renderer
{
    public function __construct(private ?string $compiledTemplatesDir = null)
    {
    }

    public function render(string $compiledCode, array $data = [], ?Engine $engine = null): string
    {
        $compiledFile = $this->writeCompiledFile($compiledCode, $engine);
        $this->assertValidPhp($compiledFile);

        $render = static function (string $__compiledFile, array $__data, ?Engine $__engine): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            include $__compiledFile;
            return (string) ob_get_clean();
        };

        try {
            return $render($compiledFile, $data, $engine);
        } catch (\Throwable $e) {
            throw new RuntimeException('Runtime render error: ' . $e->getMessage(), 0, $e);
        }
    }

    private function writeCompiledFile(string $compiledCode, ?Engine $engine): string
    {
        $targetDir = $this->compiledTemplatesDir;
        if ($targetDir === null && $engine !== null) {
            $targetDir = $engine->getEnvironment()->getConfig('compiled_templates_dir', $engine->getEnvironment()->getConfig('cache_dir', sys_get_temp_dir()));
        }

        $targetDir ??= sys_get_temp_dir();

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Failed to create compiled templates directory: ' . $targetDir);
        }

        $file = rtrim($targetDir, '/\\') . '/tpl_' . md5($compiledCode) . '.php';
        if (file_put_contents($file, $compiledCode, LOCK_EX) === false) {
            throw new RuntimeException('Failed to write compiled template file: ' . $file);
        }

        return $file;
    }

    private function assertValidPhp(string $compiledFile): void
    {
        $phpBinary = $this->resolvePhpBinary();
        $output = [];
        $status = 0;
        exec(escapeshellarg($phpBinary) . ' -l ' . escapeshellarg($compiledFile) . ' 2>&1', $output, $status);

        if ($status !== 0) {
            throw new SyntaxException('Compiled template syntax error: ' . implode("\n", $output));
        }
    }

    private function resolvePhpBinary(): string
    {
        $candidates = [];

        if (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') {
            $candidates[] = PHP_BINARY;
        }

        if (defined('PHP_BINDIR') && is_string(PHP_BINDIR) && PHP_BINDIR !== '') {
            $candidates[] = rtrim(PHP_BINDIR, '/\\') . DIRECTORY_SEPARATOR . 'php';
            if (DIRECTORY_SEPARATOR === '\\') {
                $candidates[] = rtrim(PHP_BINDIR, '/\\') . DIRECTORY_SEPARATOR . 'php.exe';
            }
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return 'php';
    }
}
