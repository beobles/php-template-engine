<?php

namespace Beobles\Core\View;

use Beobles\Core\View\Cache\CacheManager;
use Beobles\Core\View\Cache\FileCacheAdapter;
use Beobles\Core\View\Components\ComponentRegistry;
use Beobles\Core\View\Exceptions\ViewException;
use Beobles\Core\View\Filters\FilterRegistry;

/**
 * Motor de Template Engine Principal
 * 
 * Orquestra a compilação, cache e renderização de templates
 */
class Engine
{
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
    private int $cacheTtl;

    /**
     * Construtor do Engine
     * 
     * @param array $config [
     *   'templates_dir' => string,
     *   'cache_dir' => string,
     *   'auto_escape' => bool,
     *   'cache_enabled' => bool,
     * ]
     */
    public function __construct(array $config = [])
    {
        $this->templatesDir = $this->normalizeDirectory($config['templates_dir'] ?? __DIR__ . '/../../../templates');
        $this->cacheDir = $this->normalizeDirectory($config['cache_dir'] ?? __DIR__ . '/../../../cache', false);
        $this->autoEscape = $config['auto_escape'] ?? true;
        $this->cacheEnabled = $config['cache_enabled'] ?? true;
        $this->cacheTtl = (int) ($config['cache_ttl'] ?? 0);

        // Validar diretórios
        if (!is_dir($this->templatesDir)) {
            throw new ViewException("Templates directory not found: {$this->templatesDir}");
        }

        // Criar cache dir se necessário
        if ($this->cacheEnabled && !is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        // Inicializar componentes
        $this->environment = new Environment($config);
        $this->lexer = new Lexer();
        $this->parser = new Parser();
        $this->compiler = new Compiler();
        $this->renderer = new Renderer();
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
            // Resolver caminho absoluto do template
            $absolutePath = $this->resolveTemplatePath($templatePath);

            if (!file_exists($absolutePath)) {
                throw new ViewException("Template not found: {$templatePath}");
            }

            // Verificar cache
            if ($this->cacheEnabled) {
                $cacheKey = $this->generateCacheKey($templatePath);
                $cachedContent = $this->cacheManager->get($cacheKey);

                if ($cachedContent !== null) {
                    return $this->renderer->render($cachedContent, $data, $this);
                }
            }

            // Ler template
            $content = file_get_contents($absolutePath);

            // Tokenizar
            $tokens = $this->lexer->tokenize($content);

            // Fazer parse
            $ast = $this->parser->parse($tokens);

            // Compilar
            $compiledCode = $this->compiler->compile($ast);

            // Cachear se habilitado
            if ($this->cacheEnabled) {
                $this->cacheManager->set($cacheKey, $compiledCode, $this->cacheTtl);
            }

            // Renderizar
            return $this->renderer->render($compiledCode, $data, $this);
        } catch (\Exception $e) {
            throw new ViewException("Error rendering template '{$templatePath}': " . $e->getMessage(), 0, $e);
        }
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
        // Resolver alias @components
        if (strpos($path, '@') === 0) {
            $path = str_replace('@components/', 'components/', $path);
        }

        // Adicionar extensão se necessário
        if (!str_ends_with($path, '.html')) {
            $path .= '.html';
        }

        $candidate = $this->templatesDir . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
        $resolved = realpath($candidate);
        $base = realpath($this->templatesDir);

        if ($resolved === false || $base === false || !str_starts_with($resolved, $base . DIRECTORY_SEPARATOR)) {
            throw new ViewException("Template path is outside templates directory: {$path}");
        }

        return $resolved;
    }

    /**
     * Gera chave de cache
     * 
     * @param string $path Caminho do template
     * @return string Chave de cache
     */
    private function generateCacheKey(string $path): string
    {
        return 'template_' . hash('sha256', $path . '|' . filemtime($this->resolveTemplatePath($path)) . '|' . filesize($this->resolveTemplatePath($path)));
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


    public function evaluateExpression(string $expression, array $data)
    {
        $parts = array_map('trim', explode('|', $expression));
        $value = $this->evaluateValue(array_shift($parts), $data);

        foreach ($parts as $filterExpression) {
            if ($filterExpression === '') {
                continue;
            }
            $segments = array_map('trim', explode(':', $filterExpression, 2));
            $args = [];
            if (isset($segments[1])) {
                $args = array_map(fn($arg) => $this->evaluateValue(trim($arg), $data), explode(',', $segments[1]));
            }
            $value = $this->applyFilter($value, $segments[0], $args);
        }

        return $value;
    }

    public function isTruthy(string $expression, array $data): bool
    {
        $expression = trim($expression);
        if (str_starts_with($expression, '!')) {
            return !$this->isTruthy(substr($expression, 1), $data);
        }
        return (bool) $this->evaluateExpression($expression, $data);
    }

    private function evaluateValue(string $expression, array $data)
    {
        $expression = trim($expression);
        if (preg_match('/^(.+?)\s*\?\?\s*(.+)$/', $expression, $matches)) {
            $value = $this->evaluateValue($matches[1], $data);
            return $value ?? $this->evaluateValue($matches[2], $data);
        }
        if ((str_starts_with($expression, '"') && str_ends_with($expression, '"')) || (str_starts_with($expression, "'") && str_ends_with($expression, "'"))) {
            return stripcslashes(substr($expression, 1, -1));
        }
        if (is_numeric($expression)) {
            return $expression + 0;
        }
        return match (strtolower($expression)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $this->resolveDataPath($expression, $data),
        };
    }

    private function resolveDataPath(string $path, array $data)
    {
        $value = $data;
        foreach (explode('.', $path) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                return null;
            }
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
                continue;
            }
            if (is_object($value) && isset($value->{$segment})) {
                $value = $value->{$segment};
                continue;
            }
            return null;
        }
        return $value;
    }

    private function normalizeDirectory(string $directory, bool $mustExist = true): string
    {
        $resolved = realpath($directory);
        if ($resolved === false) {
            if ($mustExist) {
                throw new ViewException("Directory not found: {$directory}");
            }
            return rtrim($directory, DIRECTORY_SEPARATOR);
        }
        return $resolved;
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
