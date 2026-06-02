<?php

namespace Core\View\Exceptions;

/**
 * Exceção de sintaxe
 */
class SyntaxException extends ViewException
{
    private string $templateFile;
    private int $lineNumber;
    private int $columnNumber;
    private string $snippet;
    private string $lintOutput;

    public function __construct(
        string $templateFile,
        int $lineNumber,
        int $columnNumber,
        string $snippet = '',
        string $lintOutput = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $context = [
            'template_file' => $templateFile,
            'line' => $lineNumber,
            'column' => $columnNumber,
            'snippet' => $snippet,
            'lint_output' => $lintOutput,
        ];

        $message = "Compiled template syntax error in '{$templateFile}' at line {$lineNumber}, column {$columnNumber}.";
        if ($snippet !== '') {
            $message .= " Snippet: {$snippet}";
        }
        if ($lintOutput !== '') {
            $message .= " PHP lint: {$lintOutput}";
        }

        parent::__construct($message, $code, $previous, $context, 'Template syntax error.');
        $this->templateFile = $templateFile;
        $this->lineNumber = $lineNumber;
        $this->columnNumber = $columnNumber;
        $this->snippet = $snippet;
        $this->lintOutput = $lintOutput;
    }

    public function getTemplateFile(): string
    {
        return $this->templateFile;
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    public function getColumnNumber(): int
    {
        return $this->columnNumber;
    }

    public function getSnippet(): string
    {
        return $this->snippet;
    }

    public function getLintOutput(): string
    {
        return $this->lintOutput;
    }
}
