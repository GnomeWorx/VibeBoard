<?php

/**
 * VibeBoard Database Connection
 * 
 * Establishes a persistent connection to the MariaDB database.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use VibeBoard\Config\Config;

try {
    $dbConfig = Config::get('db');
    
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset=" . $dbConfig['charset'];
    
    // Using PDO with persistent connection enabled via attribute
    $options = [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
        \PDO::ATTR_PERSISTENT => true, // Ensures persistent connection
    ];

    $pdo = new \PDO($dsn, $dbConfig['user'], $dbConfig['pass'], $options);

} catch (\PDOException $e) {
    // In a production environment, log this and show a generic 500 error.
    error_log("Database connection failed: " . $e->getMessage());
    die("Internal Server Error: Database connection failed.");
}

return $pdo;
