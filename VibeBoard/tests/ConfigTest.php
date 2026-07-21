<?php

use PHPUnit\Framework\TestCase;
use VibeBoard\Config\Config;

class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        Config::resetCache();
        foreach (array_keys(getenv()) as $k) {
            if (str_starts_with($k, 'VB_')) {
                putenv("{$k}");
            }
        }
    }

    public function testDefaultDatabaseConfig(): void
    {
        $db = Config::db();
        $this->assertSame('127.0.0.1', $db['host']);
        $this->assertSame(3306, $db['port']);
        $this->assertSame('vibeboard', $db['database']);
        $this->assertSame('vibeboard', $db['username']);
        $this->assertSame('vibeboard', $db['password']);
        $this->assertSame('utf8mb4', $db['charset']);
    }

    public function testEnvironmentOverride(): void
    {
        putenv('DB_HOST=db.example.com');
        putenv('DB_PORT=3307');
        putenv('DB_DATABASE=prod');
        Config::resetCache();

        $db = Config::db();
        $this->assertSame('db.example.com', $db['host']);
        $this->assertSame(3307, $db['port']);
        $this->assertSame('prod', $db['database']);
    }

    public function testAppConfigDefaults(): void
    {
        $app = Config::app();
        $this->assertSame('VibeBoard', $app['name']);
        $this->assertSame('1.0.0', $app['version']);
        $this->assertSame('development', $app['env']);
    }

    public function testGetWithDefault(): void
    {
        Config::resetCache();
        $this->assertSame('fallback', Config::get('NON_EXISTENT_KEY', 'fallback'));
    }

    public function testGetCachesValue(): void
    {
        putenv('CACHE_TEST_KEY=first');
        Config::resetCache();
        $this->assertSame('first', Config::get('CACHE_TEST_KEY'));
        putenv('CACHE_TEST_KEY=second');
        $this->assertSame('first', Config::get('CACHE_TEST_KEY'));
    }
}
