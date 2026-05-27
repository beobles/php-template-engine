<?php

namespace Beobles\Core\View\Compilation;

/**
 * Compila expressões do template para código PHP válido.
 *
 * Responsabilidades:
 *   - Converter dot-notation (user.name) em chamadas __tpl_get()
 *   - Prefixar variáveis simples com $ quando necessário
 *   - Resolver cadeia de filtros (| filtro | filtro(arg))
 *   - Gerar a função auxiliar __tpl_get usada em tempo de execução
 */
class ExpressionCompiler
{
    /**
     * Código PHP do helper __tpl_get injetado uma vez no template compilado.
     */
    public function preamble(): string
    {
        return <<<'PHP'
if (!function_exists('__tpl_get')) {
    function __tpl_get(array $scope, string $path)
    {
        $segments = explode('.', $path);
        $value    = $scope[$segments[0]] ?? null;
        foreach (array_slice($segments, 1) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
                continue;
            }
            if (is_object($value)) {
                if (isset($value->{$segment})) {
                    $value = $value->{$segment};
                    continue;
                }
                $getter = 'get' . ucfirst($segment);
                if (method_exists($value, $getter)) {
                    $value = $value->{$getter}();
                    continue;
                }
            }
            return null;
        }
        return $value;
    }
}

PHP;
    }

    /**
     * Compila uma expressão simples (sem filtros) para PHP.
     *
     * Exemplos:
     *   "user.name"   → "__tpl_get(get_defined_vars(), 'user.name')"
     *   "count"       → "$count"
     *   "user.age > 18 ? 'M' : 'm'" → mantém operadores intactos
     */
    public function compile(string $expression): string
    {
        $expression = trim($expression);
        if ($expression === '') {
            return 'null';
        }

        // Substituir dot-notation por placeholders antes de tokenizar
        $placeholders = [];
        $counter      = 0;
        $expression   = preg_replace_callback(
            '/\b([a-zA-Z_]\w*(?:\.[a-zA-Z_]\w*)+)\b/',
            function (array $matches) use (&$placeholders, &$counter): string {
                $key              = "__TPL_DOT_{$counter}__";
                $placeholders[$key] = "__tpl_get(get_defined_vars(), '" . $matches[1] . "')";
                ++$counter;
                return $key;
            },
            $expression
        );

        $phpTokens  = token_get_all('<?php ' . $expression);
        array_shift($phpTokens); // remove T_OPEN_TAG

        $result     = '';
        $tokenCount = count($phpTokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $phpTokens[$i];

            if (is_string($token)) {
                $result .= $token;
                continue;
            }

            [$type, $text] = $token;

            if ($type !== T_STRING) {
                $result .= $text;
                continue;
            }

            // Substituir placeholder de dot-notation
            if (isset($placeholders[$text])) {
                $result .= $placeholders[$text];
                continue;
            }

            // Identificadores reservados não recebem $
            if ($this->isReserved($text)) {
                $result .= $text;
                continue;
            }

            // Chamada de função: next token é (
            $next = $this->nextSignificant($phpTokens, $i + 1);
            if ($next === '(') {
                $result .= $text;
                continue;
            }

            // Acesso a propriedade/método: precedido de -> ou ::
            $prev = $this->prevSignificant($phpTokens, $i - 1);
            if ($prev === '->' || $prev === '::' || $prev === '$') {
                $result .= $text;
                continue;
            }

            $result .= '$' . $text;
        }

        return $result;
    }

    /**
     * Compila uma expressão que pode conter cadeia de filtros.
     *
     * Retorna [código PHP, isRaw].
     * isRaw = true quando o último filtro da cadeia for "raw".
     *
     * @return array{0: string, 1: bool}
     */
    public function compileChain(string $expression): array
    {
        $parts          = $this->splitByPipes($expression);
        $baseExpression = array_shift($parts) ?? '';
        $code           = $this->compile($baseExpression);
        $isRaw          = false;

        foreach ($parts as $filterPart) {
            $filterPart = trim($filterPart);
            if ($filterPart === '') {
                continue;
            }

            if (preg_match('/^(\w+)(?:\((.*)\))?$/s', $filterPart, $matches) !== 1) {
                continue;
            }

            $name     = $matches[1];
            $rawArgs  = trim($matches[2] ?? '');
            $argsCode = $rawArgs === '' ? '[]' : '[' . $rawArgs . ']';

            $code  = '$__engine->applyFilter(' . $code . ', ' . var_export($name, true) . ', ' . $argsCode . ')';
            $isRaw = ($name === 'raw');
        }

        return [$code, $isRaw];
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    private function isReserved(string $value): bool
    {
        // Constantes ALL_CAPS
        if (preg_match('/^[A-Z_][A-Z0-9_]*$/', $value) === 1) {
            return true;
        }

        static $reserved = [
            'true', 'false', 'null', 'and', 'or', 'xor',
            'instanceof', 'new', 'clone', 'match', 'fn',
            'array', 'parent', 'self', 'static',
        ];

        return in_array(strtolower($value), $reserved, true);
    }

    /** @param array<int, array{int,string,int}|string> $tokens */
    private function nextSignificant(array $tokens, int $start): ?string
    {
        for ($i = $start, $len = count($tokens); $i < $len; $i++) {
            $token = $tokens[$i];
            if (is_string($token)) {
                return trim($token) !== '' ? $token : null;
            }
            if ($token[0] !== T_WHITESPACE) {
                return $token[1];
            }
        }
        return null;
    }

    /** @param array<int, array{int,string,int}|string> $tokens */
    private function prevSignificant(array $tokens, int $start): ?string
    {
        for ($i = $start; $i >= 0; $i--) {
            $token = $tokens[$i];
            if (is_string($token)) {
                return trim($token) !== '' ? $token : null;
            }
            if ($token[0] !== T_WHITESPACE) {
                return $token[1];
            }
        }
        return null;
    }

    /**
     * Divide a string de expressão em partes pelo pipe |,
     * respeitando strings, parênteses, colchetes e chaves.
     *
     * @return string[]
     */
    private function splitByPipes(string $value): array
    {
        $parts  = [];
        $buffer = '';
        $depth  = 0;
        $quote  = null;
        $len    = strlen($value);

        for ($i = 0; $i < $len; $i++) {
            $char = $value[$i];

            if ($quote !== null) {
                if ($char === $quote && ($i === 0 || $value[$i - 1] !== '\\')) {
                    $quote = null;
                }
                $buffer .= $char;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote   = $char;
                $buffer .= $char;
                continue;
            }

            if (in_array($char, ['(', '[', '{'], true)) {
                ++$depth;
                $buffer .= $char;
                continue;
            }

            if (in_array($char, [')', ']', '}'], true)) {
                $depth  = max(0, $depth - 1);
                $buffer .= $char;
                continue;
            }

            if ($char === '|' && $depth === 0) {
                $parts[] = trim($buffer);
                $buffer  = '';
                continue;
            }

            $buffer .= $char;
        }

        $parts[] = trim($buffer);

        return $parts;
    }
}
