# Next Priorities

Based on the overall `task-progress.md` and the `ghl-flow.md` requirements, here are the immediate next priorities to complete the standard payment provider integration for production:

### [ ] 1️⃣ Priority: Recurring Payments (Funnels)

> Support recurring subscriptions initiated from GHL funnels.

- [ ] Investigate how GHL sends `mode: "subscription"` in `payment_initiate_props`
- [ ] Map GHL subscription details (recurring amount, interval) to PayMongo
- [ ] Determine card vaulting strategy (PayMongo's limitations with hosted checkout vs UI Elements)
- [ ] Implement `create_subscription` handler for `queryUrl`
- [ ] Handle `subscription.created` and `subscription.charged` webhook events
- [ ] Handle subscription cancellations (`cancel_subscription` queryUrl)

### [ ] 2️⃣ Priority: Error Handling & Edge Cases

- [ ] Better error messages for failed refunds (insufficient balance, already refunded)
- [ ] Handle edge cases in `verify` where customer pays but webhook is delayed

### [x] 3️⃣ Priority: Deployment & CI/CD [DONE]

- [x] **Singapore Deployment** — Fully live on Cloud Run with Cloud SQL.
- [x] **Automated Script** — `deploy.sh` handles build, deploy, and migrations.
- [x] **Live Webhook** — Production webhook registered and secret updated.

### [x] 4️⃣ Priority: UI & Multi-Merchant Foundation [DONE]

- [x] **Subaccount Name Proactive Fetch** — Correctly displays the human-readable name instead of ID.
- [x] **Landscape Configuration UI** — Professional layout with side-by-side Live/Test mode columns.
- [x] **Per-location Webhook secrets** — Dynamic provisioning and signature verification fix.

### [ ] 5️⃣ Priority: Testing Coverage

- [ ] Add unit tests for `WebhookProcessingService`
- [ ] Add integration tests for the full Redirect vs Popup flow
