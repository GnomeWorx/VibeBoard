<?php

namespace VibeBoard\Models;

use PDO;
use Exception;

class Worker {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function findAll(?int $projectId = null): array {
        $sql = "SELECT w.*, t.id AS task_id, t.title AS task_title, t.status AS task_status
                FROM workers w
                LEFT JOIN tasks t ON t.id = (
                    SELECT t2.id FROM tasks t2
                    WHERE t2.assigned_to = w.id
                    ORDER BY CASE t2.status WHEN 'Done' THEN 1 ELSE 0 END ASC, t2.updated_at DESC
                    LIMIT 1
                )";
        $params = [];
        if ($projectId !== null) {
            $sql = "SELECT w.*, t.id AS task_id, t.title AS task_title, t.status AS task_status
                    FROM workers w
                    LEFT JOIN tasks t ON t.id = (
                        SELECT t2.id FROM tasks t2
                        WHERE t2.assigned_to = w.id AND t2.project_id = ?
                        ORDER BY CASE t2.status WHEN 'Done' THEN 1 ELSE 0 END ASC, t2.updated_at DESC
                        LIMIT 1
                    )";
            $params[] = $projectId;
        }
        $sql .= " ORDER BY w.name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findActive(?int $projectId = null): array {
        $sql = "SELECT w.*, t.id AS task_id, t.title AS task_title, t.status AS task_status
                FROM workers w
                LEFT JOIN tasks t ON t.id = (
                    SELECT t2.id FROM tasks t2
                    WHERE t2.assigned_to = w.id AND t2.status != 'Done'
                    ORDER BY t2.updated_at DESC
                    LIMIT 1
                )
                WHERE w.status = 'busy' OR t.id IS NOT NULL";
        $params = [];
        if ($projectId !== null) {
            $sql = "SELECT w.*, t.id AS task_id, t.title AS task_title, t.status AS task_status
                    FROM workers w
                    LEFT JOIN tasks t ON t.id = (
                        SELECT t2.id FROM tasks t2
                        WHERE t2.assigned_to = w.id AND t2.project_id = ? AND t2.status != 'Done'
                        ORDER BY t2.updated_at DESC
                        LIMIT 1
                    )
                    WHERE w.status = 'busy' OR t.id IS NOT NULL";
            $params[] = $projectId;
        }
        $sql .= " ORDER BY w.name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM workers WHERE id = ?");
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public function updateStatus(int $id, string $status): bool {
        $stmt = $this->pdo->prepare("UPDATE workers SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    public function create(array $data): int {
        $stmt = $this->pdo->prepare("INSERT INTO workers (name, role, status, model, provider, toolset) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['name'] ?? '',
            $data['role'] ?? 'Developer',
            $data['status'] ?? 'idle',
            $data['model'] ?? null,
            $data['provider'] ?? null,
            $data['toolset'] ?? null
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [];
        
        if (isset($data['name'])) {
            $fields[] = "name = ?";
            $params[] = $data['name'];
        }
        if (isset($data['role'])) {
            $fields[] = "role = ?";
            $params[] = $data['role'];
        }
        if (isset($data['status'])) {
            $fields[] = "status = ?";
            $params[] = $data['status'];
        }
        if (array_key_exists('model', $data)) {
            $fields[] = "model = ?";
            $params[] = $data['model'];
        }
        if (array_key_exists('provider', $data)) {
            $fields[] = "provider = ?";
            $params[] = $data['provider'];
        }
        if (array_key_exists('toolset', $data)) {
            $fields[] = "toolset = ?";
            $params[] = $data['toolset'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE workers SET " . implode(', ', $fields) . " WHERE id = ?";
        $params[] = $id;
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM workers WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
