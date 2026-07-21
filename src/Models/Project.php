<?php

namespace VibeBoard\Models;

use PDO;

class Project {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function findAll(): array {
        $stmt = $this->pdo->query(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) AS task_count,
                    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'Done') AS done_count
             FROM projects p
             ORDER BY FIELD(p.status, 'active', 'parked', 'archived'), p.updated_at DESC"
        );
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) AS task_count,
                    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'Done') AS done_count
             FROM projects p WHERE p.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getCurrentProjectId(): int {
        $stmt = $this->pdo->query("SELECT `value` FROM app_settings WHERE `key` = 'current_project_id'");
        $row = $stmt->fetch();
        return $row ? (int)$row['value'] : 1;
    }

    public function getCurrent(): ?array {
        return $this->findById($this->getCurrentProjectId());
    }

    public function activate(int $id): bool {
        // Update active project status
        $st = $this->pdo->prepare("UPDATE projects SET status = 'active' WHERE id = ?");
        $st->execute([$id]);
        // Set current project ID in settings
        $stmt = $this->pdo->prepare("UPDATE app_settings SET `value` = ? WHERE `key` = 'current_project_id'");
        return $stmt->execute([(string)$id]);
    }

    public function park(int $id, string $note = ''): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE projects SET status = 'parked', park_note = ?, parked_at = NOW() WHERE id = ?"
        );
        $ok = $stmt->execute([$note, $id]);
        if (!$ok) return false;

        // If this was the current project, auto-switch to another active one
        if ($this->getCurrentProjectId() === $id) {
            $next = $this->pdo->query(
                "SELECT id FROM projects WHERE status = 'active' AND id != $id ORDER BY updated_at DESC LIMIT 1"
            )->fetchColumn();
            if (!$next) {
                $next = $this->pdo->query(
                    "SELECT id FROM projects WHERE status != 'archived' AND id != $id ORDER BY updated_at DESC LIMIT 1"
                )->fetchColumn();
            }
            if ($next) {
                $this->pdo->prepare("UPDATE app_settings SET `value` = ? WHERE `key` = 'current_project_id'")
                          ->execute([(string)$next]);
                $this->pdo->prepare("UPDATE projects SET status = 'active' WHERE id = ?")->execute([$next]);
            }
        }
        return true;
    }

    public function create(string $name, string $description = ''): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO projects (name, description, status) VALUES (?, ?, 'active')"
        );
        $stmt->execute([$name, $description]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $sets = [];
        $params = [];
        foreach (['name', 'description', 'status'] as $field) {
            if (isset($data[$field])) {
                $sets[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        if (empty($sets)) return false;
        $params[] = $id;
        $stmt = $this->pdo->prepare("UPDATE projects SET " . implode(', ', $sets) . " WHERE id = ?");
        return $stmt->execute($params);
    }

    public function delete(int $id): bool {
        // If this was the current project, auto-switch to another
        $wasCurrent = $this->getCurrentProjectId() === $id;
        // Unlink tasks before deleting project
        $this->pdo->prepare("UPDATE tasks SET project_id = NULL WHERE project_id = ?")->execute([$id]);
        $stmt = $this->pdo->prepare("DELETE FROM projects WHERE id = ?");
        $ok = $stmt->execute([$id]);
        if ($ok && $wasCurrent) {
            $next = $this->pdo->query(
                "SELECT id FROM projects WHERE status != 'archived' ORDER BY updated_at DESC LIMIT 1"
            )->fetchColumn();
            if ($next) {
                $this->pdo->prepare("UPDATE app_settings SET `value` = ? WHERE `key` = 'current_project_id'")
                          ->execute([(string)$next]);
                $this->pdo->prepare("UPDATE projects SET status = 'active' WHERE id = ?")->execute([$next]);
            }
        }
        return $ok;
    }
}