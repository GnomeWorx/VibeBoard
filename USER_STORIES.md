# VibeBoard — User Stories (As Built)

Generated: 2026-07-21

> **Status:** Documentation curated from existing codebase at `/home/sfarrant/.openclaw/workspace/`.
> Legacy status values (Backlog, In Progress, QA-Review) still exist in the database for pre-migration tasks; the UI maps them via `getTaskPipelineStage()` fallback logic.

---

## 1. Task Management

### US-01: Create a new task
**As a** Brain orchestrator,  
**I want to** create a task with a title, description, status, and optional metadata,  
**so that** I can track work items in the project.

- **Built:** POST `/api/tasks` — accepts `title`, `description`, `status`, `complexity` (1–5), `assigned_to`, `depends_on` (JSON array of task IDs), `story_id`. Default status is `Plan`. Returns `{"success": true}` with HTTP 201.
- **UI:** Modal form triggered via "New Task" button. Fields: Title, Description, Status (7-stage dropdown), Complexity (1–5), Assigned Agent (from worker list), Depends on (multi-select from existing tasks), Story (dropdown). Form submit fires POST.

### US-02: Edit a task
**As a** Brain orchestrator,  
**I want to** update a task's details,  
**so that** it reflects the current state of work.

- **Built:** PUT `/api/tasks/{id}` — partial update. Accepts same fields as create. Validates dependency cycle detection. Returns `{"success": true}`.
- **UI:** "Edit" button in task table row, or click a pipeline card. Pre-populates modal with current values.

### US-03: Delete a task
**As a** Brain orchestrator,  
**I want to** remove a task that is no longer relevant,  
**so that** the task list stays clean.

- **Built:** DELETE `/api/tasks/{id}` — hard delete.
- **UI:** "Delete" button in task table row with `confirm()` dialog.

### US-04: View all tasks in a table
**As a** Brain orchestrator,  
**I want to** see all tasks in a sortable table,  
**so that** I can quickly scan and find work items.

- **Built:** GET `/api/tasks` — returns JSON array of all tasks with worker info joined. Responses include `id`, `title`, `description`, `status`, `created_by`, `worker_name`, `complexity`, `depends_on`, `retry_count`, `execution_log`, etc.
- **UI:** Sortable table with columns: ID, Title, Type (Usr/Agent badge), Status (colored badge), Worker, Deps, Retry (count + retry button), Created, Actions (Edit/Delete). Sort arrows toggle asc/desc.

### US-05: View execution log per task
**As a** Brain orchestrator,  
**I want to** see a log of what happened during a task's lifecycle,  
**so that** I can understand its execution history.

- **Built:** `execution_log` column stores JSON array of entries. `showExecutionLog()` renders them in a panel inside the task modal. Each entry has `timestamp` and `message`. Log panel toggles visible/hidden based on presence of log data.

### US-06: Retry a failed task
**As a** Brain orchestrator,  
**I want to** retry a task that failed,  
**so that** the worker can attempt it again.

- **Built:** PUT `/api/tasks/{id}/retry` — sets status to `Code`, increments `retry_count`. Max retries configurable (default 3). UI shows retry button when `status === 'Failed'` or `retry_count > 0`, disabled when retries exhausted (shows ✕).

---

## 2. Pipeline / Kanban

### US-24: View task execution pipeline
**As a** Brain orchestrator,  
**I want to** see pipeline stages (Plan → Spec → Assess → Code → Test → Review → Done) for each task,  
**so that** I know exactly where work is blocked or waiting.

- **Built:** 7-column kanban board with full drag-and-drop. Columns: Plan, Spec, Assess, Code, Test, Review, Done. Each column has a stage header with icon, stage count badge, and a card container. Cards show task number (sequential for Plan, DB ID for others), title, worker badge, dependency info, and mini stage dots. Pipeline stage indicator in the task modal shows a 7-step horizontal stepper with completed/active/future states.

### US-25: Drag and drop tasks between pipeline stages
**As a** Brain orchestrator,  
**I want to** drag a task card from one pipeline column to another,  
**so that** I can update its stage intuitively.

- **Built:** HTML5 drag-and-drop with full mouse event handling. `initDragDrop()` sets up dragover/dragenter/dragleave/drop on all `.pipeline-col` elements. `handleDrop()` fires PUT to update task status. Auto-assigns idle worker when dropping into active stages (Plan, Spec, Assess, Code). Also supports batch move via rubber-band multi-select.

### US-26: Multi-select tasks in pipeline via rubber-band selection
**As a** Brain orchestrator,  
**I want to** draw a selection rectangle across multiple pipeline cards,  
**so that** I can move or act on them as a batch.

