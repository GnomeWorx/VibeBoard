<?php

declare(strict_types=1);

namespace VibeBoard\Tests;

use PHPUnit\Framework\TestCase;
use VibeBoard\Models\Task;

class TaskTest extends TestCase
{
    private static ?Task $taskModel = null;
    private static int $testTaskId = 0;

    public static function setUpBeforeClass(): void
    {
        global $pdo;
        if (!$pdo) {
            self::markTestSkipped('Database not available');
        }
        self::$taskModel = new Task($pdo);
    }

    public function testFindAll(): void
    {
        $tasks = self::$taskModel->findAll();
        $this->assertIsArray($tasks);
    }

    public function testCreate(): void
    {
        $result = self::$taskModel->create([
            'title' => 'PHPUnit Test Task',
            'description' => 'Created by automated test',
            'status' => 'Backlog',
        ]);
        $this->assertTrue($result);

        // Get the ID of the last inserted task
        global $pdo;
        self::$testTaskId = (int)$pdo->lastInsertId();
        $this->assertGreaterThan(0, self::$testTaskId);
    }

    /**
     * @depends testCreate
     */
    public function testFindById(): void
    {
        $task = self::$taskModel->findById(self::$testTaskId);
        $this->assertNotNull($task);
        $this->assertEquals('PHPUnit Test Task', $task['title']);
        $this->assertEquals('Backlog', $task['status']);
    }

    /**
     * @depends testCreate
     */
    public function testUpdate(): void
    {
        $result = self::$taskModel->update(self::$testTaskId, [
            'title' => 'Updated Test Task',
            'status' => 'In Progress',
        ]);
        $this->assertTrue($result);

        $task = self::$taskModel->findById(self::$testTaskId);
        $this->assertEquals('Updated Test Task', $task['title']);
        $this->assertEquals('In Progress', $task['status']);
    }

    public function testFindByIds(): void
    {
        $ids = [1, 2, 3];
        $tasks = self::$taskModel->findByIds($ids);
        $this->assertIsArray($tasks);
        $this->assertCount(3, $tasks);
    }

    public function testGetOverdueTasks(): void
    {
        $overdue = self::$taskModel->getOverdueTasks();
        $this->assertIsArray($overdue);
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $result = self::$taskModel->delete(self::$testTaskId);
        $this->assertTrue($result);

        $task = self::$taskModel->findById(self::$testTaskId);
        $this->assertNull($task);
    }
}
