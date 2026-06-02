<?php

require_once __DIR__ . '/../autoload.php';

use Core\View\Engine;

// Criar engine
$engine = new Engine([
    'templates_dir' => __DIR__ . '/templates',
    'cache_dir' => __DIR__ . '/../cache',
    'auto_escape' => true,
    'cache_enabled' => true
]);

// Dados para o template
$data = [
    'user' => [
        'logged' => true,
        'name' => 'João Silva',
        'email' => 'joao@example.com',
        'role' => 'admin',
        'button' => [
            'text' => 'Logout',
            'color' => 'danger'
        ]
    ],
    'users' => [
        ['id' => 1, 'name' => 'Maria', 'email' => 'maria@example.com'],
        ['id' => 2, 'name' => 'Pedro', 'email' => 'pedro@example.com'],
        ['id' => 3, 'name' => 'Ana', 'email' => 'ana@example.com']
    ]
];

// Renderizar template
try {
    echo $engine->render('home.html', $data);
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
