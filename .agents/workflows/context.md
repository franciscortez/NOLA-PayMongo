---
description: Read project context files before starting any task
---

## Pre-Prompt Context Loading

Before performing any work on this codebase, read these context files:

// turbo

1. Read `.agents/context-codebase.md` — contains the full codebase architecture, flows, database schema, API references, and known issues.
2. Read `.agents/task-progress.md` — contains the master feature-chunked task list detailing all overall completed and remaining items.
3. Read `.agents/todo.md` — contains the current immediate priorities you are focusing on.

**CRITICAL INSTRUCTION 1:** Every time you accomplish a task and verify that it works, you MUST update `.agents/task-progress.md` and `.agents/todo.md` by marking the task with `[x]`. If you are currently working on a task, mark it with `[/]`.
**CRITICAL INSTRUCTION 2:** Every time you make structural or meaningful changes to the codebase (e.g. adding a new table, writing a new middleware, changing a core flow), you MUST update `.agents/context-codebase.md` to accurately reflect the current state of the application.

3. Reference the external documentation as needed:
    - **PayMongo API**: https://developers.paymongo.com/docs/introduction
    - **GoHighLevel API**: https://marketplace.gohighlevel.com/docs/
    - **GHL Custom Provider**: https://marketplace.gohighlevel.com/docs/ghl/payments/custom-provider
    - **How to build a custom payments integration on the platform (flow)** https://help.gohighlevel.com/support/solutions/articles/155000002620-how-to-build-a-custom-payments-integration-on-the-platform
    - **Google Cloud Run**: The target production environment is Google Cloud Run + Cloud SQL.
