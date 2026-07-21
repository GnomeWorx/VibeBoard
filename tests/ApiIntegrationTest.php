<?php

declare(strict_types=1);

namespace VibeBoard\Tests;

use PHPUnit\Framework\TestCase;

class ApiIntegrationTest extends TestCase
{
    private const BASE_URL = 'http://127.0.0.1:8899';

    public static function setUpBeforeClass(): void
    {
        // Check if the dev server is running
        $fp = @fsockopen('127.0.0.1', 8899, $errno, $errstr, 2);
        if (!$fp) {
            self::markTestSkipped('PHP dev server not running on port 8899. Start with: php -S 127.0.0.1:8899 -t public/');
        }
        fclose($fp);
    }

    public function testStatusEndpoint(): void
    {
        $data = $this->getJson('/api/status');
        $this->assertArrayHasKey('status', $data);
        $this->assertContains($data['status'], ['online', 'degraded']);
        $this->assertArrayHasKey('db', $data);
        $this->assertArrayHasKey('version', $data);
    }

    public function testMetricsEndpoint(): void
    {
        $data = $this->getJson('/api/metrics');
        $this->assertArrayHasKey('progressPercentage', $data);
        $this->assertIsFloat($data['progressPercentage']);
        $this->assertArrayHasKey('breakdown', $data);
        $this->assertIsArray($data['breakdown']);
    }

    public function testListTasks(): void
    {
        $tasks = $this->getJson('/api/tasks');
        $this->assertIsArray($tasks);
    }

    public function testCreateTask(): void
    {
        $ch = curl_init(self::BASE_URL . '/api/tasks');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'title' => 'API Test Task',
                'description' => 'Created by integration test',
                'status' => 'Backlog',
            ]),
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertEquals(201, $httpCode);
        $data = json_decode($response, true);
        $this->assertTrue($data['success'] ?? false);
    }

    public function testUpdateTask(): void
    {
        $ch = curl_init(self::BASE_URL . '/api/tasks/1');
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'title' => 'Integration Test Update',
                'status' => 'Done',
            ]),
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertEquals(200, $httpCode);
        $data = json_decode($response, true);
        $this->assertTrue($data['success'] ?? false);
    }

    public function testDeleteTask(): void
    {
        $ch = curl_init(self::BASE_URL . '/api/tasks/999');
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 999 doesn't exist, but DELETE should still return 200 (idempotent)
        $this->assertContains($httpCode, [200, 404]);
    }

    private function getJson(string $path): mixed
    {
        $ch = curl_init(self::BASE_URL . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertEquals(200, $httpCode, "GET $path should return 200");
        $data = json_decode($response, true);
        $this->assertNotNull($data, "GET $path should return valid JSON");
        return $data;
    }
}
