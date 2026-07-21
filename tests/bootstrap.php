<?php

declare(strict_types=1);

// VibeBoard Test Suite Bootstrap

// Load project config
require_once __DIR__ . '/../config/config.php';

// PDO connection — captured as global for test access
global $pdo;
$pdo = require_once __DIR__ . '/../config/db.php';

// PSR-4-style autoloader for VibeBoard namespace
spl_autoload_register(function ($class) {
    $prefix = 'VibeBoard\\';
    $base_dir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
