# Next Priorities

Based on the overall `task-progress.md`, here are the immediate next priorities to work on:

### [x] 1️⃣ Priority: Provider Config Resilience (Private Integration)

> Currently, the integration uses the central `.env` API keys for all sub-accounts because it is a private integration apps. However, we need to improve the GHL Connect Config flow so it is resilient.

- [x] Display current provider connection status — Show whether provider is already connected before offering connect/disconnect.
- [x] Fetch existing provider config from GHL — Use `GET /payments/custom-provider/provider` to check registration state.
- [x] Provider config validation — Verify API keys are valid before pushing to GHL.

### [ ] 2️⃣ Priority: Verify & Refund Resilience

> Handle edge cases in the payment verification and refund flows.

- [ ] Improve verify resilience — Handle race conditions where webhook hasn't arrived yet (add retry/wait logic).
- [ ] Verify by multiple ID types — Support lookup by `payment_id`, `checkout_session_id`, or `ghl_transaction_id` more robustly.
- [ ] Partial refund support — Verify partial refund amounts work correctly end-to-end.
- [ ] Refund status tracking — Store refund ID and amount in transaction metadata.

### [x] 3️⃣ Priority: Checkout UX Improvements

> Enhance the user experience within the GoHighLevel checkout iFrame.

- [x] Checkout UI improvements — Better loading states, branded design, progress indicators.
- [x] Handle expired checkout sessions — Auto-create new session if previous one expired.
- [x] Customer billing address — Pass full address from GHL contact to PayMongo.
- [x] Inline payment form (no popup) — Embed PayMongo Elements directly in iFrame instead of popup
