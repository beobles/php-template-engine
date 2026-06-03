<?php

namespace Beobles\Core\View\Cache;

class CompiledTemplateCache
{
    public function __construct(private string $cacheDir)
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /** @param array<int, string> $dependencies */
    public function load(string $templatePath, string $compiledCode, array $dependencies = []): string
    {
        $cacheKey = hash('sha256', $templatePath);
        $cacheFile = $this->getCacheFile($cacheKey);
        $metaFile = $this->getMetaFile($cacheFile);

        if ($this->isCacheValid($cacheFile, $metaFile, $dependencies)) {
            return $this->requireCompiledClass($cacheFile, $metaFile);
        }

        return $this->writeCache($cacheFile, $metaFile, $compiledCode, $dependencies);
    }

    private function getCacheFile(string $key): string
    {
        return rtrim($this->cacheDir, '/\\') . '/' . substr($key, 0, 2) . '/' . substr($key, 2) . '.php';
    }

    private function getMetaFile(string $cacheFile): string
    {
        return substr($cacheFile, 0, -4) . '.meta';
    }

    /** @param array<int, string> $dependencies */
    private function isCacheValid(string $cacheFile, string $metaFile, array $dependencies): bool
    {
        if (!is_file($cacheFile) || !is_file($metaFile)) {
            return false;
        }

        $meta = $this->readMeta($metaFile);
        if (!is_array($meta)) {
            return false;
        }

        $expectedHash = hash_file('sha256', $cacheFile);
        if (($meta['hash'] ?? null) !== $expectedHash) {
            return false;
        }

        $cacheTime = filemtime($cacheFile) ?: 0;
        foreach ($dependencies as $dependency) {
            if (!is_file($dependency) || ((filemtime($dependency) ?: 0) > $cacheTime)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, string> $dependencies */
    private function writeCache(string $cacheFile, string $metaFile, string $compiledCode, array $dependencies): string
    {
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($cacheFile, $compiledCode);
        chmod($cacheFile, 0644);

        $class = $this->extractClassName($compiledCode);
        $meta = [
            'generated_at' => time(),
            'dependencies' => $dependencies,
            'hash' => hash_file('sha256', $cacheFile),
            'class' => $class,
        ];

        file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT));

        return $this->requireCompiledClass($cacheFile, $metaFile);
    }

    private function requireCompiledClass(string $cacheFile, string $metaFile): string
    {
        $meta = $this->readMeta($metaFile);
        if (is_array($meta) && isset($meta['class']) && is_string($meta['class']) && $meta['class'] !== '' && class_exists($meta['class'])) {
            return $meta['class'];
        }

        return (string) require $cacheFile;
    }

    private function readMeta(string $metaFile): ?array
    {
        $content = file_get_contents($metaFile);
        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function extractClassName(string $compiledCode): string
    {
        $namespace = '';
        if (preg_match('/namespace\s+([^;]+);/', $compiledCode, $namespaceMatch) === 1) {
            $namespace = trim($namespaceMatch[1]);
        }

        $class = '';
        if (preg_match('/class\s+([A-Za-z_][A-Za-z0-9_]*)/', $compiledCode, $classMatch) === 1) {
            $class = $classMatch[1];
        }

        if ($class === '') {
            return '';
        }

        return $namespace === '' ? $class : $namespace . '\\' . $class;
    }
}

