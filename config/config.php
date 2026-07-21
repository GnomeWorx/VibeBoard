<?php

/**
 * VibeBoard Core Configuration Loader
 * 
 * This class handles the loading of environment variables and 
 * provides a centralized configuration array.
 */

declare(strict_types=1);

namespace VibeBoard\Config;

class Config {
    private static array $settings = [];

    public static function load(): void {
        // In a production environment, this would integrate with .env files 
        // using a library like vlucas/phpdotenv.
        self::$settings = [
            'db' => [
                'host' => 'localhost',
                'port' => 3306,
                'dbname' => 'vibeboard_db',
                'user' => 'sfarrant',
                'pass' => '',
                'charset' => 'utf8mb4',
            ],
            'app' => [
                'debug' => true,
                'base_url' => 'http://localhost:8080',
            ]
        ];
    }

    public static function get(string $key): mixed {
        $parts = explode('.', $key);
        $current = self::$settings;
        foreach ($parts as $part) {
            if (!isset($current[$part])) {
                return null;
            }
            $current = $current[$part];
        }
        return $current;
    }
}

// Initialize loader
Config::load();
