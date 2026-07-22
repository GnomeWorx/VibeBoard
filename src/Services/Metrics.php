<?php

namespace VibeBoard\Services;

use PDO;

/**
 * Analytics Engine
 * 
 * Handles calculations for project progress and metrics.
 */
class Metrics {
    private PDO $pdo;
    private ?int $projectId;

    public function __construct(PDO $pdo, ?int $projectId = null) {
        $this->pdo = $pdo;
        $this->projectId = $projectId;
    }

    /**
     * Calculate completion percentage based on tasks in 'Done' status.
     * @return float Percentage between 0 and 100.
     */
    public function getProgressPercentage(): float {
        $where = '';
        $params = [];
        if ($this->projectId !== null) {
            $where = ' WHERE project_id = ?';
            $params[] = $this->projectId;
        }
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks$where");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
        if ($total == 0) return 0.0;

        $doneWhere = $where ? "$where AND status = 'Done'" : " WHERE status = 'Done'";
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks$doneWhere");
        $stmt->execute($params);
        $completed = (int)$stmt->fetchColumn();
        return round(($completed / $total) * 100);
    }

    /**
     * Get breakdown of tasks by status.
     */
    public function getStatusBreakdown(): array {
        $where = '';
        $params = [];
        if ($this->projectId !== null) {
            $where = ' WHERE project_id = ?';
            $params[] = $this->projectId;
        }
        $stmt = $this->pdo->prepare("SELECT IFNULL(status, 'Plan') AS status, COUNT(*) AS count FROM tasks$where GROUP BY status");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[$row['status']] = (int)$row['count'];
        }
        return $result;
    }

    /**
     * Compute velocity: average tasks completed per day over last 7 days.
     */
    public function getVelocity(): float {
        $where = '';
        $params = [];
        if ($this->projectId !== null) {
            $where = ' AND project_id = ?';
            $params[] = $this->projectId;
        }
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) / 7.0 as velocity
            FROM tasks
            WHERE completed_at IS NOT NULL
              AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
              $where
        ");
        $stmt->execute($params);
        return round((float)$stmt->fetchColumn(), 1);
    }
}