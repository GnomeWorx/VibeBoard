<?php

namespace VibeBoard\Models;

use PDO;
use Exception;

/**
 * Task Model
 * 
 * Handles interaction with the 'tasks' table in MariaDB.
 * Supports assigned_to, depends_on (dependencies), execution_log, 
 * retry_count/max_retries, and cycle detection.
 */
class Task {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?array {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch() ?: null;
        } catch (\PDOException $e) {
            error_log("Database Error in findById($id): " . $e->getMessage());
            throw new Exception("Failed to retrieve task details.");
        }
    }

    /**
     * Fetch a task with its worker name via LEFT JOIN.
     */
    public function findByIdWithWorker(int $id): ?array {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT t.*, w.name AS worker_name, w.role AS worker_role
                 FROM tasks t
                 LEFT JOIN workers w ON w.id = t.assigned_to
                 WHERE t.id = ?"
            );
            $stmt->execute([$id]);
            return $stmt->fetch() ?: null;
        } catch (\PDOException $e) {
            error_log("Database Error in findByIdWithWorker($id): " . $e->getMessage());
            throw new Exception("Failed to retrieve task with worker.");
        }
    }

    public function findAll(?int $projectId = null): array {
        try {
            $sql = "SELECT t.*, w.name AS worker_name, w.role AS worker_role
                    FROM tasks t
                    LEFT JOIN workers w ON w.id = t.assigned_to";
            $params = [];
            if ($projectId !== null) {
                $sql .= " WHERE t.project_id = ?";
                $params[] = $projectId;
            }
            $sql .= " ORDER BY t.created_at DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log("Database Error in findAll: " . $e->getMessage());
            throw new Exception("Failed to fetch all tasks.");
        }
    }

    public function batchUpdate(array $ids, array $data): int {
        try {
            $allowedFields = ['title', 'description', 'status', 'assigned_to', 'depends_on', 'max_retries', 'complexity', 'story_url', 'story_id', 'regression_count'];
            $setClauses = [];
            $params = [];
            foreach ($data as $field => $value) {
                if (!in_array($field, $allowedFields, true)) continue;
                $setClauses[] = "`$field` = ?";
                $params[] = $value;
            }
            if (empty($setClauses)) return 0;
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE tasks SET " . implode(', ', $setClauses) . " WHERE id IN ($placeholders)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($params, $ids));
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            error_log("Database Error in batchUpdate: " . $e->getMessage());
            throw new Exception("Failed to batch-update tasks.");
        }
    }

    public function findByIds(array $ids): array {
        if (empty($ids)) return [];
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->pdo->prepare("SELECT * FROM tasks WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log("Database Error in findByIds: " . $e->getMessage());
            throw new Exception("Failed to fetch a batch of tasks.");
        }
    }

    public function create(array $data): bool {
        try {
            // Cycle detection on depends_on create
            if (isset($data['depends_on']) && $data['depends_on'] !== null) {
                $newDeps = $data['depends_on'];
                if (is_string($newDeps)) {
                    $newDeps = json_decode($newDeps, true);
                }
                if (is_array($newDeps)) {
                    // Self-dependency check: reject any id <= 0 (including the placeholder id
                    // of the not-yet-created task) because a new task cannot depend on itself.
                    foreach ($newDeps as $dep) {
                        if ((int)$dep <= 0) {
                            throw new Exception("Task cannot depend on itself (cycle detected)");
                        }
                    }
                    // Transitive cycle check: for each new dep, see if that dep already
                    // transitively depends on this task. Since the task is new, this only
                    // catches self-dependency through the placeholder id 0.
                    foreach ($newDeps as $dep) {
                        if ($this->wouldCreateCycle(0, (int)$dep)) {
                            throw new Exception("Circular dependency detected: task #$dep already transitively depends on the new task");
                        }
                    }
                    // Encode back to JSON string for PDO storage
                    $data['depends_on'] = json_encode($newDeps);
                }
            }
            $sql = "INSERT INTO tasks (title, description, status, assigned_to, depends_on, execution_log, retry_count, max_retries, project_id, created_by, complexity, story_url, story_id, regression_count, started_at, completed_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                $data['title'] ?? 'New Task',
                $data['description'] ?? '',
                $data['status'] ?? 'Plan',
                isset($data['assigned_to']) ? (int)$data['assigned_to'] : null,
                $data['depends_on'] ?? null,
                $data['execution_log'] ?? null,
                isset($data['retry_count']) ? (int)$data['retry_count'] : 0,
                isset($data['max_retries']) ? (int)$data['max_retries'] : 3,
                isset($data['project_id']) ? (int)$data['project_id'] : null,
                $data['created_by'] ?? 'user',
                isset($data['complexity']) ? (int)$data['complexity'] : null,
                $data['story_url'] ?? null,
                isset($data['story_id']) ? (int)$data['story_id'] : null,
                isset($data['regression_count']) ? (int)$data['regression_count'] : 0,
                $data['started_at'] ?? null,
                $data['completed_at'] ?? null,
            ]);
        } catch (\PDOException $e) {
            error_log("Database Error in create: " . $e->getMessage());
            throw new Exception("Failed to create the new task.");
        }
    }

    public function update(int $id, array $data): bool {
        try {
            // Cycle detection on depends_on update
            if (isset($data['depends_on']) && $data['depends_on'] !== null) {
                $newDeps = $data['depends_on'];
                if (is_string($newDeps)) {
                    $newDeps = json_decode($newDeps, true);
                }
                if (is_array($newDeps)) {
                    // Self-dependency check
                    foreach ($newDeps as $dep) {
                        if ((int)$dep === $id) {
                            throw new Exception("Task cannot depend on itself (cycle detected)");
                        }
                    }
                    // Transitive cycle check: for each new dep, see if that dep
                    // already transitively depends on $id (via existing deps in DB)
                    foreach ($newDeps as $dep) {
                        if ($this->wouldCreateCycle((int)$dep, $id)) {
                            throw new Exception("Circular dependency detected: task #$dep already transitively depends on task #$id");
                        }
                    }
                    // Encode back to JSON string for PDO storage
                    $data['depends_on'] = json_encode($newDeps);
                }
            }
            $stmt = $this->pdo->prepare(
                "UPDATE tasks SET title=?, description=?, status=?, assigned_to=?, depends_on=?, execution_log=?, retry_count=?, max_retries=?, complexity=?, story_url=?, story_id=?, regression_count=?, started_at=?, completed_at=? WHERE id=?"
            );
            // Fetch current task to use existing values for fields not provided
            $current = $this->findById($id);
            $newStatus = $data['status'] ?? ($current['status'] ?? 'Plan');
            $oldStatus = $current['status'] ?? '';
            // Auto-set completed_at when moving to Done, clear when moving out
            if (!isset($data['completed_at'])) {
                if ($newStatus === 'Done' && $oldStatus !== 'Done') {
                    $data['completed_at'] = date('Y-m-d H:i:s');
                } elseif ($newStatus !== 'Done' && $oldStatus === 'Done') {
                    $data['completed_at'] = null;
                }
            }
            return $stmt->execute([
                $data['title'] ?? ($current['title'] ?? 'New Task'),
                $data['description'] ?? ($current['description'] ?? ''),
                $data['status'] ?? ($current['status'] ?? 'Plan'),
                isset($data['assigned_to']) ? (int)$data['assigned_to'] : ($current['assigned_to'] ?? null),
                $data['depends_on'] ?? ($current['depends_on'] ?? null),
                $data['execution_log'] ?? ($current['execution_log'] ?? null),
                isset($data['retry_count']) ? (int)$data['retry_count'] : (int)($current['retry_count'] ?? 0),
                isset($data['max_retries']) ? (int)$data['max_retries'] : (int)($current['max_retries'] ?? 3),
                isset($data['complexity']) ? (int)$data['complexity'] : (isset($current['complexity']) ? (int)$current['complexity'] : null),
                $data['story_url'] ?? ($current['story_url'] ?? null),
                isset($data['story_id']) ? (int)$data['story_id'] : (isset($current['story_id']) ? (int)$current['story_id'] : null),
                isset($data['regression_count']) ? (int)$data['regression_count'] : (int)($current['regression_count'] ?? 0),
                $data['started_at'] ?? ($current['started_at'] ?? null),
                $data['completed_at'] ?? ($current['completed_at'] ?? null),
                $id
            ]);
        } catch (\PDOException $e) {
            error_log("Database Error in update($id): " . $e->getMessage());
            throw new Exception("Failed to update task details.");
        }
    }

    public function delete(int $id): bool {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM tasks WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (\PDOException $e) {
            error_log("Database Error in delete($id): " . $e->getMessage());
            throw new Exception("Failed to delete task.");
        }
    }

    /**
     * Detect if adding a depends_on link from $taskId to $depId creates a cycle.
     * Uses DFS from $taskId to see if we can reach $depId (which would be a cycle
     * since $taskId -> $depId means $taskId depends on $depId, so $depId can't
     * also depend on $taskId transitively).
     */
    public function wouldCreateCycle(int $taskId, int $depId): bool {
        if ($taskId === $depId) return true;
        $visited = [];
        $stack = [$taskId];
        while (!empty($stack)) {
            $current = array_pop($stack);
            $currentTask = $this->findById($current);
            if (!$currentTask) continue;
            $deps = $currentTask['depends_on'] ?? null;
            if (!$deps) continue;
            $depIds = json_decode($deps, true);
            if (!is_array($depIds)) continue;
            foreach ($depIds as $did) {
                if ((int)$did === $depId) return true;
                if (!in_array($did, $visited, true)) {
                    $visited[] = $did;
                    $stack[] = $did;
                }
            }
        }
        return false;
    }

    /**
     * Append a log entry to a task's execution_log with timestamp.
     */
    public function appendLog(int $id, string $message): bool {
        try {
            $task = $this->findById($id);
            if (!$task) throw new Exception("Task not found");
            $existing = $task['execution_log'] ?? null;
            $logs = $existing ? json_decode($existing, true) : [];
            if (!is_array($logs)) $logs = [];
            $logs[] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'message' => $message,
            ];
            $stmt = $this->pdo->prepare("UPDATE tasks SET execution_log=? WHERE id=?");
            return $stmt->execute([json_encode($logs), $id]);
        } catch (\PDOException $e) {
            error_log("Database Error in appendLog($id): " . $e->getMessage());
            throw new Exception("Failed to append log.");
        }
    }

    /**
     * Retry a task: reset status to 'In Progress', increment retry_count,
     * append a log entry.
     */
    public function retry(int $id): bool {
        try {
            $task = $this->findById($id);
            if (!$task) throw new Exception("Task not found");
            $currentRetries = (int)($task['retry_count'] ?? 0);
            $maxRetries = (int)($task['max_retries'] ?? 3);
            if ($currentRetries >= $maxRetries) {
                throw new Exception("Max retries ($maxRetries) reached for task $id");
            }
            $this->appendLog($id, "Retry attempt " . ($currentRetries + 1) . " of $maxRetries");
            $stmt = $this->pdo->prepare(
                "UPDATE tasks SET status='Code', retry_count=retry_count+1 WHERE id=?"
            );
            return $stmt->execute([$id]);
        } catch (\PDOException $e) {
            error_log("Database Error in retry($id): " . $e->getMessage());
            throw new Exception("Failed to retry task.");
        }
    }

    public function getOverdueTasks(): array {
        try {
            $sql = "SELECT * FROM tasks 
                    WHERE status NOT IN ('Plan', 'Done') 
                    AND updated_at < DATE_SUB(NOW(), INTERVAL 1 DAY)";
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log("Database Error in getOverdueTasks: " . $e->getMessage());
            return []; 
        }
    }

    /**
     * Append a structured log entry to a task's execution_log with timestamp.
     * Supports entry types: dispatched, file_created, test_result, error, verified.
     *
     * @param int    $id     Task ID
     * @param string $event  Event type (dispatched|file_created|test_result|error|verified|message)
     * @param array  $data   Event-specific data fields
     * @return bool
     */
    public function appendStructuredLog(int $id, string $event, array $data = []): bool
    {
        try {
            $task = $this->findById($id);
            if (!$task) throw new Exception("Task not found");
            $existing = $task['execution_log'] ?? null;
            $logs = $existing ? json_decode($existing, true) : [];
            if (!is_array($logs)) $logs = [];

            $entry = array_merge([
                'event'     => $event,
                'timestamp' => date('Y-m-d H:i:s'),
            ], $data);

            $logs[] = $entry;
            $stmt = $this->pdo->prepare("UPDATE tasks SET execution_log=? WHERE id=?");
            return $stmt->execute([json_encode($logs), $id]);
        } catch (\PDOException $e) {
            error_log("Database Error in appendStructuredLog($id): " . $e->getMessage());
            throw new Exception("Failed to append structured log.");
        }
    }

    /**
     * Get aggregate statistics for reports.
     * Returns avg_complexity, avg_regressions, regressed_count, avg_duration, total_stories.
     */
    public function getStats(?int $projectId = null): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT 
                    AVG(complexity) AS avg_complexity,
                    AVG(regression_count) AS avg_regressions,
                    SUM(CASE WHEN regression_count > 0 THEN 1 ELSE 0 END) AS regressed_count,
                    COUNT(*) AS total_tasks
                 FROM tasks 
                 WHERE 1=1" . ($projectId ? " AND project_id = ?" : "")
            );
            $params = $projectId ? [$projectId] : [];
            $stmt->execute($params);
            $row = $stmt->fetch();
            $totalStories = 0;
            if ($projectId) {
                $sStmt = $this->pdo->prepare("SELECT COUNT(*) FROM stories WHERE project_id = ?");
                $sStmt->execute([$projectId]);
                $totalStories = (int)$sStmt->fetchColumn();
            }
            if (!$row) {
                return [
                    'avg_complexity' => 0,
                    'avg_regressions' => 0,
                    'regressed_count' => 0,
                    'avg_duration' => '—',
                    'total_stories' => $totalStories,
                ];
            }
            // Calculate avg duration from completed tasks
            $durStmt = $this->pdo->prepare(
                "SELECT AVG(TIMESTAMPDIFF(HOUR, started_at, completed_at)) AS avg_hours
                 FROM tasks
                 WHERE completed_at IS NOT NULL AND started_at IS NOT NULL" .
                 ($projectId ? " AND project_id = ?" : "")
            );
            $durParams = $projectId ? [$projectId] : [];
            $durStmt->execute($durParams);
            $durRow = $durStmt->fetch();
            $avgHours = $durRow && $durRow['avg_hours'] ? round($durRow['avg_hours'], 1) : null;
            $avgDuration = $avgHours !== null
                ? ($avgHours >= 24 ? round($avgHours / 24, 1) . 'd' : $avgHours . 'h')
                : '—';

            return [
                'avg_complexity' => $row['avg_complexity'] ? round((float)$row['avg_complexity'], 1) : 0,
                'avg_regressions' => $row['avg_regressions'] ? round((float)$row['avg_regressions'], 1) : 0,
                'regressed_count' => (int)($row['regressed_count'] ?? 0),
                'avg_duration' => $avgDuration,
                'total_stories' => $totalStories,
            ];
        } catch (\PDOException $e) {
            error_log("Database Error in getStats: " . $e->getMessage());
            return [
                'avg_complexity' => 0,
                'avg_regressions' => 0,
                'regressed_count' => 0,
                'avg_duration' => '—',
                'total_stories' => 0,
            ];
        }
    }

}
