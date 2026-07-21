<?php
/**
 * VibeBoard — Front Controller / Entry Point
 *
 * Bootstraps the application: autoloader, config, DB, routing.
 * Handles API endpoints for the dashboard and serves static assets.
 */

declare(strict_types=1);

use VibeBoard\Core\ErrorHandler;
use VibeBoard\Config\Config;
use VibeBoard\Router\Router;
use VibeBoard\Models\Task;
use VibeBoard\Models\Worker;
use VibeBoard\Models\Project;
use VibeBoard\Services\Metrics;

// ── Autoloader (PSR-4 style, no Composer dependency) ──────────────────────
spl_autoload_register(function (string $class): void {
    $prefix = 'VibeBoard\\';
    $base_dir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// ── Error handling ───────────────────────────────────────────────────────
set_exception_handler(function (Throwable $e) {
    ErrorHandler::handle($e);
});
set_error_handler(function (int $severity, string $message, string $file, int $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// ── Config (lives outside src/ — load directly) ─────────────────────────
/** @var VibeBoard\Config\Config $cfgLoaded */
$cfgLoaded = require_once __DIR__ . '/../config/config.php';

// ── Database ──────────────────────────────────────────────────────────────
try {
    $dbConfig = Config::get('db');
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $dbConfig['host'] ?? 'localhost',
        $dbConfig['port'] ?? 3306,
        $dbConfig['dbname'] ?? 'vibeboard_db',
        $dbConfig['charset'] ?? 'utf8mb4'
    );
    $pdo = new PDO($dsn, $dbConfig['user'] ?? 'root', $dbConfig['pass'] ?? '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (Throwable $e) {
    error_log('VibeBoard DB connection failed: ' . $e->getMessage());
    $pdo = null;
}

// ── Parse request ─────────────────────────────────────────────────────────
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$publicDir = __DIR__;

// ── JSON response helper ─────────────────────────────────────────────────
function jsonResponse(mixed $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Project scoping helper ─────────────────────────────────────────────
function getProjectScope(?PDO $pdo): ?int {
    if ($pdo === null) return null;
    try {
        $project = new Project($pdo);
        return $project->getCurrentProjectId();
    } catch (Throwable) {
        return null;
    }
}

// ── Route definitions ────────────────────────────────────────────────────
$router = new Router();

$router->addRoute('GET', '/api/status', function () use ($pdo) {
    $dbOk = ($pdo !== null);
    if ($dbOk) {
        try {
            $pdo->query('SELECT 1');
        } catch (Throwable) {
            $dbOk = false;
        }
    }
    jsonResponse([
        'status'  => $dbOk ? 'online' : 'degraded',
        'version' => '1.0.0',
        'db'      => $dbOk ? 'connected' : 'unavailable',
    ]);
});

$router->addRoute('GET', '/api/metrics', function () use ($pdo) {
    if (!$pdo) {
        jsonResponse(['error' => 'Database unavailable'], 503);
    }
    try {
        $pid = getProjectScope($pdo);
        $metrics = new Metrics($pdo, $pid);
        jsonResponse([
            'progressPercentage' => $metrics->getProgressPercentage(),
            'breakdown'    => $metrics->getStatusBreakdown(),
        ]);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Metrics');
    }
});

$router->addRoute('GET', '/api/tasks', function () use ($pdo) {
    if (!$pdo) {
        jsonResponse(['error' => 'Database unavailable'], 503);
    }
    try {
        $pid = getProjectScope($pdo);
        $taskModel = new Task($pdo);
        jsonResponse($taskModel->findAll($pid));
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Tasks');
    }
});

$router->addRoute('GET', '/api/tasks/overdue', function () use ($pdo) {
    if (!$pdo) {
        jsonResponse(['error' => 'Database unavailable'], 503);
    }
    try {
        $taskModel = new Task($pdo);
        jsonResponse($taskModel->getOverdueTasks());
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Tasks');
    }
});

$router->addRoute('POST', '/api/tasks', function () use ($pdo) {
    if (!$pdo) {
        jsonResponse(['error' => 'Database unavailable'], 503);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    try {
        // ── Ensure project_id is always set ─────────────────────────────
        if (!isset($input['project_id']) || !$input['project_id']) {
            try {
                $proj = (new VibeBoard\Models\Project($pdo))->getCurrentProjectId();
                if ($proj) $input['project_id'] = $proj;
            } catch (Throwable) {}
        }

        // ── Auto-assignment by title content ────────────────────────
        if (!isset($input['assigned_to']) || !$input['assigned_to']) {
            $title = strtolower($input['title'] ?? '');
            $assign = null;
            $autoStage = $input['status'] ?? null;

            if (str_contains($title, 'test')) {
                $assign = 4;  // Dave (QA)
                $autoStage = $autoStage ?: 'Test';
            } elseif (str_contains($title, 'documentation') || str_contains($title, 'spec') || str_contains($title, 'story')) {
                $assign = 8;  // Carl (BA)
                $autoStage = $autoStage ?: 'Spec';
            } elseif (str_contains($title, 'assess')) {
                // Assess — find any idle worker
                $wm = new VibeBoard\Models\Worker($pdo);
                $idle = array_filter($wm->findAll(getProjectScope($pdo)), fn($w) => $w['status'] === 'idle');
                $assign = $idle ? (int)$idle[array_key_first($idle)]['id'] : 1;
                $autoStage = $autoStage ?: 'Assess';
            } elseif (str_contains($title, 'review') || str_contains($title, 'qa')) {
                $assign = 4;  // Dave does QA review
                $autoStage = $autoStage ?: 'Review';
            } elseif (str_contains($title, 'plan')) {
                $assign = 1;  // Kevin — planning
                $autoStage = $autoStage ?: 'Plan';
            } else {
                // Development — round-robin among dev workers (Kevin=1, Bob=3, Jerry=5)
                $wm = new VibeBoard\Models\Worker($pdo);
                $devs = [1, 3, 5];
                $all = $wm->findAll(getProjectScope($pdo));
                // Pick the dev with fewest active tasks
                $loads = [];
                foreach ($devs as $did) $loads[$did] = 0;
                $ts = (new VibeBoard\Models\Task($pdo))->findAll(getProjectScope($pdo));
                foreach ($ts as $t) {
                    $wid = (int)($t['assigned_to'] ?? 0);
                    if (in_array($wid, $devs) && $t['status'] !== 'Done') $loads[$wid]++;
                }
                asort($loads);
                $assign = (int)array_key_first($loads);
                $autoStage = $autoStage ?: 'Code';
            }

            $input['assigned_to'] = $assign;
            $input['status'] = $autoStage;

            // Record auto-assignment in execution_log
            $log = json_decode($input['execution_log'] ?? '[]', true) ?: [];
            $log[] = ['timestamp' => date('c'), 'message' => 'auto-assigned to worker #' . $assign . ' (stage: ' . $autoStage . ')'];
            $input['execution_log'] = json_encode($log);
        }

        $taskModel = new Task($pdo);
        $ok = $taskModel->create($input);
        jsonResponse(['success' => $ok], $ok ? 201 : 500);
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'cycle') !== false || stripos($msg, 'depend') !== false) {
            jsonResponse(['error' => $msg], 400);
        } else {
            ErrorHandler::handle($e, 'Tasks');
        }
    }
});

$router->addRoute('PUT', '/api/tasks/{id}', function () use ($pdo) {
    if (!$pdo) {
        jsonResponse(['error' => 'Database unavailable'], 503);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($_REQUEST['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['error' => 'Valid task id required'], 400);
    }
    try {
        $taskModel = new Task($pdo);
        $ok = $taskModel->update($id, $input);
        jsonResponse(['success' => $ok]);
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'cycle') !== false || stripos($msg, 'depend') !== false) {
            jsonResponse(['error' => $msg], 400);
        } else {
            ErrorHandler::handle($e, 'Tasks');
        }
    }
});

// ── Parameterised route: /api/tasks/{id} ──────────────────────────────
$router->addRoute('GET', '/api/tasks/{id}', function () use ($pdo) {
    if (!$pdo) {
        jsonResponse(['error' => 'Database unavailable'], 503);
    }
    $id = (int)($_REQUEST['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['error' => 'Valid task id required'], 400);
    }
    try {
        $taskModel = new Task($pdo);
        $task = $taskModel->findById($id);
        if (!$task) {
            jsonResponse(['error' => 'Task not found'], 404);
        }
        jsonResponse($task);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Tasks');
    }
});

// ── Batch-update: POST /api/tasks/batch-update ────────────────────────
$router->addRoute('POST', '/api/tasks/batch-update', function () use ($pdo) {
    if (!$pdo) {
        jsonResponse(['error' => 'Database unavailable'], 503);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $ids = $input['ids'] ?? [];
    $data = $input['data'] ?? [];
    if (!is_array($ids) || empty($ids) || empty($data)) {
        jsonResponse(['error' => 'ids (array) and data (object) required'], 400);
    }
    $ids = array_unique(array_map('intval', $ids));
    try {
        $taskModel = new Task($pdo);
        $count = $taskModel->batchUpdate($ids, $data);
        jsonResponse(['success' => true, 'updated' => $count]);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Tasks');
    }
});

$router->addRoute('PUT', '/api/tasks/{id}', function () use ($pdo) {
    if (!$pdo) {
        jsonResponse(['error' => 'Database unavailable'], 503);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($_REQUEST['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['error' => 'Valid task id required'], 400);
    }
    try {
        $taskModel = new Task($pdo);
        $ok = $taskModel->update($id, $input);
        jsonResponse(['success' => $ok]);
    } catch (Throwable $e) {
        // Return cycle/dependency errors as proper JSON instead of generic message
        $msg = $e->getMessage();
        if (stripos($msg, 'cycle') !== false || stripos($msg, 'depend') !== false) {
            jsonResponse(['error' => $msg], 400);
        } else {
            ErrorHandler::handle($e, 'Tasks');
        }
    }
});

$router->addRoute('DELETE', '/api/tasks/{id}', function () use ($pdo) {
    if (!$pdo) {
        jsonResponse(['error' => 'Database unavailable'], 503);
    }
    $id = (int)($_REQUEST['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['error' => 'Valid task id required'], 400);
    }
    try {
        $taskModel = new Task($pdo);
        $ok = $taskModel->delete($id);
        jsonResponse(['success' => $ok]);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Tasks');
    }
});

// ── Parameterised route: /api/tasks/{id}/retry ────────────────────────
$router->addRoute('POST', '/api/tasks/{id}/retry', function () use ($pdo) {
    if (!$pdo) jsonResponse(['error' => 'Database unavailable'], 503);
    $id = (int)($_REQUEST['id'] ?? 0);
    if ($id <= 0) jsonResponse(['error' => 'Valid task id required'], 400);
    try {
        $taskModel = new Task($pdo);
        $ok = $taskModel->retry($id);
        jsonResponse(['success' => $ok]);
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'retry') !== false || stripos($msg, 'max') !== false || stripos($msg, 'not found') !== false) {
            jsonResponse(['error' => $msg], 400);
        } else {
            ErrorHandler::handle($e, 'Tasks');
        }
    }
});


// ── Parameterised route: /api/tasks/{id}/log ─────────────────────────
$router->addRoute("POST", "/api/tasks/{id}/log", function () use ($pdo) {
    if (!$pdo) jsonResponse(["error" => "Database unavailable"], 503);
    $id = (int)($_REQUEST["id"] ?? 0);
    if ($id <= 0) jsonResponse(["error" => "Valid task id required"], 400);
    $input = json_decode(file_get_contents("php://input"), true) ?? [];
    $event = $input["event"] ?? "message";
    unset($input["event"]);
    try {
        $taskModel = new Task($pdo);
        $ok = $taskModel->appendStructuredLog($id, $event, $input);
        jsonResponse(["success" => $ok]);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, "Tasks");
    }
});


// ── Parameterised route: /api/tasks/{id}/cycle-check ──────────────────
$router->addRoute('POST', '/api/tasks/{id}/cycle-check', function () use ($pdo) {
    if (!$pdo) jsonResponse(['error' => 'Database unavailable'], 503);
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $taskId = (int)($_REQUEST['id'] ?? 0);
    $depId = (int)($input['depends_on'] ?? 0);
    if ($taskId <= 0 || $depId <= 0) {
        jsonResponse(['cycle' => false]);
        return;
    }
    try {
        $taskModel = new Task($pdo);
        $cycle = $taskModel->wouldCreateCycle($taskId, $depId);
        jsonResponse(['cycle' => $cycle]);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Tasks');
    }
});

// ── Worker routes ──────────────────────────────────────────────────────
$router->addRoute('GET', '/api/workers', function () use ($pdo) {
    if (!$pdo) {
        jsonResponse(['error' => 'Database unavailable'], 503);
    }
    try {
        $pid = getProjectScope($pdo);
        $workerModel = new Worker($pdo);
        jsonResponse($workerModel->findAll($pid));
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Workers');
    }
});

$router->addRoute('GET', '/api/workers/active', function () use ($pdo) {
    if (!$pdo) {
        jsonResponse(['error' => 'Database unavailable'], 503);
    }
    try {
        $pid = getProjectScope($pdo);
        $workerModel = new Worker($pdo);
        jsonResponse($workerModel->findActive($pid));
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Workers');
    }
});

$router->addRoute('POST', '/api/workers', function () use ($pdo) {
    if (!$pdo) jsonResponse(['error' => 'Database unavailable'], 503);
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    try {
        $workerModel = new Worker($pdo);
        $id = $workerModel->create($input);
        jsonResponse(['success' => true, 'id' => $id], 201);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Workers');
    }
});

$router->addRoute('GET', '/api/workers/{id}', function () use ($pdo) {
    if (!$pdo) jsonResponse(['error' => 'Database unavailable'], 503);
    $id = (int)($_REQUEST['id'] ?? 0);
    if ($id <= 0) jsonResponse(['error' => 'Valid worker id required'], 400);
    try {
        $workerModel = new Worker($pdo);
        $worker = $workerModel->findById($id);
        if (!$worker) jsonResponse(['error' => 'Worker not found'], 404);
        jsonResponse($worker);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Workers');
    }
});

$router->addRoute('PUT', '/api/workers/{id}', function () use ($pdo) {
    if (!$pdo) jsonResponse(['error' => 'Database unavailable'], 503);
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($_REQUEST['id'] ?? 0);
    if ($id <= 0) jsonResponse(['error' => 'Valid worker id required'], 400);
    try {
        $workerModel = new Worker($pdo);
        jsonResponse(['success' => $workerModel->update($id, $input)]);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Workers');
    }
});

$router->addRoute('DELETE', '/api/workers/{id}', function () use ($pdo) {
    if (!$pdo) jsonResponse(['error' => 'Database unavailable'], 503);
    $id = (int)($_REQUEST['id'] ?? 0);
    if ($id <= 0) jsonResponse(['error' => 'Valid worker id required'], 400);
    try {
        $workerModel = new Worker($pdo);
        jsonResponse(['success' => $workerModel->delete($id)]);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Workers');
    }
});

// ── Batch worker start/stop ────────────────────────────────────
$router->addRoute('POST', '/api/workers/start', function () use ($pdo) {
    if (!$pdo) jsonResponse(['error' => 'Database unavailable'], 503);
    try {
        $workerModel = new Worker($pdo);
        $count = $workerModel->batchUpdateStatus('idle', 'busy');
        jsonResponse(['success' => true, 'updated' => $count, 'action' => 'started']);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Workers');
    }
});

$router->addRoute('POST', '/api/workers/stop', function () use ($pdo) {
    if (!$pdo) jsonResponse(['error' => 'Database unavailable'], 503);
    try {
        $workerModel = new Worker($pdo);
        $count = $workerModel->batchUpdateStatus('busy', 'idle');
        jsonResponse(['success' => true, 'updated' => $count, 'action' => 'stopped']);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Workers');
    }
});

// ── Project routes ────────────────────────────────────────────────────
$router->addRoute('GET', '/api/projects', function () use ($pdo) {
    if (!$pdo) jsonResponse(['error' => 'Database unavailable'], 503);
    try {
        $project = new Project($pdo);
        jsonResponse($project->findAll());
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Projects');
    }
});

$router->addRoute('GET', '/api/projects/current', function () use ($pdo) {
    if (!$pdo) jsonResponse(['error' => 'Database unavailable'], 503);
    try {
        $project = new Project($pdo);
        $cur = $project->getCurrent();
        if (!$cur) jsonResponse(['error' => 'No active project'], 404);
        jsonResponse($cur);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Projects');
    }
});

$router->addRoute('GET', '/api/projects/{id}', function () use ($pdo) {
    if (!$pdo) jsonResponse(['error' => 'Database unavailable'], 503);
    $id = (int)($_REQUEST['id'] ?? 0);
    if ($id <= 0) jsonResponse(['error' => 'Valid project id required'], 400);
    try {
        $project = new Project($pdo);
        $proj = $project->findById($id);
        if (!$proj) jsonResponse(['error' => 'Project not found'], 404);
        jsonResponse($proj);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Projects');
    }
});

$router->addRoute('POST', '/api/projects', function () use ($pdo) {
    if (!$pdo) jsonResponse(['error' => 'Database unavailable'], 503);
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    try {
        $project = new Project($pdo);
        $id = $project->create($input['name'] ?? 'New Project', $input['description'] ?? '');
        $project->activate($id);
        jsonResponse(['success' => true, 'id' => $id], 201);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Projects');
    }
});

$router->addRoute('PUT', '/api/projects/{id}', function () use ($pdo) {
    if (!$pdo) jsonResponse(['error' => 'Database unavailable'], 503);
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($_REQUEST['id'] ?? 0);
    if ($id <= 0) jsonResponse(['error' => 'Valid project id required'], 400);
    try {
        $project = new Project($pdo);
        jsonResponse(['success' => $project->update($id, $input)]);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Projects');
    }
});

$router->addRoute('POST', '/api/projects/{id}/activate', function () use ($pdo) {
    if (!$pdo) jsonResponse(['error' => 'Database unavailable'], 503);
    $id = (int)($_REQUEST['id'] ?? 0);
    if ($id <= 0) jsonResponse(['error' => 'Valid project id required'], 400);
    try {
        $project = new Project($pdo);
        jsonResponse(['success' => $project->activate($id), 'current' => $project->getCurrent()]);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Projects');
    }
});

$router->addRoute('POST', '/api/projects/{id}/park', function () use ($pdo) {
    if (!$pdo) jsonResponse(['error' => 'Database unavailable'], 503);
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($_REQUEST['id'] ?? 0);
    $note = $input['park_note'] ?? '';
    // Auto-generate summary from current state
    $pid = $id;
    $metrics = new Metrics($pdo, $pid);
    $pct = $metrics->getProgressPercentage();
    $taskModel = new Task($pdo);
    $tasks = $taskModel->findAll($pid);
    $inProgress = array_filter($tasks, fn($t) => $t['status'] !== 'Plan' && $t['status'] !== 'Done');
    $done = array_filter($tasks, fn($t) => $t['status'] === 'Done');
    $workerModel = new Worker($pdo);
    $workers = $workerModel->findAll($pid);
    $busy = array_filter($workers, fn($w) => $w['status'] === 'busy');
    $summaryParts = [];
    if ($done || $inProgress) {
        $summaryParts[] = count($done) . ' done, ' . count($inProgress) . ' in progress (' . round($pct) . '% complete)';
    }
    if ($busy) {
        $busyNames = array_map(fn($w) => $w['name'] . ' on "' . ($w['task_title'] ?? '?') . '"', $busy);
        $summaryParts[] = implode(', ', $busyNames);
    }
    $summary = implode('. ', $summaryParts);
    $fullNote = $note ? $summary . '. ' . $note : $summary;
    try {
        $project = new Project($pdo);
        jsonResponse(['success' => $project->park($id, $fullNote)]);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Projects');
    }
});

$router->addRoute('DELETE', '/api/projects/{id}', function () use ($pdo) {
    if (!$pdo) jsonResponse(['error' => 'Database unavailable'], 503);
    $id = (int)($_REQUEST['id'] ?? 0);
    if ($id <= 0) jsonResponse(['error' => 'Valid project id required'], 400);
    try {
        $project = new Project($pdo);
        $currentId = $project->getCurrentProjectId();
        $deleted = $project->delete($id);
        // If we deleted the active project, reset to the first available
        if ($currentId === $id) {
            $default = null;
            $stmt = $pdo->query("SELECT id FROM projects WHERE status = 'active' ORDER BY id LIMIT 1");
            $default = $stmt->fetchColumn();
            if ($default) {
                $project->activate((int)$default);
            } else {
                $pdo->exec("UPDATE app_settings SET `value` = '' WHERE `key` = 'current_project_id'");
            }
        }
        jsonResponse(['success' => $deleted]);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Projects');
    }
});


// ── Stories CRUD ──────────────────────────────────────────────────────
$router->addRoute('GET', '/api/stories', function () use ($pdo) {
    if (!$pdo) {
        jsonResponse(['error' => 'Database unavailable'], 503);
    }
    try {
        $pid = function_exists('getProjectScope') ? getProjectScope($pdo) : null;
        if ($pid) {
            $stmt = $pdo->prepare("SELECT * FROM stories WHERE project_id = ? ORDER BY created_at DESC");
            $stmt->execute([$pid]);
        } else {
            $stmt = $pdo->query("SELECT * FROM stories ORDER BY created_at DESC");
        }
        jsonResponse($stmt->fetchAll());
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Stories');
    }
});

$router->addRoute('POST', '/api/stories', function () use ($pdo) {
    if (!$pdo) {
        jsonResponse(['error' => 'Database unavailable'], 503);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($input['title'])) {
        jsonResponse(['error' => 'Title is required'], 400);
    }
    try {
        $pid = function_exists('getProjectScope') ? getProjectScope($pdo) : null;
        $stmt = $pdo->prepare(
            "INSERT INTO stories (project_id, title, description, story_type, github_url, status, complexity, department, issue_url, issue_number) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $ok = $stmt->execute([
            $pid,
            $input['title'],
            $input['description'] ?? '',
            $input['story_type'] ?? 'feature',
            $input['github_url'] ?? null,
            $input['status'] ?? 'Plan',
            isset($input['complexity']) ? (int)$input['complexity'] : null,
            $input['department'] ?? null,
            $input['issue_url'] ?? null,
            $input['issue_number'] ?? null,
        ]);
        jsonResponse(['success' => $ok, 'id' => $pdo->lastInsertId()], $ok ? 201 : 500);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Stories');
    }
});

$router->addRoute('GET', '/api/stories/{id}', function () use ($pdo) {
    if (!$pdo) { jsonResponse(['error' => 'Database unavailable'], 503); }
    $id = (int)($_REQUEST['id'] ?? 0);
    if ($id <= 0) { jsonResponse(['error' => 'Valid story id required'], 400); }
    try {
        $stmt = $pdo->prepare("SELECT * FROM stories WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { jsonResponse(['error' => 'Story not found'], 404); }
        jsonResponse($row);
    } catch (Throwable $e) { ErrorHandler::handle($e, 'Stories'); }
});

$router->addRoute('PUT', '/api/stories/{id}', function () use ($pdo) {
    if (!$pdo) { jsonResponse(['error' => 'Database unavailable'], 503); }
    $id = (int)($_REQUEST['id'] ?? 0);
    if ($id <= 0) { jsonResponse(['error' => 'Valid story id required'], 400); }
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM stories WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetch();
        if (!$current) { jsonResponse(['error' => 'Story not found'], 404); }
        $upd = $pdo->prepare(
            "UPDATE stories SET title=?, description=?, story_type=?, github_url=?, status=?, complexity=?, department=?, issue_url=?, issue_number=? WHERE id=?"
        );
        $ok = $upd->execute([
            $input['title'] ?? $current['title'],
            $input['description'] ?? $current['description'],
            $input['story_type'] ?? $current['story_type'],
            $input['github_url'] ?? $current['github_url'],
            $input['status'] ?? $current['status'],
            isset($input['complexity']) ? (int)$input['complexity'] : $current['complexity'],
            $input['department'] ?? $current['department'],
            $input['issue_url'] ?? $current['issue_url'],
            $input['issue_number'] ?? $current['issue_number'],
            $id
        ]);
        jsonResponse(['success' => $ok]);
    } catch (Throwable $e) { ErrorHandler::handle($e, 'Stories'); }
});

$router->addRoute('DELETE', '/api/stories/{id}', function () use ($pdo) {
    if (!$pdo) { jsonResponse(['error' => 'Database unavailable'], 503); }
    $id = (int)($_REQUEST['id'] ?? 0);
    if ($id <= 0) { jsonResponse(['error' => 'Valid story id required'], 400); }
    try {
        $stmt = $pdo->prepare("DELETE FROM stories WHERE id = ?");
        jsonResponse(['success' => $stmt->execute([$id])]);
    } catch (Throwable $e) { ErrorHandler::handle($e, 'Stories'); }
});

// ── GitHub Import ──────────────────────────────────────────────────
$router->addRoute('GET', '/api/stories/import', function () use ($pdo) {
    if (!$pdo) { jsonResponse(['error' => 'Database unavailable'], 503); }
    $repo = $_GET['repo'] ?? '';
    if (!$repo) { jsonResponse(['error' => 'repo query parameter required'], 400); }
    try {
        $parts = parse_url($repo);
        $path = trim($parts['path'] ?? $repo, '/');
        $path = preg_replace('/\.git$/', '', $path);
        $apiUrl = "https://api.github.com/repos/$path/issues?state=all&per_page=50";
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: VibeBoard\r\nAccept: application/vnd.github.v3+json\r\n",
                'timeout' => 10,
            ],
        ]);
        $response = @file_get_contents($apiUrl, false, $context);
        if ($response === false) {
            jsonResponse(['imported' => 0, 'message' => 'Could not reach GitHub API. Check the repo URL and network access.']);
            return;
        }
        $issues = json_decode($response, true);
        if (!is_array($issues)) {
            jsonResponse(['imported' => 0, 'message' => 'Invalid response from GitHub API.']);
            return;
        }
        $pid = function_exists('getProjectScope') ? getProjectScope($pdo) : null;
        $imported = 0;
        $stmt = $pdo->prepare(
            "INSERT INTO stories (project_id, title, description, story_type, github_url, status, issue_url, issue_number, complexity) 
             VALUES (?, ?, ?, 'github', ?, 'Plan', ?, ?, 3)"
        );
        foreach ($issues as $issue) {
            if (isset($issue['pull_request'])) continue;
            $stmt->execute([
                $pid,
                $issue['title'] ?? 'Untitled',
                $issue['body'] ?? '',
                $issue['html_url'] ?? $repo . '/issues/' . ($issue['number'] ?? ''),
                $issue['html_url'] ?? '',
                (string)($issue['number'] ?? ''),
            ]);
            $imported++;
        }
        jsonResponse(['imported' => $imported, 'message' => "Imported $imported GitHub issues."]);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Stories');
    }
});

// ── Reports ───────────────────────────────────────────────────────────
$router->addRoute('GET', '/api/reports/stats', function () use ($pdo) {
    if (!$pdo) { jsonResponse(['error' => 'Database unavailable'], 503); }
    try {
        $pid = function_exists('getProjectScope') ? getProjectScope($pdo) : null;
        $taskModel = new \VibeBoard\Models\Task($pdo);
        $stats = $taskModel->getStats($pid);
        jsonResponse(['stats' => $stats]);
    } catch (Throwable $e) {
        ErrorHandler::handle($e, 'Reports');
    }
});

// ── Static file serving ──────────────────────────────────────────────────
if (str_starts_with($uri, '/css/') || str_starts_with($uri, '/js/') || preg_match('/\.(md|html)$/', $uri)) {
    $filePath = $publicDir . $uri;
    if (file_exists($filePath) && !is_dir($filePath)) {
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $mime = match ($ext) {
            'css' => 'text/css',
            'js'  => 'application/javascript',
            default => 'text/plain',
        };
        header("Content-Type: $mime");
        readfile($filePath);
        exit;
    }
    http_response_code(404);
    echo 'Not Found';
    exit;
}

// ── API routes (delegated to router) ──────────────────────────────────────
if (str_starts_with($uri, '/api/')) {
    $router->resolve();
    exit;
}

// ── Default: serve the dashboard HTML ─────────────────────────────────────
$indexHtml = $publicDir . '/index.html';
if (file_exists($indexHtml)) {
    readfile($indexHtml);
} else {
    http_response_code(404);
    echo 'Dashboard not found.';
}
