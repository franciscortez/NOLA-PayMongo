# Next Priorities

Based on the overall `task-progress.md`, here are the immediate next priorities to work on:

### [x] 1️⃣ Priority: Webhook & Security Hardening

> Improve the reliability and security of the integration.

- [x] Webhook retry/idempotency — Prevent duplicate processing of the same webhook event.
- [x] Webhook event logging table — Store raw webhook payloads for debugging/audit trail.

### [x] 1️⃣ Priority: Verify & Refund Resilience [DONE]

> Handle edge cases in the payment verification and refund flows.

- [x] Improve verify resilience — Handle race conditions where webhook hasn't arrived yet (add retry/wait logic).
- [x] Verify by multiple ID types — Support lookup by `payment_id`, `checkout_session_id`, or `ghl_transaction_id` more robustly.
- [x] Partial refund support — Verify partial refund amounts work correctly end-to-end.
- [x] Refund status tracking — Store refund ID and amount in transaction metadata.
