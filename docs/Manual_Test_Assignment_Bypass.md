# Manual Test Assignment Bypass

**Task ID:** 161
**Target Area:** Testing Framework / CI Pipeline Integration

## Objective
This document details the procedure for manually bypassing automated test assignment logic. This bypass is intended *only* for QA and Triage teams when standard automated testing assignment fails or needs to be overridden for specific test cases (e.g., in pre-release/staging environments).

## Prerequisites
1.  Access to the internal task management API (`http://127.0.0.1:8899`).
2.  Write permissions on the target test suite artifact repository.
3.  A detailed understanding of which specific assignment rules need bypassing (e.g., `Priority` conflict, `ModuleOwner` exclusion).

## Procedure
### Step 1: Identify Bypass Scope
Determine the exact set of test IDs or files that require manual assignment bypass. **(Example:** `TestID-402`, `UserFlow-LoginFail`). Do not attempt to bypass entire suites unless authorized.

### Step 2: Execute Manual Assignment Flagging
The system uses a dedicated API endpoint to flag tests for manual review, preventing them from being consumed by standard automated assignment queues.

*   **Endpoint:** `/api/tasks/manual_flag` (Hypothetical)
*   **Method:** POST
*   **Body:** `{"test_ids": ["TestID-402", "UserFlow-LoginFail"], "reason": "Manual Override required per Task #161"}`

### Step 3: Execution Verification
After flagging, verify that the test cases are no longer appearing in the standard assignment queue dashboard. A successful bypass should result in a status change to `[MANUAL_PENDING]` or `[BYPASS_SUCCESS]`.

### Troubleshooting
*   **Error Code 401/403:** Check API credentials and ensure the service user has `Triage` role privileges.
*   **Failure to flag:** Ensure that no locking mechanisms (e.g., concurrent assignment runs) are active. Wait 5 minutes and retry.

## Conclusion
Successful bypass guarantees manual testing resources focus on critical, non-automated path coverage as defined by the QA lead. Always escalate unexpected behavior to Engineering Support immediately after executing the bypass.
