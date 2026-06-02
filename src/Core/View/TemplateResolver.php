<?php

namespace Core\View;

use Core\View\Exceptions\ViewException;

class TemplateResolver
{
    public function __construct(private string $templatesDir)
    {
    }

    public function resolve(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new ViewException('Template path contains invalid null byte.');
        }

        if (str_starts_with($path, '@components/')) {
            $path = 'components/' . substr($path, strlen('@components/'));
        }

        if (!str_ends_with($path, '.html')) {
            $path .= '.html';
        }

        $candidate = $this->templatesDir . '/' . ltrim($path, '/');
        $realTemplateDir = realpath($this->templatesDir);
        $realCandidate = realpath($candidate);

        if ($realTemplateDir === false) {
            throw new ViewException('Templates directory is invalid: ' . $this->templatesDir);
        }

        if ($realCandidate === false) {
            return $candidate;
        }

        if (!$this->isPathInsideTemplatesDir($realCandidate, $realTemplateDir)) {
            throw new ViewException('Template path traversal is not allowed: ' . $path);
        }

        return $realCandidate;
    }

    private function isPathInsideTemplatesDir(string $realCandidate, string $realTemplateDir): bool
    {
        $normalizedTemplateDir = rtrim($realTemplateDir, DIRECTORY_SEPARATOR);
        $normalizedCandidate = rtrim($realCandidate, DIRECTORY_SEPARATOR);

        if ($normalizedCandidate === $normalizedTemplateDir) {
            return true;
        }

        return str_starts_with($realCandidate, $normalizedTemplateDir . DIRECTORY_SEPARATOR);
    }
}
