# VibeBoard

> Project Task Dashboard with Swarm Orchestration

## Overview

VibeBoard is a lightweight PHP + MariaDB task management dashboard designed for AI agent swarms. It provides real-time task tracking, worker/agent management, multi-project support, and a full REST API for automated orchestration.

## Current Status

- **Phase:** Production (all core features complete)
- **Active Workers:** 7 (Kevin, Stuart, Bob, Jerry, Dave, Carl, Tim)
- **Tasks:** 36 total (35 Done, 1 in progress)
- **Server:** PHP built-in dev server on 127.0.0.1:8899

## What's Built

- Full task CRUD with pipeline workflow (Backlog → In Progress → QA-Review → Done)
- Multi-project management with park/resume
- Worker/agent tracking with real-time status updates
- 7-stage execution pipeline (Plan → Spec → Assess → Code → Test → Review → Done)
- Task dependency management with cycle detection
- Structured execution logging
- Automatic retry on failure
- Chart.js dashboard with auto-refresh
- 6 PHPUnit test suites (22 integration tests)

## Quick Start

```bash
cd /home/sfarrant/.openclaw/workspace
php -S 127.0.0.1:8899 -t public/
```

Then open http://127.0.0.1:8899 in a browser.

## Database

MariaDB with 4 tables: `tasks`, `workers`, `projects`, `app_settings`.
Migration scripts in `db/` directory.

## API

Full REST API at `/api/` — see SPEC.md for complete endpoint reference.

## Documentation

- SPEC.md — Full technical specification
- PLAN.md — Implementation plan  
- USER_STORIES.md — All user stories documentation
