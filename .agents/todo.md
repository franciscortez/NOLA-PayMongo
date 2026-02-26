# Next Priorities

Based on the overall `task-progress.md`, here are the immediate next priorities to work on:

### [ ] 1️⃣ Priority: Queue System & Webhook Retries

> Ensure reliable delivery of webhooks to GHL by moving to a queue-based architecture.

- [ ] **Queue worker setup** — Configure Laravel queues for background jobs.
- [ ] **Failed GHL webhook notification retry** — Move GHL webhook logic to an async Job with automatic retries if GHL webhook delivery fails.

### [x] 2️⃣ Priority: Security Hardening

> Continue hardening the integration for production use.

- [x] **HTTPS enforcement** — Ensure all endpoints require HTTPS (middleware in production; skipped local/testing).
- [x] **Rate limiting** — Rate limits on checkout only (Webhooks excluded to avoid 429s causing integration failure).
- [x] **PayMongo webhook signature verification** — Validate webhook authenticity (already implemented; debug logging gated to APP_DEBUG).
- [x] **CSRF protection on API routes** — Exclusions narrowed to `checkout/create-session` only.
- [x] **Logging & monitoring** — Structured logging to `payments` channel.

## 8. 📊 Subscriptions & Recurring Payments

> Support for recurring billing through GHL.

- [ ] **Subscription creation** — Handle GHL subscription initiation
- [ ] **Recurring charge processing** — Charge saved payment methods on schedule
- [ ] **Subscription status tracking** — Track active/paused/cancelled subscriptions
- [ ] **Subscription webhook handling** — Handle GHL subscription lifecycle events
- [ ] **PayMongo recurring payments** — Integrate with PayMongo's recurring payment features if available