- **Built:** Frame-select (rubber-band) via `initFrameSelect()`. Mouse drag on empty pipeline area draws a selection rectangle. Cards intersecting the rectangle are selected. Dropping a selected card moves all selected tasks to the target stage. Clicking empty space clears selection.

### US-27: See stage progress indicator in modal
**As a** Brain orchestrator,  
**I want to** see a visual progress bar of the 7 stages when I open a task,  
**so that** I immediately understand its lifecycle position.

- **Built:** `renderPipelineStages(task)` in `openModal()`. Shows/hides the `.pipeline-stage-panel` div. The bar has 7 `.pipeline-stage-step` elements with labels. Completed stages have a checkmark and green color, current stage is highlighted, future stages are gray. Label shows "Stage X of 7 - StageName".

### US-28: See mini stage dots on pipeline cards
**As a** Brain orchestrator,  
**I want to** see a small stage indicator on each card in the pipeline view,  
**so that** I can quickly see a task's progress without opening it.

- **Built:** `pipelineCardStageDots(t)` generates a row of 7 small dots (each `.pipeline-card-stage-dot`). Completed dots are green, current dot is highlighted, future dots are gray. Rendered inside each pipeline card via `renderPipeline()`.

---

## 3. Workers / Agents

### US-10: View all workers and their current task
**As a** Brain orchestrator,  
**I want to** see all agents/workers in a grid,  
**so that** I know who is available and what they're working on.

- **Built:** GET `/api/workers` — returns worker profiles with `id`, `name`, `role`, `model`, `provider`, `status` (idle/busy), linked `task_title` and `task_status`. UI renders a grid of worker cards. Each card shows name, role, model/provider, status badge with color (idle=green, busy=orange), and current task link.

### US-11: Create a new worker/agent profile
**As a** Brain orchestrator,  
**I want to** register a new agent with a name, role, model, and toolset,  
**so that** they can be assigned tasks in the system.

- **Built:** POST `/api/workers` — accepts `name`, `role`, `model`, `provider`, `toolset`, `status`. UI has "Manage Agents" modal with a table of existing agents and a form to create new ones. New agent form has fields: Name, Role, Model, Provider, Toolset, Status.

### US-12: Reset a worker's task assignment
**As a** Brain orchestrator,  
**I want to** set a worker's status to idle or reassign them,  
**so that** I can manage the workforce.

- **Built:** PUT `/api/workers/{id}` — accepts `status`, `task_id`. Setting `status: "idle"` unlinks the worker from their current task.

---

## 4. Stories

### US-13: View user stories in a grid
**As a** Business analyst,  
**I want to** see all stories displayed as cards,  
**so that** I can track requirements at a higher level than tasks.

- **Built:** GET `/api/stories` — returns stories with `id`, `title`, `description`, `status`, `story_type`, `department`, `issue_url`, `issue_number`, `created_at`. UI renders a story card grid. Each card shows story title, linked tasks as `#taskId` links, GitHub link, and assigned department badge.

### US-14: Create a new story
**As a** Business analyst,  
**I want to** create a user story with acceptance criteria,  
**so that** developers have requirements to implement.

- **Built:** POST `/api/stories` — accepts `title`, `description`, `status`, `story_type`, `department`. UI has "New Story" button and modal form.

### US-15: Link tasks to stories
**As a** Brain orchestrator,  
**I want to** assign a task to a story,  
**so that** I can trace implementation work back to requirements.

- **Built:** Task modal includes a Story dropdown, populated from `/api/stories`. Task's `story_id` field links it to a story. `renderStories()` shows linked task IDs under each story card.

### US-16: Import GitHub issues as stories
**As a** Brain orchestrator,  
**I want to** import issues from a GitHub repository,  
**so that** existing work items are represented in VibeBoard.

- **Built:** POST `/api/stories/import` — accepts `repo` (e.g. `"owner/repo"`) and optional `github_token`. Fetches issues via GitHub API, creates a story per issue. Stories imported from GitHub have `story_type = 'github'`. UI: "Import from GitHub" button triggers the import. Stories show GitHub issue number and link.

---

## 5. Reports & Analytics

### US-17: View metrics dashboard
**As a** Brain orchestrator,  
**I want to** see key metrics (total tasks, completed, active, progress %),  
**so that** I can gauge project health at a glance.

- **Built:** GET `/api/metrics` — returns `progressPercentage` and `breakdown` (counts per status). UI: Stats cards at top: Total Tasks, Completed, Active, Plan, Progress %. Chart.js doughnut chart shows status distribution with colored segments.

