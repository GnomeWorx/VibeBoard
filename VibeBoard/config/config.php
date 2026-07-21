<?php

namespace VibeBoard\Config;

/**
 * Simple configuration loader.
 *
 * Looks for values in environment variables first, then falls back to
 * sensible defaults for local development.
 */
class Config
{
    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            $value = $default;
        }

        self::$cache[$key] = $value;
        return $value;
    }

    public static function db(): array
    {
        return [
            'host' => self::get('DB_HOST', '127.0.0.1'),
            'port' => (int) self::get('DB_PORT', '3306'),
            'database' => self::get('DB_DATABASE', 'vibeboard'),
            'username' => self::get('DB_USERNAME', 'vibeboard'),
            'password' => self::get('DB_PASSWORD', 'vibeboard'),
            'charset' => self::get('DB_CHARSET', 'utf8mb4'),
        ];
    }

    public static function app(): array
    {
        return [
            'name' => self::get('APP_NAME', 'VibeBoard'),
            'version' => self::get('APP_VERSION', '1.0.0'),
            'env' => self::get('APP_ENV', 'development'),
        ];
    }

    public static function resetCache(): void
    {
        self::$cache = [];
    }
}
