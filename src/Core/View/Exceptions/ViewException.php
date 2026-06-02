<?php

namespace Core\View\Exceptions;

/**
 * Exceção base do template engine
 */
class ViewException extends \Exception
{
    /** @var array<string,mixed> */
    private array $context;
    private string $safeMessage;

    /**
     * @param array<string,mixed> $context
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = [],
        string $safeMessage = 'Template rendering failed.'
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
        $this->safeMessage = $safeMessage;
    }

    /**
     * @return array<string,mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getSafeMessage(): string
    {
        return $this->safeMessage;
    }
}
