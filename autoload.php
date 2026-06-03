<?php
/**
 * PHP Template Engine - Autoloader
 * 
 * Carregador PSR-4 simples sem dependências externas
 */

spl_autoload_register(function ($class) {
    $prefixes = ['Core\\View\\', 'Beobles\\Core\\View\\'];
    $baseDir = __DIR__ . '/src/Core/View/';

    foreach ($prefixes as $prefix) {
        if (strpos($class, $prefix) !== 0) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (!file_exists($file)) {
            return;
        }

        require $file;

        if ($prefix === 'Beobles\\Core\\View\\') {
            $newClass = 'Core\\View\\' . $relativeClass;
            if (class_exists($newClass, false) && !class_exists($class, false)) {
                class_alias($newClass, $class);
            }
        }

        return;
    }
});
