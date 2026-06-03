<?php

namespace Core\View;

use Core\View\Cache\CompiledTemplateCache;
use Core\View\Exceptions\RuntimeException;

class Renderer
{
    private CompiledTemplateCache $compiledTemplateCache;

    public function __construct(private ?string $compiledTemplatesDir = null)
    {
        $targetDir = $this->compiledTemplatesDir ?? sys_get_temp_dir();
        $this->compiledTemplateCache = new CompiledTemplateCache($targetDir);
    }

    public function render(
        string $compiledCode,
        array $data = [],
        ?Engine $engine = null,
        string $sourceFile = ''
    ): string {
        $templateId = $sourceFile !== '' ? $sourceFile : hash('sha256', $compiledCode);
        $dependencies = $sourceFile !== '' ? [$sourceFile] : [];
        $className = $this->compiledTemplateCache->load($templateId, $compiledCode, $dependencies);

        if (!class_exists($className)) {
            throw new RuntimeException("Compiled template class not found: {$className}");
        }

        $template = new $className();
        if (!$template instanceof CompiledTemplate) {
            throw new RuntimeException('Compiled template must extend ' . CompiledTemplate::class);
        }

        try {
            return $template->render($data, $engine);
        } catch (\Throwable $e) {
            throw new RuntimeException('Runtime render error: ' . $e->getMessage(), 0, $e);
        }
    }
}

