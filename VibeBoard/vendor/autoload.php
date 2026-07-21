<?php

spl_autoload_register(function (string $class): void {
    $prefixes = [
        'VibeBoard\\Config\\' => __DIR__ . '/config/',
        'VibeBoard\\' => __DIR__ . '/src/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});
