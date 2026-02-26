# Next Priorities

Based on the overall `task-progress.md`, here are the immediate next priorities to work on:

### [ ] 1️⃣ Priority: Queue System & Webhook Retries

> Ensure reliable delivery of webhooks to GHL by moving to a queue-based architecture.

- [ ] **Queue worker setup** — Configure Laravel queues for background jobs.
- [ ] **Failed GHL webhook notification retry** — Move GHL webhook logic to an async Job with automatic retries if GHL webhook delivery fails.

### [ ] 2️⃣ Priority: Security Hardening

> Continue hardening the integration for production use.

- [ ] **HTTPS enforcement** — Ensure all endpoints require HTTPS (currently relies on ngrok).
- [ ] **Rate limiting** — Add rate limits on checkout creation and webhook endpoints.
- [ ] **PayMongo webhook signature verification** — Validate webhook authenticity.
- [ ] **CSRF protection on API routes** — Review CSRF exclusions for webhook endpoints.
- [ ] **Logging & monitoring** — Structured logging for production debugging.
