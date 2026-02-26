# NOLA PayMongo × GoHighLevel — Feature TODO

> **Feature-chunked task list.** Each section is a feature area with sub-tasks.
>
> - `[ ]` = Not started
> - `[/]` = In progress
> - `[x]` = Done

---

## 1. 🔐 OAuth & Authentication [x]

> GHL OAuth flow for connecting sub-accounts to this app.

- [x] OAuth callback endpoint (`/oauth/callback`)
- [x] Exchange authorization code for access/refresh tokens
- [x] Save tokens to `location_tokens` table
- [x] Redirect to provider config page after OAuth
- [x] Auto-refresh expired tokens — `GhlService::refreshToken()` exists and is called automatically via `CheckGhlToken` middleware
- [x] Token expiry check middleware — Validates token freshness before any GHL API call

### [x] 1️⃣ Priority: Multi-location & Error Handling

> Ensure the app scales and handles errors gracefully.

- [x] Multi-location support — Test and verify multiple GHL locations can connect simultaneously.
- [x] OAuth error handling UI — Show user-friendly errors on callback failures.
- [x] Provider config error detail display — Show GHL API error details on failure.

## 2. 🔧 Provider Configuration [x]

> Registering/removing NOLA PayMongo as a custom payment provider in GHL. (Private App: Uses central `.env` PayMongo keys for all locations).

- [x] Register custom provider in GHL (`ProviderConfigService::registerCustomProvider`)
- [x] Push PayMongo API keys via Connect Config API
- [x] Delete provider from GHL
- [x] Provider config UI (`/provider/config`)

### [x] 1️⃣ Priority: Provider Config Resilience (Private Integration)

> Currently, the integration uses the central `.env` API keys for all sub-accounts because it is a private integration apps. However, we need to improve the GHL Connect Config flow so it is resilient.

- [x] Display current provider connection status — Show whether provider is already connected before offering connect/disconnect.
- [x] Fetch existing provider config from GHL — Use `GET /payments/custom-provider/provider` to check registration state.
- [x] Provider config validation — Verify API keys are valid before pushing to GHL.
- [x] Support central PayMongo keys — Uses environment variables since this is a private integration.
- [ ] **Provider config error detail display** — Show GHL API error details on failure, not just generic messages

---

## 3. 💳 Checkout & Payment Flow

> The iFrame checkout experience inside GHL and PayMongo session management.

- [x] Checkout iFrame page (`/checkout`) with GHL postMessage handshake
- [x] `custom_provider_ready` heartbeat to GHL
- [x] Listen for `payment_initiate_props` from GHL
- [x] Create PayMongo Checkout Session with line items
- [x] Open PayMongo checkout URL in popup window
- [x] Popup fallback if popup blocker activated
- [x] Poll payment status after popup closes
- [x] Notify GHL with `custom_element_success_response`
- [x] Notify GHL with `custom_element_error_response`
- [x] Success and cancel callback pages
- [x] Support multiple line items — Parses `productDetails` array from GHL into mapped PayMongo line items
- [x] Inline payment form — Embed PayMongo Elements directly in iFrame
- [x] Handle expired checkout sessions — Auto-create new session if previous one expired
- [x] Customer billing address — Pass full address from GHL contact to PayMongo
- [ ] **Currency support beyond PHP** — Handle GHL locations using USD or other currencies
- [ ] **Checkout timeout configuration** — Make the 30s handshake timeout configurable

---

## 4. ✅ Payment Verification (queryUrl) [DONE]

> GHL's queryUrl handler for verifying, refunding, and managing payments.

- [x] Verify payment status (`type: verify`) — DB-first with PayMongo API fallback
- [x] Build chargeSnapshot response for GHL
- [x] Resolve test vs live PayMongo key from GHL `apiKey`
- [x] Improve verify resilience — Handle race conditions where webhook hasn't arrived yet (add retry/wait logic)
- [x] Verify by multiple ID types — Support lookup by `payment_id`, `checkout_session_id`, or `ghl_transaction_id` more robustly

---

## 5. 💸 Refunds [DONE]

> Processing refunds initiated from GHL back through PayMongo.

