<?php

declare(strict_types=1);

namespace VibeBoard\Tests;

use PHPUnit\Framework\TestCase;
use VibeBoard\Services\Metrics;

class MetricsTest extends TestCase
{
    private static ?Metrics $metrics = null;

    public static function setUpBeforeClass(): void
    {
        global $pdo;
        if (!$pdo) {
            self::markTestSkipped('Database not available');
        }
        self::$metrics = new Metrics($pdo);
    }

    public function testGetProgressPercentage(): void
    {
        $pct = self::$metrics->getProgressPercentage();
        $this->assertIsFloat($pct);
        $this->assertGreaterThanOrEqual(0.0, $pct);
        $this->assertLessThanOrEqual(100.0, $pct);
    }

    public function testGetStatusBreakdown(): void
    {
        $breakdown = self::$metrics->getStatusBreakdown();
        $this->assertIsArray($breakdown);
        // Should have at least one status key
        $this->assertNotEmpty($breakdown);
        // Keys should include valid statuses
        foreach ($breakdown as $status => $count) {
            $this->assertIsString($status);
            $this->assertIsInt($count);
            $this->assertGreaterThanOrEqual(0, $count);
        }
    }
}
