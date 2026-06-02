<?php

namespace Core\View;

use Core\View\Cache\CacheManager;
use Core\View\Cache\FileCacheAdapter;
use Core\View\Components\ComponentRegistry;
use Core\View\Exceptions\ViewException;
use Core\View\Filters\FilterRegistry;
use Core\View\NodeVisitor\NodeVisitorInterface;

/**
 * Motor de Template Engine Principal
 * 
 * Orquestra a compilação, cache e renderização de templates
 */
class Engine
{
    private const CACHE_VERSION = '6';
    private string $templatesDir;
    private string $cacheDir;
    private bool $autoEscape;
    private bool $cacheEnabled;
    private Environment $environment;
    private Lexer $lexer;
    private Parser $parser;
    private Compiler $compiler;
    private Renderer $renderer;
    private CacheManager $cacheManager;
    private ComponentRegistry $componentRegistry;
    private FilterRegistry $filterRegistry;
    private string $compiledTemplatesDir;

    /**
     * Construtor do Engine
     * 
     * @param array $config [
     *   'templates_dir' => string,
     *   'cache_dir' => string,
     *   'compiled_templates_dir' => string,
     *   'auto_escape' => bool,
     *   'cache_enabled' => bool,
     * ]
     */
    public function __construct(array $config = [])
    {
        $this->templatesDir = $config['templates_dir'] ?? __DIR__ . '/../../../templates';
        $this->cacheDir = $config['cache_dir'] ?? __DIR__ . '/../../../cache';
        $this->autoEscape = $config['auto_escape'] ?? true;
        $this->cacheEnabled = $config['cache_enabled'] ?? true;
        $this->compiledTemplatesDir = ($config['compiled_templates_dir'] ?? ($this->cacheDir . '/compiled'));

        // Validar diretórios
        if (!is_dir($this->templatesDir)) {
            throw new ViewException("Templates directory not found: {$this->templatesDir}");
        }

        // Criar cache dir se necessário
        if ($this->cacheEnabled && !is_dir($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0755, true) && !is_dir($this->cacheDir)) {
                throw new ViewException("Unable to create cache directory: {$this->cacheDir}");
            }
        }

