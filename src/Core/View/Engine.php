<?php

namespace Core\View;

use Core\View\Cache\CacheKey;
use Core\View\Cache\CacheManager;
use Core\View\Cache\FileCacheAdapter;
use Core\View\Cache\FileWatcher;
use Core\View\Components\ComponentRegistry;
use Core\View\Debug\TemplateDebugger;
use Core\View\Directives\DirectiveRegistry;
use Core\View\Escape\Escaper;
use Core\View\Exceptions\ViewException;
use Core\View\Filters\FilterRegistry;
use Core\View\Layout\LayoutManager;
use Core\View\Middleware\CacheMiddleware;
use Core\View\Middleware\MiddlewarePipeline;
use Core\View\Middleware\ProfilingMiddleware;
use Core\View\Middleware\SecurityMiddleware;
use Core\View\Scope\ScopeStack;
use Core\View\Validation\TemplateValidator;

class Engine
{
    private string $templatesDir;
    private string $cacheDir;
    private bool $cacheEnabled;
    private Environment $environment;
    private Lexer $lexer;
    private Parser $parser;
    private Compiler $compiler;
    private Renderer $renderer;
    private CacheManager $cacheManager;
    private CacheKey $cacheKey;
    private FileWatcher $fileWatcher;
    private TemplateResolver $templateResolver;
    private LayoutManager $layoutManager;
    private ComponentRegistry $componentRegistry;
    private FilterRegistry $filterRegistry;
    private ScopeStack $scopeStack;
    private Escaper $escaper;
    private MiddlewarePipeline $middlewarePipeline;
    private TemplateValidator $templateValidator;
    private TemplateDebugger $debugger;

    public function __construct(array $config = [])
    {
        $this->templatesDir = $config['templates_dir'] ?? __DIR__ . '/../../../templates';
        $this->cacheDir = $config['cache_dir'] ?? __DIR__ . '/../../../cache';
        $this->cacheEnabled = $config['cache_enabled'] ?? true;

        if (!is_dir($this->templatesDir)) {
            throw new ViewException("Templates directory not found: {$this->templatesDir}");
        }

        if ($this->cacheEnabled && !is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        $this->environment = new Environment(array_merge($config, ['cache_dir' => $this->cacheDir]));
        $this->cacheManager = new CacheManager(new FileCacheAdapter($this->cacheDir));
        $this->cacheKey = new CacheKey();
        $this->fileWatcher = new FileWatcher();
        $this->templateResolver = new TemplateResolver($this->templatesDir);
        $this->layoutManager = new LayoutManager($this->templateResolver);
        $this->componentRegistry = new ComponentRegistry();
        $this->filterRegistry = new FilterRegistry();
        $this->scopeStack = new ScopeStack();
        $this->escaper = new Escaper();
        $this->middlewarePipeline = new MiddlewarePipeline();
        $this->templateValidator = new TemplateValidator();
        $this->debugger = new TemplateDebugger();

        $directiveRegistry = new DirectiveRegistry();
        $this->lexer = new Lexer();
        $this->parser = new Parser($directiveRegistry);
        $this->compiler = new Compiler();
        $this->renderer = new Renderer($this->environment->getConfig('compiled_templates_dir', $this->cacheDir));

        $this->middlewarePipeline->add(new SecurityMiddleware());
        $this->middlewarePipeline->add(new CacheMiddleware());
        $this->middlewarePipeline->add(new ProfilingMiddleware());
    }

    public function render(string $templatePath, array $data = []): string
    {
        $start = $this->debugger->begin();

        return $this->middlewarePipeline->process(
            ['template' => $templatePath, 'data' => $data],
            function (array $context) use ($start): string {
                $effectiveTemplatePath = isset($context['template']) ? (string) $context['template'] : '';
                $effectiveData = isset($context['data']) && is_array($context['data']) ? $context['data'] : [];

                try {
                    $absolutePath = $this->resolveTemplatePath($effectiveTemplatePath);

                    if (!is_file($absolutePath)) {
                        throw new ViewException("Template not found: {$effectiveTemplatePath}");
                    }

                    $compiledCode = $this->compileTemplate($absolutePath, $effectiveTemplatePath);

                    $runtimeData = array_merge($this->environment->getGlobals(), $effectiveData);
                    foreach ($runtimeData as $name => $value) {
                        $this->scopeStack->set((string) $name, $value);
                    }

                    return $this->renderer->render($compiledCode, $runtimeData, $this);
                } catch (\Throwable $e) {
                    throw new ViewException("Error rendering template '{$effectiveTemplatePath}': " . $e->getMessage(), 0, $e);
                }
            }
        );
    }

    public function registerComponent(string $name, string $path): void
    {
        $this->componentRegistry->register($name, $path);
    }

    public function registerFilter(string $name, callable $callback): void
    {
        $this->filterRegistry->register($name, $callback);
    }

    public function resolveTemplatePath(string $path): string
    {
        return $this->templateResolver->resolve($path);
    }

    public function renderComponent(string $name, array $props = []): string
    {
        $componentPath = $this->componentRegistry->resolve($name);
        return $this->render($componentPath, ['props' => $props]);
    }

    public function applyFilter(mixed $value, string $filter, array $args = []): mixed
    {
        return $this->filterRegistry->apply($value, $filter, $args);
    }

    public function escape(mixed $value, string $context = 'html'): string
    {
        return $this->escaper->escape($value, $context);
    }

    /** @param array<string, mixed> $scope */
    public function resolveValue(string $path, array $scope): mixed
    {
        if (str_contains($path, '.')) {
            $segments = explode('.', $path);
            $current = $scope[$segments[0]] ?? $this->scopeStack->get($segments[0]);

            foreach (array_slice($segments, 1) as $segment) {
                if (is_array($current) && array_key_exists($segment, $current)) {
                    $current = $current[$segment];
                    continue;
                }

                if (is_object($current) && isset($current->{$segment})) {
                    $current = $current->{$segment};
                    continue;
                }

                return null;
            }

            return $current;
        }

        return $scope[$path] ?? $this->scopeStack->get($path);
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }

    public function clearCache(): void
    {
        if ($this->cacheEnabled) {
            $this->cacheManager->clear();
        }
    }

    private function compileTemplate(string $absolutePath, string $templatePath): string
    {
        $source = file_get_contents($absolutePath);
        if ($source === false) {
            throw new ViewException("Cannot read template file: {$absolutePath}");
        }

        $merged = $this->layoutManager->merge($absolutePath, $source);
        $this->templateValidator->validate($merged);

        $cacheKey = $this->cacheKey->forTemplate($templatePath, [$absolutePath]);

        if ($this->cacheEnabled && !$this->fileWatcher->hasChanged([$absolutePath])) {
            $cached = $this->cacheManager->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $tokens = $this->lexer->tokenize($merged, $absolutePath);
        $ast = $this->parser->parse($tokens);
        $compiledCode = $this->compiler->compile($ast);

        if ($this->cacheEnabled) {
            $this->cacheManager->set($cacheKey, $compiledCode);
        }

        return $compiledCode;
    }
}
