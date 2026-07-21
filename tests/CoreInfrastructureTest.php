<?php

declare(strict_types=1);

namespace VibeBoard\Tests;

use PHPUnit\Framework\TestCase;
use VibeBoard\Config\Config;
use VibeBoard\Router\Router;

class CoreInfrastructureTest extends TestCase
{
    public function testConfigLoading(): void
    {
        $dbHost = Config::get('db.host');
        $this->assertEquals('localhost', $dbHost, "Config 'db.host' should be 'localhost'");

        $isDebug = Config::get('app.debug');
        $this->assertTrue($isDebug, "Config 'app.debug' should be true");

        $missing = Config::get('non_existent_key');
        $this->assertNull($missing, "Missing keys should return null");
    }

    public function testDatabaseConnectivity(): void
    {
        global $pdo;
        $this->assertNotNull($pdo, "PDO connection object must be initialized.");
        $this->assertInstanceOf(\PDO::class, $pdo);

        $stmt = $pdo->query("SELECT 1");
        $result = $stmt->fetch(\PDO::FETCH_NUM);
        $this->assertEquals(1, (int)$result[0], "Database should successfully execute a basic query.");
    }

    public function testRouterRegistration(): void
    {
        $router = new Router();
        $called = false;
        $router->addRoute('GET', '/test/route', function () use (&$called) {
            $called = true;
        });

        // Simulate the request
        $_SERVER['REQUEST_URI'] = '/test/route';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $router->resolve();

        $this->assertTrue($called, "Router should execute the handler for registered routes.");
    }
}
