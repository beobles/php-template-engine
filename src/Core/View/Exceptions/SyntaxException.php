<?php

namespace Core\View\Exceptions;

/**
 * Exceção de sintaxe
 */
class SyntaxException extends ViewException
{
    private string $templateFile = '';
    private int $lineNumber = 1;
    private int $columnNumber = 1;
    private string $snippet = '';
    private string $lintOutput = '';

    /**
     * @param array<string,mixed> $context
     */
    public function __construct(
        string $message = 'Template syntax error.',
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = [],
        string $safeMessage = 'Template syntax error.'
    ) {
        parent::__construct($message, $code, $previous, $context, $safeMessage);

        $this->templateFile = (string) ($context['template_file'] ?? '');
        $this->lineNumber = (int) ($context['line'] ?? 1);
        $this->columnNumber = (int) ($context['column'] ?? 1);
        $this->snippet = (string) ($context['snippet'] ?? '');
        $this->lintOutput = (string) ($context['lint_output'] ?? '');
    }

    public static function fromLocation(
        string $templateFile,
        int $lineNumber,
        int $columnNumber,
        string $snippet = '',
        string $details = ''
    ): self {
        $message = "Template syntax error in '{$templateFile}' at line {$lineNumber}, column {$columnNumber}.";
        if ($snippet !== '') {
            $message .= " Snippet: {$snippet}";
        }
        if ($details !== '') {
            $message .= " Details: {$details}";
        }

        return new self($message, 0, null, [
            'template_file' => $templateFile,
            'line' => $lineNumber,
            'column' => $columnNumber,
            'snippet' => $snippet,
            'lint_output' => $details,
        ]);
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
