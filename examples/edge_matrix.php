<?php

require_once __DIR__ . '/../autoload.php';

use Core\View\Engine;

/**
 * Matriz de cenários de borda para diretivas e filtros.
 * Execução: php examples/edge_matrix.php
 */

$baseDir = sys_get_temp_dir() . '/template_engine_edge_matrix_' . uniqid('', true);
$templatesDir = $baseDir . '/templates';
$cacheDir = $baseDir . '/cache';

if (!is_dir($templatesDir) && !mkdir($templatesDir, 0755, true) && !is_dir($templatesDir)) {
    fwrite(STDERR, "Falha ao criar diretório de templates: {$templatesDir}\n");
    exit(1);
}

if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
    fwrite(STDERR, "Falha ao criar diretório de cache: {$cacheDir}\n");
    exit(1);
}

$engine = new Engine([
    'templates_dir' => $templatesDir,
    'cache_dir' => $cacheDir,
    'cache_enabled' => false,
    'auto_escape' => true,
    'debug' => true,
]);

$writeTemplate = static function (string $name, string $content) use ($templatesDir): void {
    file_put_contents($templatesDir . '/' . $name, $content);
};

$normalize = static function (string $value): string {
    return trim((string) preg_replace('/\s+/', ' ', $value));
};

$writeTemplate('partial.html', '[[{{ user.name }}]]');

$cases = [
    // Diretivas
    [
        'name' => 'directive_if_elseif_else',
        'template' => '<If condition={{ user.level === 1 }}>ONE<ElseIf condition={{ user.level === 2 }}>TWO<Else>OTHER</Else></If>',
        'data' => ['user' => ['level' => 2]],
        'expect' => 'TWO',
    ],
    [
        'name' => 'directive_foreach_loop_meta',
        'template' => '<Foreach items={{ users }} as="user,index">{{ __loop.count }}-{{ index }}-{{ user.name }};</Foreach>',
        'data' => ['users' => [['name' => 'Ana'], ['name' => 'Beto']]],
        'expect' => '1-0-Ana;2-1-Beto;',
    ],
    [
        'name' => 'directive_set',
        'template' => '<Set var="total" value={{ 2 + 3 }} />{{ total }}',
        'data' => [],
        'expect' => '5',
    ],
    [
        'name' => 'directive_include_with_data_expression',
        'template' => '<Include path="partial.html" data={{ ["user" => ["name" => user.name]] }} />',
        'data' => ['user' => ['name' => 'Carla']],
        'expect' => '[[Carla]]',
    ],
    [
        'name' => 'directive_block_passthrough',
        'template' => '<Block name="content">conteudo</Block>',
        'data' => [],
        'expect' => 'conteudo',
    ],

    // Filtros de string
    ['name' => 'filter_uppercase_utf8', 'template' => '{{ value | uppercase }}', 'data' => ['value' => 'João'], 'expect' => 'JOÃO'],
    ['name' => 'filter_lowercase_utf8', 'template' => '{{ value | lowercase }}', 'data' => ['value' => 'AÇÃO'], 'expect' => 'ação'],
    ['name' => 'filter_ucfirst_utf8', 'template' => '{{ value | ucfirst }}', 'data' => ['value' => 'joão'], 'expect' => 'João'],
    ['name' => 'filter_trim', 'template' => '{{ value | trim }}', 'data' => ['value' => '  x  '], 'expect' => 'x'],
    ['name' => 'filter_truncate', 'template' => '{{ value | truncate(4, "..") }}', 'data' => ['value' => 'abcdef'], 'expect' => 'abcd..'],
    ['name' => 'filter_slug', 'template' => '{{ value | slug }}', 'data' => ['value' => 'Ação de Teste!'], 'expect' => 'acao-de-teste'],
    ['name' => 'filter_reverse_utf8', 'template' => '{{ value | reverse }}', 'data' => ['value' => 'João'], 'expect' => 'oãoJ'],

    // Filtros numéricos/data
    ['name' => 'filter_currency', 'template' => '{{ value | currency("BRL") }}', 'data' => ['value' => 1234.5], 'expect' => 'R$ 1.234,50'],
    ['name' => 'filter_number_format', 'template' => '{{ value | number_format(2) }}', 'data' => ['value' => 1234.5], 'expect' => '1.234,50'],
    ['name' => 'filter_abs', 'template' => '{{ value | abs }}', 'data' => ['value' => -10], 'expect' => '10'],
    ['name' => 'filter_date', 'template' => '{{ value | date("Y-m-d") }}', 'data' => ['value' => '2024-01-02'], 'expect' => '2024-01-02'],

    // Filtros de array
    ['name' => 'filter_count', 'template' => '{{ value | count }}', 'data' => ['value' => [1, 2, 3]], 'expect' => '3'],
    ['name' => 'filter_first', 'template' => '{{ value | first }}', 'data' => ['value' => [10, 20]], 'expect' => '10'],
    ['name' => 'filter_last', 'template' => '{{ value | last }}', 'data' => ['value' => [10, 20]], 'expect' => '20'],
    ['name' => 'filter_join_nested_values', 'template' => '{! value | join(" | ") !}', 'data' => ['value' => ['a', ['k' => 'v'], true]], 'expectContains' => 'a | {"k":"v"} | 1'],
    ['name' => 'filter_json', 'template' => '{! value | json !}', 'data' => ['value' => ['a' => 1]], 'expectContains' => '"a": 1'],
];

$failures = [];

foreach ($cases as $case) {
    $templateFile = $case['name'] . '.html';
    $writeTemplate($templateFile, $case['template']);

    try {
        $output = $engine->render($templateFile, $case['data']);
    } catch (\Throwable $e) {
        $failures[] = $case['name'] . ': exception inesperada -> ' . $e->getMessage();
        continue;
    }

    $normalizedOutput = $normalize($output);
    if (array_key_exists('expect', $case)) {
        $expected = $normalize((string) $case['expect']);
        if ($normalizedOutput !== $expected) {
            $failures[] = $case['name'] . ": esperado '{$expected}', obtido '{$normalizedOutput}'";
        }
        continue;
    }

    if (array_key_exists('expectContains', $case)) {
        $expectedContains = (string) $case['expectContains'];
        if (strpos($normalizedOutput, $expectedContains) === false) {
            $failures[] = $case['name'] . ": saída não contém '{$expectedContains}', obtido '{$normalizedOutput}'";
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Falhas na matriz de borda:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "OK: matriz de diretivas/filtros passou com " . count($cases) . " cenários.\n";