### US-18: View report statistics
**As a** Brain orchestrator,  
**I want to** see aggregate statistics about task execution,  
**so that** I can identify trends and bottlenecks.

- **Built:** GET `/api/reports/stats` — returns averages for regressions, duration, complexity, and counts for regressed tasks and total stories. UI: Reports section with cards: Avg Regressions, Avg Duration, Avg Complexity, Regressed Tasks, Total Stories.

### US-19: Auto-refresh dashboard data
**As a** Brain orchestrator,  
**I want to** see live-updating data without manual refresh,  
**so that** I always see the current state.

- **Built:** `setInterval(reloadAll, 30000)` — auto-refreshes every 30 seconds. Refresh button in navbar for manual reload. Loading spinner shown during fetches. Toast notifications on errors.

### US-20: View overdue tasks
**As a** Brain orchestrator,  
**I want to** see tasks stuck in non-terminal stages for more than 24 hours,  
**so that** I can identify and unblock stalled work.

- **Built:** GET `/api/tasks/overdue` — queries tasks where status NOT IN ('Plan', 'Done') AND TIMESTAMPDIFF(HOUR, updated_at, NOW()) > 24. Returns list of overdue tasks.

---

## 6. Project Management

### US-21: Switch between projects
**As a** Brain orchestrator,  
**I want to** select a different project from a dropdown,  
**so that** I can manage multiple projects from one dashboard.

- **Built:** Project selector in navbar with dropdown. GET `/api/projects` returns all projects. GET `/api/projects/current` returns active project. Selecting a project filters all data (tasks, metrics, workers, stories) to that project's scope.

### US-22: Create a new project
**As a** Brain orchestrator,  
**I want to** create a new project with a name and description,  
**so that** I can organize work into separate initiatives.

- **Built:** POST `/api/projects` — accepts `name` and `description`. "Manage Projects" button opens modal with project table and "New Project" form.

### US-23: Park a project with summary
**As a** Brain orchestrator,  
**I want to** park the current project and record a summary,  
**so that** I can switch contexts and preserve state.

- **Built:** "Park" button in navbar. Opens park modal showing project name, auto-generated preview summary (done count, in-progress count, percentage, busy workers), and a note text field. Calls POST `/api/projects/{id}/park`. Parked projects show a "Parked" badge. `getProjectScope()` returns null for parked projects.

---

## 7. API & Infrastructure

### US-08: Health check endpoint
**As a** System operator,  
**I want to** check if the API and database are online,  
**so that** I can monitor system health.

- **Built:** GET `/api/status` — returns `{"status": "online", "version": "1.0.0", "db": "connected"}` with HTTP 200. DB health confirmed via `SELECT 1`.

### US-09: Database migration / setup
**As a** Developer,  
**I want to** set up the database schema with a single command,  
**so that** the application can be deployed fresh.

- **Built:** `db/setup.php` — creates `tasks`, `workers`, `projects`, `stories` tables with proper schemas and indexes. Also runs `db/seed.php` to insert sample data.

### API Routes (All Built)
| Method | Path | Description |
|--------|------|-------------|
| GET | /api/status | Health check |
| GET | /api/metrics | Progress % + status breakdown |
| GET | /api/tasks | List tasks |
| GET | /api/tasks/:id | Get single task |
| POST | /api/tasks | Create task |
| PUT | /api/tasks/:id | Update task |
| DELETE | /api/tasks/:id | Delete task |
| GET | /api/tasks/overdue | Stuck tasks >24h |
| PUT | /api/tasks/:id/retry | Retry failed task |
| GET | /api/workers | List workers |
| POST | /api/workers | Create worker |
| PUT | /api/workers/:id | Update worker |
| GET | /api/projects | List projects |
| POST | /api/projects | Create project |
| GET | /api/projects/current | Get active project |
| POST | /api/projects/:id/park | Park project |
| GET | /api/stories | List stories |
| POST | /api/stories | Create story |
| POST | /api/stories/import | Import from GitHub |
| GET | /api/reports/stats | Report statistics |

---

## 8. UI & UX Features

### Dark theme
- Full dark theme with CSS variables (`--bg-primary: #0d1117`, `--bg-secondary: #161b22`, etc.)
- GitHub-inspired color scheme (dark mode)
- Responsive grid layout

### Toast notifications
- Slide-in toasts from right, auto-dismiss after 3s
- Types: success (green), error (red)
- Styled with CSS animations (opacity + translate)

### Loading states
- Spinner overlay during data fetches (`showLoading(true/false)`)
- Spinner on refresh button
- Loading row in empty task table

### Error handling
- API errors displayed via toast
- Modal close button + click-outside-to-close (not visible in full code read but standard pattern)
- Null-safe element references
