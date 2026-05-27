<?php
/**
 * PHP Template Engine - Autoloader
 * 
 * Carregador PSR-4 simples sem dependências externas
 */

spl_autoload_register(function ($class) {
    $prefix = 'Beobles\\Core\\View\\';
    $baseDir = __DIR__ . '/src/Core/View/';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
