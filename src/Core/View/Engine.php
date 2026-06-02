<?php

namespace Core\View;

use Core\View\Cache\CacheManager;
use Core\View\Cache\FileCacheAdapter;
use Core\View\Components\ComponentRegistry;
use Core\View\Exceptions\ViewException;
use Core\View\Exceptions\ParserException;
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
        $resolvedTemplate = $this->resolveTemplateInheritanceWithSource($absolutePath, [$absolutePath]);
        $tokens = $this->lexer->tokenize($resolvedTemplate['content'], $resolvedTemplate['char_map']);

        try {
            $ast = $this->parser->parse($tokens);
        } catch (ParserException $e) {
            throw $this->augmentParserExceptionWithTemplate($e, $templatePath);
        }

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
            throw new ViewException(
                "Parent template not found: '{$parentRelativePath}' referenced from '{$absolutePath}'"
            );
        }

        if (in_array($parentAbsolutePath, $stack, true)) {
            throw new ViewException(
                "Circular extends detected. '{$absolutePath}' references '{$parentRelativePath}' recursively"
            );
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
     * @return array{content: string, char_map: array<int, array{file: string, line: int, column: int}>}
     */
    private function resolveTemplateInheritanceWithSource(string $absolutePath, array $stack): array
    {
        $content = $this->readTemplateFile($absolutePath, $absolutePath);
        $parentRelativePath = $this->extractParentTemplatePath($content);

        if ($parentRelativePath === null) {
            return [
                'content' => $content,
                'char_map' => $this->buildCharMap($content, $absolutePath),
            ];
        }

        $parentAbsolutePath = $this->resolveTemplatePath($parentRelativePath);
        if (!file_exists($parentAbsolutePath)) {
            throw new ViewException(
                "Parent template not found: '{$parentRelativePath}' referenced from '{$absolutePath}'"
            );
        }

        if (in_array($parentAbsolutePath, $stack, true)) {
            throw new ViewException(
                "Circular extends detected. '{$absolutePath}' references '{$parentRelativePath}' recursively"
            );
        }

        $parentResolved = $this->resolveTemplateInheritanceWithSource($parentAbsolutePath, [...$stack, $parentAbsolutePath]);
        $childWithoutExtends = $this->removeExtendsDirective($content);
        $childResolved = [
            'content' => $childWithoutExtends['content'],
            'char_map' => $this->buildCharMap(
                $childWithoutExtends['content'],
                $absolutePath,
                $childWithoutExtends['line'],
                $childWithoutExtends['column']
            ),
        ];

        return $this->mergeBlocksWithSource($parentResolved, $childResolved);
    }

    private function extractParentTemplatePath(string $content): ?string
    {
        if (preg_match('/^\s*extends\s+["\']([^"\']+)["\']\s*;/i', $content, $match) !== 1) {
            return null;
        }

        return trim($match[1]);
    }

    /**
     * @param array{content: string, char_map: array<int, array{file: string, line: int, column: int}>} $parentResolved
     * @param array{content: string, char_map: array<int, array{file: string, line: int, column: int}>} $childResolved
     * @return array{content: string, char_map: array<int, array{file: string, line: int, column: int}>}
     */
    private function mergeBlocksWithSource(array $parentResolved, array $childResolved): array
    {
        $pattern = '/<Block\s+name\s*=\s*["\']([^"\']+)["\']\s*>(.*?)<\/Block>/is';
        $childBlocks = $this->extractBlocksWithSource($childResolved, $pattern);

        preg_match_all($pattern, $parentResolved['content'], $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        if ($matches === []) {
            return $parentResolved;
        }

        $mergedContent = '';
        $mergedMap = [];
        $cursor = 0;

        foreach ($matches as $match) {
            $fullMatch = (string) $match[0][0];
            $matchOffset = (int) $match[0][1];
            $matchLength = strlen($fullMatch);
            $blockName = (string) $match[1][0];
            $innerContent = (string) $match[2][0];
            $innerOffset = (int) $match[2][1];

            $prefix = $this->sliceResolvedSegment($parentResolved, $cursor, $matchOffset - $cursor);
            $mergedContent .= $prefix['content'];
            if ($prefix['char_map'] !== []) {
                $mergedMap = array_merge($mergedMap, $prefix['char_map']);
            }

            if (isset($childBlocks[$blockName])) {
                $replacement = $childBlocks[$blockName];
            } else {
                $replacement = $this->sliceResolvedSegment($parentResolved, $innerOffset, strlen($innerContent));
            }

            $mergedContent .= $replacement['content'];
            if ($replacement['char_map'] !== []) {
                $mergedMap = array_merge($mergedMap, $replacement['char_map']);
            }

            $cursor = $matchOffset + $matchLength;
        }

        $suffix = $this->sliceResolvedSegment($parentResolved, $cursor, strlen($parentResolved['content']) - $cursor);
        $mergedContent .= $suffix['content'];
        if ($suffix['char_map'] !== []) {
            $mergedMap = array_merge($mergedMap, $suffix['char_map']);
        }

        return [
            'content' => $mergedContent,
            'char_map' => $mergedMap,
        ];
    }

    /**
     * @param array{content: string, char_map: array<int, array{file: string, line: int, column: int}>} $resolved
     * @return array<string, array{content: string, char_map: array<int, array{file: string, line: int, column: int}>}>
     */
    private function extractBlocksWithSource(array $resolved, string $pattern): array
    {
        $blocks = [];
        preg_match_all($pattern, $resolved['content'], $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            $name = (string) $match[1][0];
            $innerContent = (string) $match[2][0];
            $innerOffset = (int) $match[2][1];
            $blocks[$name] = $this->sliceResolvedSegment($resolved, $innerOffset, strlen($innerContent));
        }

        return $blocks;
    }

    /**
     * @param array{content: string, char_map: array<int, array{file: string, line: int, column: int}>} $resolved
     * @return array{content: string, char_map: array<int, array{file: string, line: int, column: int}>}
     */
    private function sliceResolvedSegment(array $resolved, int $start, int $length): array
    {
        if ($length <= 0) {
            return ['content' => '', 'char_map' => []];
        }

        return [
            'content' => substr($resolved['content'], $start, $length),
            'char_map' => array_slice($resolved['char_map'], $start, $length),
        ];
    }

    /**
     * @return array{content: string, line: int, column: int}
     */
    private function removeExtendsDirective(string $content): array
    {
        if (preg_match('/^\s*extends\s+["\']([^"\']+)["\']\s*;[ \t]*\R?/i', $content, $match) !== 1) {
            return ['content' => $content, 'line' => 1, 'column' => 1];
        }

        $removed = $match[0];
        $withoutExtends = substr($content, strlen($removed));
        [$line, $column] = $this->advanceCursorByText($removed, 1, 1);

        return [
            'content' => $withoutExtends,
            'line' => $line,
            'column' => $column,
        ];
    }

    /**
     * @return array<int, array{file: string, line: int, column: int}>
     */
    private function buildCharMap(string $content, string $file, int $startLine = 1, int $startColumn = 1): array
    {
        $line = $startLine;
        $column = $startColumn;
        $map = [];
        $length = strlen($content);

        for ($i = 0; $i < $length; $i++) {
            $char = $content[$i];
            $map[$i] = [
                'file' => $file,
                'line' => $line,
                'column' => $column,
            ];

            if ($char === "\n") {
                $line++;
                $column = 1;
                continue;
            }

            $column++;
        }

        return $map;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function advanceCursorByText(string $text, int $line, int $column): array
    {
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            if ($text[$i] === "\n") {
                $line++;
                $column = 1;
                continue;
            }
            $column++;
        }

        return [$line, $column];
    }

    private function augmentParserExceptionWithTemplate(ParserException $exception, string $templatePath): ParserException
    {
        $context = $exception->getContext();
        if (isset($context['template_file'], $context['line'], $context['column'])) {
            return $exception;
        }

        return new ParserException(
            "Template parse error in '{$templatePath}': " . $exception->getMessage(),
            0,
            $exception,
            ['template_file' => $templatePath],
            'Template syntax error.'
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
