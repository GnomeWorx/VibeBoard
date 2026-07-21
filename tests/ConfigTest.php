<?php

declare(strict_types=1);

namespace VibeBoard\Tests;

use PHPUnit\Framework\TestCase;
use VibeBoard\Config\Config;

class ConfigTest extends TestCase
{
    public function testDefaultDatabaseConfig(): void
    {
        $db = Config::get('db');
        $this->assertIsArray($db);
        $this->assertSame('localhost', $db['host']);
        $this->assertSame(3306, $db['port']);
        $this->assertSame('vibeboard_db', $db['dbname']);
    }

    public function testDotNotation(): void
    {
        $this->assertSame('localhost', Config::get('db.host'));
        $this->assertSame(3306, Config::get('db.port'));
        $this->assertTrue(Config::get('app.debug'));
    }

    public function testMissingKey(): void
    {
        $this->assertNull(Config::get('non_existent'));
        $this->assertNull(Config::get('db.missing'));
    }

    public function testAppConfig(): void
    {
        $app = Config::get('app');
        $this->assertIsArray($app);
        $this->assertTrue($app['debug']);
    }
}
