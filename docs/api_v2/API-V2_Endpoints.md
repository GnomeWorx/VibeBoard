# VibeBoard API v2 Documentation

This document outlines the major endpoints and functionalities for Version 2 of the VibeBoard internal API. This version focuses on modularity, improved worker handling, and standardized metric tracking.

## 🚀 Core Endpoints Overview

| Endpoint | Method | Description | Models Involved | Status Code |
| :--- | :--- | :--- | :--- | :--- |
| /api/tasks/{task_id} | GET | Retrieves granular metadata for a single task ID (e.g., Task 132). | Task, Project | 200 OK |
| /api/workers/{worker_id} | PUT | Updates the operational status of a specific worker unit. | Worker | 200 OK |
| /api/metrics | POST | Submits usage metrics and performance data from client modules. | Metric | 201 Created |

## 👷 Worker Management (Worker Endpoint)

**Endpoint:** `http://127.0.0.1:8899/api/workers/{worker_id}`
**Purpose:** Used to broadcast the real-time operational status of resource units/workers. This is critical for monitoring resource allocation.

**Example Request Body (PUT):**
```json
{
  "status": "idle",
  "last_updated": "YYYY-MM-DDTTHH:MM:SSZ",
  "worker_id": 8
}
```

## ✨ Task Management (Task Endpoint)

**Endpoint:** `http://127.0.0.1:8899/api/tasks/{task_id}`
**Purpose:** To manage the state of projects or sub-tasks. The primary workflow target is **Task ID 135**.

**State Transition Workflow (Mandatory Completion Steps):**
Upon completion, Task 135 requires two specific updates:
1. The task status must be explicitly moved to `QA-Review` via a PUT request.
2. The associated worker unit (ID: 4) must be confirmed as `idle`, referencing the Worker Endpoint.