- [x] Refund payment via PayMongo API
- [x] Update transaction status to `refunded` in DB
- [x] Return refund result to GHL
- [x] Partial refund support — Verify partial refund amounts work correctly end-to-end
- [x] Refund status tracking — Store refund ID and amount in transaction metadata
- [x] Refund webhook handling — Process `payment.refunded` webhook from PayMongo
- [ ] **Refund failure handling** — Better error messages for failed refunds (insufficient balance, already refunded)

---

## 6. 🔔 Webhooks [x]

> Bidirectional webhook handling: PayMongo → App → GHL.

- [x] PayMongo webhook endpoint (`/api/webhook/paymongo`)
- [x] Handle `checkout_session.payment.paid`
- [x] Handle `payment.paid`
- [x] Handle `payment.failed`
- [x] Handle `payment.refunded`
- [x] Forward `payment.captured` event to GHL webhook
- [x] PayMongo webhook signature verification — Validates `X-Paymongo-Signature` header against webhook secret to prevent spoofing
- [x] Webhook retry/idempotency — Prevent duplicate processing of the same webhook event
- [x] Webhook event logging table — Store raw webhook payloads for debugging/audit trail
- [ ] **Failed GHL webhook notification retry** — Queue and retry if GHL webhook delivery fails

### [x] 1️⃣ Priority: Transaction Expiration Handling

> Handle stale transactions and expiration events.

- [x] Handle `payment.expired` events — Update transaction status when PayMongo sessions expire.
- [x] Stale transaction cleanup — Mark transactions as `expired` if pending for >24 hours.

## 7. 💾 Transaction Management

> Database-backed transaction tracking and reporting.

- [x] Transaction model with scopes (`paid`, `pending`, `failed`, `byLocation`)
- [x] Transaction creation on checkout session
- [x] Status updates from webhooks and polling
- [x] Stale transaction cleanup — Mark transactions as `expired` if pending for >24 hours

---

## 8. 📊 Subscriptions & Recurring Payments

> Support for recurring billing through GHL.

- [ ] **Subscription creation** — Handle GHL subscription initiation
- [ ] **Recurring charge processing** — Charge saved payment methods on schedule
- [ ] **Subscription status tracking** — Track active/paused/cancelled subscriptions
- [ ] **Subscription webhook handling** — Handle GHL subscription lifecycle events
- [ ] **PayMongo recurring payments** — Integrate with PayMongo's recurring payment features if available

---

## 9. 🛡️ Security & Reliability [x]

> Hardening the integration for production use.

- [ ] **HTTPS enforcement** — Ensure all endpoints require HTTPS (currently relies on ngrok)
- [ ] **Rate limiting** — Add rate limits on checkout creation and webhook endpoints
- [x] Input sanitization — Validate and sanitize all incoming data from GHL and PayMongo
- [ ] **PayMongo webhook signature verification** — Validate webhook authenticity
- [x] Encrypt stored tokens — Encrypt `access_token` and `refresh_token` at rest
- [ ] **CSRF protection on API routes** — Review CSRF exclusions for webhook endpoints
- [ ] **Logging & monitoring** — Structured logging for production debugging

---

## 10. 🚀 Deployment & DevOps

> Production readiness and deployment pipeline.

- [ ] **Production deployment guide** — Document Google Cloud Run requirements, env setup, and deployment steps
- [ ] **Deploy to Google Cloud Run** — Containerize the app and deploy serverless via GCP
- [ ] **Cloud SQL Setup** — Set up managed MySQL for transaction data
- [ ] **Queue worker setup** — Configure Laravel queues for webhook processing
- [x] Health check endpoint — Dedicated `/api/health` checking DB status
- [x] Provider Diagnose endpoint — Extracted to `/api/provider/diagnose` to validate GHL config state
- [ ] **CI/CD pipeline** — Automated testing and deployment
- [ ] **Environment-specific configs** — Separate configs for staging and production

---

## 11. 📄 Documentation & Testing

> Project documentation and test coverage.

- [ ] **README.md** — Setup instructions, environment requirements, architecture overview
- [ ] **API documentation** — Document all endpoints (Postman collection or OpenAPI spec)
- [ ] **Unit tests** — Test services (PayMongoService, GhlService, ProviderConfigService)
- [ ] **Integration tests** — End-to-end test for OAuth → checkout → payment → verify flow
- [ ] **Webhook testing** — Mock PayMongo webhook events for automated testing
- [ ] **GHL sandbox testing guide** — Step-by-step guide for testing the full flow on GHL sandbox
