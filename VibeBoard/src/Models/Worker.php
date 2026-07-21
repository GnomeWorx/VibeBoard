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
        $sql = "SELECT * FROM workers w";
        $params = [];
        if ($projectId !== null) {
            $sql .= " WHERE w.project_id = ?";
            $params[] = $projectId;
        }
        $sql .= " ORDER BY w.name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findActive(?int $projectId = null): array {
        $sql = "SELECT * FROM workers w WHERE w.status = 'busy'";
        $params = [];
        if ($projectId !== null) {
            $sql .= " AND w.project_id = ?";
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

    public function updateStatus(int $id, string $status): bool {
        $stmt = $this->pdo->prepare("UPDATE workers SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    public function assign(int $workerId, int $taskId): bool {
        $stmt = $this->pdo->prepare("UPDATE tasks SET assigned_to = ? WHERE id = ?");
        $ok = $stmt->execute([$workerId, $taskId]);
        $this->updateStatus($workerId, 'busy');
        return $ok;
    }
}