# VibeBoard — Full Specification v2.0

> Updated: 2026-07-21 — Reflects actual built state.

## 1. Overview

VibeBoard is a PHP + MariaDB project task dashboard. Lightweight, no Composer, no framework — plain PHP with a PSR-4-style autoloader. Serves a dark-theme single-page dashboard with real-time task management, multi-project support, worker/agent tracking, and swarm orchestration.

## 2. Architecture

```
public/                  Document root
  index.php              Front controller (API routes + static file serving)
  index.html             Dashboard UI (dark theme, Chart.js)
  css/style.css          Dark theme styling
  js/app.js              Dashboard JS (fetch API + Chart.js + pipeline)
config/
  config.php             Config loader (db, app settings)
  db.php                 PDO connection factory
src/
  Controllers/
    BaseController.php   Abstract controller base
  Core/
    ErrorHandler.php     Global exception/error handler
  Models/
    Task.php             Task CRUD model (CRUD, dependencies, retry, logging)
    Worker.php           Worker CRUD model (status tracking, task joins)
    Project.php          Project CRUD model (activate/park/delete)
  Router/
    Router.php           Simple route collection + dispatch
  Services/
    Metrics.php          Progress calculation + status breakdown
tests/
  bootstrap.php          Test autoloader
  CoreInfrastructureTest.php
  TaskTest.php           Task model tests
  MetricsTest.php        Metrics service tests
  ApiIntegrationTest.php 22 API endpoint integration tests
  ConfigTest.php         Config loading tests
VibeBoard/               Project meta
  README.md
  SPEC.md                This file
  PLAN.md                Implementation plan
  USER_STORIES.md        All user stories documentation
```

## 3. Database Schema

### Table: `tasks`
| Column | Type | Description |
|--------|------|-------------|
| id | INT(11) PK AUTO_INCREMENT | Unique task ID |
| title | VARCHAR(255) NOT NULL | Task title |
| description | TEXT NULL | Task description |
| status | VARCHAR(50) DEFAULT 'Backlog' | Backlog, In Progress, QA-Review, Done |
| project_id | INT(11) NULL | FK to projects |
| assigned_to | INT(11) NULL | FK to workers |
| depends_on | JSON NULL | Task dependency IDs |
| execution_log | JSON NULL | Array of execution events |
| retry_count | INT DEFAULT 0 | Current retry count |
| max_retries | INT DEFAULT 3 | Max retries before failure |
| complexity | INT NULL | Task complexity score |
| story_url | VARCHAR(255) NULL | Link to user story |
| story_id | VARCHAR(50) NULL | Story identifier |
| regression_count | INT DEFAULT 0 | Regression test count |
| created_at | TIMESTAMP | Auto-set on creation |
| updated_at | TIMESTAMP | Auto-updated on change |

### Table: `workers`
| Column | Type | Description |
|--------|------|-------------|
| id | INT(11) PK AUTO_INCREMENT | Unique worker ID |
| name | VARCHAR(100) | Worker display name |
| role | VARCHAR(50) | Role (Senior Dev, Frontend, Backend, QA, BA, Full Stack) |
| status | VARCHAR(20) DEFAULT 'idle' | idle, busy, offline |
| model | VARCHAR(100) NULL | AI model they use |
| provider | VARCHAR(50) NULL | Model provider |
| toolset | VARCHAR(255) NULL | Available tools |
| created_at | TIMESTAMP | Auto-set |

### Table: `projects`
| Column | Type | Description |
|--------|------|-------------|
| id | INT(11) PK AUTO_INCREMENT | Unique project ID |
| name | VARCHAR(255) NOT NULL | Project name |
| description | TEXT NULL | Project description |
| status | VARCHAR(20) DEFAULT 'active' | active, parked, archived |
| park_note | TEXT NULL | Context note when parked |
| parked_at | TIMESTAMP NULL | When project was parked |
| created_at | TIMESTAMP | Auto-set |
| updated_at | TIMESTAMP | Auto-updated |

### Table: `app_settings`
| Column | Type | Description |
|--------|------|-------------|
| key | VARCHAR(100) PK | Setting key |
| value | TEXT | Setting value |

Key setting: `current_project_id` = the active project ID (default: 1)

## 4. API Endpoints

### System
| Method | Path | Description |
|--------|------|-------------|
| GET | /api/status | Health check (DB + app version) |

### Projects
| Method | Path | Description |
|--------|------|-------------|
| GET | /api/projects | List all projects with task counts |
| GET | /api/projects/current | Get current active project |
| GET | /api/projects/{id} | Get single project by ID |
| POST | /api/projects | Create new project |
| PUT | /api/projects/{id} | Update project fields |
| DELETE | /api/projects/{id} | Delete project (unlinks tasks, auto-switches) |
| POST | /api/projects/{id}/park | Park project with context note |
| POST | /api/projects/{id}/activate | Reactivate a parked project |

### Tasks
| Method | Path | Description |
|--------|------|-------------|
| GET | /api/tasks | List all tasks (optionally filtered by project_id) |
| GET | /api/tasks/{id} | Get single task with worker info |
| GET | /api/tasks/overdue | Tasks stuck >24h in progress/QA |
| POST | /api/tasks | Create task (title, description, status, assigned_to) |
| PUT | /api/tasks/{id} | Update task fields |
| DELETE | /api/tasks/{id} | Delete task |
| POST | /api/tasks/batch-update | Batch update multiple tasks |
| POST | /api/tasks/{id}/log | Append structured log entry |
| POST | /api/tasks/{id}/retry | Reset and retry a failed task |
| POST | /api/tasks/{id}/cycle-check | Check for dependency cycles |

### Metrics
| Method | Path | Description |
|--------|------|-------------|
| GET | /api/metrics | Progress % + status breakdown (scoped to current project) |

### Workers
| Method | Path | Description |
|--------|------|-------------|
| GET | /api/workers | List all workers with current task |
| GET | /api/workers/active | List busy workers with non-Done tasks |
| GET | /api/workers/{id} | Get single worker |
| PUT | /api/workers/{id} | Update worker (status, model, provider) |
| POST | /api/workers | Create new worker |
| DELETE | /api/workers/{id} | Delete worker |

All responses: `{ "success": true, ... }` or `{ "error": "message" }` with appropriate HTTP codes.

## 5. Frontend Dashboard

### Features built:
- Dark theme with CSS custom properties
- 5 stat cards: Total Tasks, Completed, In Progress, Backlog, Progress %
- Chart.js doughnut showing status distribution
- Sortable task table with ID, Title, Status badge, Created date, Actions
- Modal forms for Create/Edit task (with worker assignment dropdown)
- 4-column pipeline view: Backlog, In Progress, QA-Review, Done
- 7-stage progress bar in expanded task view (Plan-Spec-Assess-Code-Test-Review-Done)
- Worker section with current task, status, and role
- Manage Projects modal with Activate/Delete actions
- Park/Resume project workflow
- Toast notifications for success/error feedback
- Auto-refresh every 30s (workers every 5s)
- Loading spinner during fetches
- Project title "VibeBoard" centred in header

## 6. Implemented User Stories

28 user stories implemented, covering:
- 8 project management stories (CRUD, switch, park, resume)
- 9 task management stories (CRUD, pipeline, dependencies, logging, retry)
- 4 worker/agent stories (view, assign, auto-refresh, swarm dashboard)
- 5 UI/dashboard stories (stats, chart, auto-refresh, loading, header)
- 4 infrastructure stories (API, dashboard, tests, deployment)

See USER_STORIES.md for full details.