        // Inicializar componentes
        $this->environment = new Environment($config);
        $this->lexer = new Lexer();
        $this->parser = new Parser();
        $this->compiler = new Compiler();
        $this->renderer = new Renderer($this->compiledTemplatesDir);
        $this->cacheManager = new CacheManager(new FileCacheAdapter($this->cacheDir));
        $this->componentRegistry = new ComponentRegistry();
        $this->filterRegistry = new FilterRegistry();
    }

    /**
     * Renderiza um template com dados
     * 
     * @param string $templatePath Caminho relativo do template
     * @param array $data Dados para o template
     * @return string HTML renderizado
     */
    public function render(string $templatePath, array $data = []): string
    {
        try {
            $absolutePath = $this->resolveTemplatePath($templatePath);
            if (!file_exists($absolutePath)) {
                throw new ViewException("Template not found: {$templatePath}");
            }

            $dependencies = $this->collectTemplateDependencies($absolutePath, [$absolutePath]);
            $cacheKey = null;

            if ($this->cacheEnabled) {
                $cacheKey = $this->generateCacheKey($dependencies);
                $cachedContent = $this->cacheManager->get($cacheKey);

                if ($cachedContent !== null) {
                    return $this->renderer->render($cachedContent, $this->prepareRenderData($data), $this);
                }
            }

            $compiledCode = $this->compileTemplate($absolutePath, $templatePath);
            $output = $this->renderer->render($compiledCode, $this->prepareRenderData($data), $this);

            if ($this->cacheEnabled && $cacheKey !== null) {
                $this->cacheManager->set($cacheKey, $compiledCode);
            }

            return $output;
        } catch (\Throwable $e) {
            throw $this->normalizeRenderException($templatePath, $e);
        }
    }

    private function compileTemplate(string $absolutePath, string $templatePath): string
    {
        $content = $this->readTemplateFile($absolutePath, $templatePath);
        $content = $this->resolveTemplateInheritance($content, [$absolutePath]);
        $tokens = $this->lexer->tokenize($content);
        $ast = $this->parser->parse($tokens);

        return $this->compiler->compile($ast);
    }

    private function readTemplateFile(string $absolutePath, string $templatePath): string
    {
        $content = file_get_contents($absolutePath);
        if ($content === false) {
            throw new ViewException("Unable to read template: {$templatePath}");
        }

        return $content;
    }

    private function prepareRenderData(array $data): array
    {
        return array_merge($this->environment->getGlobals(), $data);
    }

    private function normalizeRenderException(string $templatePath, \Throwable $exception): ViewException
    {
        if ($this->environment->isDebug()) {
            if ($exception instanceof ViewException) {
                return $exception;
            }

            return new ViewException(
                "Error rendering template '{$templatePath}': " . $exception->getMessage(),
                0,
                $exception,
                ['template' => $templatePath],
                'Template rendering failed.'
            );
        }

        $safeMessage = $exception instanceof ViewException
            ? $exception->getSafeMessage()
            : 'Template rendering failed.';

        return new ViewException(
            $safeMessage,
            0,
            $exception,
            ['template' => $templatePath],
            $safeMessage
        );
    }

    /**
     * Registra um visitante de nós para análise ou transformação da AST.
     */
    public function addVisitor(NodeVisitorInterface $visitor): void
    {
        $this->compiler->addVisitor($visitor);
    }

    /**
     * Registra um componente customizado
     * 
     * @param string $name Nome do componente
     * @param string $path Caminho do arquivo
     * @return void
     */
    public function registerComponent(string $name, string $path): void
    {
        $this->componentRegistry->register($name, $path);
    }

    /**
     * Registra um filtro customizado
     * 
     * @param string $name Nome do filtro
     * @param callable $callback Callback do filtro
     * @return void
     */
    public function registerFilter(string $name, callable $callback): void
    {
        $this->filterRegistry->register($name, $callback);
    }

    /**
     * Resolve o caminho completo do template
     * 
     * @param string $path Caminho relativo
     * @return string Caminho absoluto
     */
    public function resolveTemplatePath(string $path): string
    {
        $path = ltrim($path, '/');
        if (str_contains($path, '..')) {
            throw new ViewException("Invalid template path: {$path}");
        }

        // Resolver alias @components
        if (strpos($path, '@') === 0) {
            $path = str_replace('@components/', 'components/', $path);
        }

        // Adicionar extensão se necessário
        if (!str_ends_with($path, '.html')) {
            $path .= '.html';
        }

        $resolved = realpath($this->templatesDir . '/' . $path);
        if ($resolved === false || strpos($resolved, realpath($this->templatesDir)) !== 0) {
            return $this->templatesDir . '/' . $path;
        }

        return $resolved;
    }

    /**
     * Gera chave de cache
     * 
     * @param string $path Caminho do template
     * @return string Chave de cache
     */
    private function generateCacheKey(array $dependencies): string
    {
        $signature = [];

        foreach ($dependencies as $path) {
            $modifiedAt = file_exists($path) ? (string) filemtime($path) : '0';
            $signature[] = $path . '@' . $modifiedAt;
        }

        return 'template_' . md5(self::CACHE_VERSION . '|' . implode('|', $signature));
    }

    /**
     * Coleta cadeia completa de templates envolvidos em extends.
     *
     * @param string[] $stack
     * @return string[]
     */
    private function collectTemplateDependencies(string $absolutePath, array $stack): array
    {
        $content = file_get_contents($absolutePath);
        if ($content === false) {
            throw new ViewException("Unable to read template dependency: {$absolutePath}");
        }

        $parentRelativePath = $this->extractParentTemplatePath($content);
        if ($parentRelativePath === null) {
            return [$absolutePath];
        }

        $parentAbsolutePath = $this->resolveTemplatePath($parentRelativePath);
        if (!file_exists($parentAbsolutePath)) {
            throw new ViewException("Parent template not found: {$parentRelativePath}");
        }

        if (in_array($parentAbsolutePath, $stack, true)) {
            throw new ViewException("Circular extends detected: {$parentRelativePath}");
        }

        return array_merge(
            [$absolutePath],
            $this->collectTemplateDependencies($parentAbsolutePath, [...$stack, $parentAbsolutePath])
        );
    }

    /**
     * Resolve herança de template via `extends "path";` e sobrescrita de <Block>.
     *
     * @param string[] $stack
     */
    private function resolveTemplateInheritance(string $content, array $stack): string
    {
        $parentRelativePath = $this->extractParentTemplatePath($content);
        if ($parentRelativePath === null) {
            return $content;
        }
        $parentAbsolutePath = $this->resolveTemplatePath($parentRelativePath);

        if (!file_exists($parentAbsolutePath)) {
            throw new ViewException("Parent template not found: {$parentRelativePath}");
        }

        if (in_array($parentAbsolutePath, $stack, true)) {
            throw new ViewException("Circular extends detected: {$parentRelativePath}");
        }

        $parentContent = file_get_contents($parentAbsolutePath);
        if ($parentContent === false) {
            throw new ViewException("Unable to read parent template: {$parentRelativePath}");
        }

        $childContent = preg_replace('/^\s*extends\s+["\']([^"\']+)["\']\s*;[ \t]*\R?/i', '', $content, 1) ?? $content;
        $parentResolved = $this->resolveTemplateInheritance($parentContent, [...$stack, $parentAbsolutePath]);

        return $this->mergeBlocks($parentResolved, $childContent);
    }

    private function extractParentTemplatePath(string $content): ?string
    {
        if (preg_match('/^\s*extends\s+["\']([^"\']+)["\']\s*;/i', $content, $match) !== 1) {
            return null;
        }

        return trim($match[1]);
    }

    /**
     * @return array<string,string>
     */
    private function extractBlocks(string $content): array
    {
        $blocks = [];
        preg_match_all('/<Block\s+name\s*=\s*["\']([^"\']+)["\']\s*>(.*?)<\/Block>/is', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $blocks[$match[1]] = $match[2];
        }

        return $blocks;
    }

    private function mergeBlocks(string $parentContent, string $childContent): string
    {
        $childBlocks = $this->extractBlocks($childContent);

        return (string) preg_replace_callback(
            '/<Block\s+name\s*=\s*["\']([^"\']+)["\']\s*>(.*?)<\/Block>/is',
            static function (array $match) use ($childBlocks): string {
                $name = $match[1];
                return $childBlocks[$name] ?? $match[2];
            },
            $parentContent
        );
    }

    /**
     * Renderiza um componente
     * 
     * @param string $name Nome do componente
     * @param array $props Props do componente
     * @return string HTML renderizado
     */
    public function renderComponent(string $name, array $props = []): string
    {
        $componentPath = $this->componentRegistry->resolve($name);
        return $this->render($componentPath, ['props' => $props]);
    }

    /**
     * Aplica um filtro a um valor
     * 
     * @param mixed $value Valor
     * @param string $filter Nome do filtro
     * @param array $args Argumentos do filtro
     * @return mixed Valor filtrado
     */
    public function applyFilter($value, string $filter, array $args = [])
    {
        return $this->filterRegistry->apply($value, $filter, $args);
    }

    /**
     * Obtém o environment
     * 
     * @return Environment
     */
    public function getEnvironment(): Environment
    {
        return $this->environment;
    }

    /**
     * Limpa o cache
     * 
     * @return void
     */
    public function clearCache(): void
    {
        if ($this->cacheEnabled) {
            $this->cacheManager->clear();
        }
    }
}
