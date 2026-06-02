<?php

namespace Beobles\Core\View;

use Beobles\Core\View\Exceptions\RuntimeException;
use Beobles\Core\View\Exceptions\SyntaxException;

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

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $file = rtrim($targetDir, '/\\') . '/tpl_' . md5($compiledCode) . '.php';
        file_put_contents($file, $compiledCode);

        return $file;
    }

    private function assertValidPhp(string $compiledFile): void
    {
        $phpBinary = PHP_BINARY ?: 'php';
        $output = [];
        $status = 0;
        @exec(escapeshellcmd($phpBinary) . ' -l ' . escapeshellarg($compiledFile) . ' 2>&1', $output, $status);

        if ($status !== 0) {
            throw new SyntaxException('Compiled template syntax error: ' . implode("\n", $output));
        }
    }
}
