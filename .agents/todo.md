# NOLA PayMongo × GoHighLevel — Feature TODO

> **Feature-chunked task list.** Each section is a feature area with sub-tasks.
>
> - `[ ]` = Not started
> - `[/]` = In progress
> - `[x]` = Done

---

## 1. 🔐 OAuth & Authentication

> GHL OAuth flow for connecting sub-accounts to this app.

- [x] OAuth callback endpoint (`/oauth/callback`)
- [x] Exchange authorization code for access/refresh tokens
- [x] Save tokens to `location_tokens` table
- [x] Redirect to provider config page after OAuth
- [ ] **Auto-refresh expired tokens** — `GhlService::refreshToken()` exists but is never called automatically
- [ ] **Token expiry check middleware** — Validate token freshness before any GHL API call
- [ ] **Multi-location support** — Test and verify multiple GHL locations can connect simultaneously
- [ ] **OAuth error handling UI** — Show user-friendly errors on callback failures

---

## 2. 🔧 Provider Configuration

> Registering/removing NOLA PayMongo as a custom payment provider in GHL.

- [x] Register custom provider in GHL (`ProviderConfigService::registerCustomProvider`)
- [x] Push PayMongo API keys via Connect Config API
- [x] Delete provider from GHL
- [x] Provider config UI (`/provider/config`)
- [ ] **Display current provider connection status** — Show whether provider is already connected before offering connect/disconnect
- [ ] **Fetch existing provider config from GHL** — Use `GET /payments/custom-provider/provider` to check registration state
- [ ] **Provider config validation** — Verify API keys are valid before pushing to GHL
- [ ] **Support user-entered PayMongo keys** — Currently hardcoded from `.env`; allow per-location key configuration
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
- [ ] **Support multiple line items** — Currently creates 1 line item from description; parse `productDetails` array from GHL
- [ ] **Checkout UI improvements** — Better loading states, branded design, progress indicators
- [ ] **Inline payment form (no popup)** — Embed PayMongo Elements directly in iFrame instead of popup
- [ ] **Handle expired checkout sessions** — Auto-create new session if previous one expired
- [ ] **Customer billing address** — Pass full address from GHL contact to PayMongo
- [ ] **Currency support beyond PHP** — Handle GHL locations using USD or other currencies
- [ ] **Checkout timeout configuration** — Make the 30s handshake timeout configurable

---

## 4. ✅ Payment Verification (queryUrl)

> GHL's queryUrl handler for verifying, refunding, and managing payments.

- [x] Verify payment status (`type: verify`) — DB-first with PayMongo API fallback
- [x] Build chargeSnapshot response for GHL
- [x] Resolve test vs live PayMongo key from GHL `apiKey`
- [ ] **Improve verify resilience** — Handle race conditions where webhook hasn't arrived yet (add retry/wait logic)
- [ ] **Verify by multiple ID types** — Support lookup by `payment_id`, `checkout_session_id`, or `ghl_transaction_id` more robustly
- [ ] **Add request signature validation** — Verify incoming GHL queryUrl requests are authentic

---

## 5. 💸 Refunds

> Processing refunds initiated from GHL back through PayMongo.

- [x] Refund payment via PayMongo API
- [x] Update transaction status to `refunded` in DB
- [x] Return refund result to GHL
- [ ] **Partial refund support** — Verify partial refund amounts work correctly end-to-end
- [ ] **Refund status tracking** — Store refund ID and amount in transaction metadata
- [ ] **Refund webhook handling** — Process `payment.refunded` webhook from PayMongo (exists but basic)
- [ ] **Refund failure handling** — Better error messages for failed refunds (insufficient balance, already refunded)

---

## 6. 🔔 Webhooks

> Bidirectional webhook handling: PayMongo → App → GHL.

- [x] PayMongo webhook endpoint (`/api/webhook/paymongo`)
- [x] Handle `checkout_session.payment.paid`
- [x] Handle `payment.paid`
- [x] Handle `payment.failed`
- [x] Handle `payment.refunded`
- [x] Forward `payment.captured` event to GHL webhook
- [ ] **PayMongo webhook signature verification** — Validate `X-Paymongo-Signature` header to prevent spoofing
- [ ] **Webhook retry/idempotency** — Prevent duplicate processing of the same webhook event
- [ ] **Register PayMongo webhook programmatically** — Auto-register the webhook URL via PayMongo API instead of manual setup
- [ ] **Webhook event logging table** — Store raw webhook payloads for debugging/audit trail
- [ ] **Failed GHL webhook notification retry** — Queue and retry if GHL webhook delivery fails
- [ ] **Handle `payment.expired` events** — Update transaction status when PayMongo sessions expire

---

## 7. 💾 Transaction Management

> Database-backed transaction tracking and reporting.

- [x] Transaction model with scopes (`paid`, `pending`, `failed`, `byLocation`)
- [x] Transaction creation on checkout session
- [x] Status updates from webhooks and polling
- [ ] **Transaction dashboard/admin panel** — View all transactions, filter by status/location/date
- [ ] **Transaction search** — Search by customer name, email, order ID, or amount
- [ ] **Export transactions** — CSV/Excel export for accounting
- [ ] **Transaction analytics** — Daily/weekly/monthly revenue charts per location
- [ ] **Stale transaction cleanup** — Mark transactions as `expired` if pending for >24 hours

---

## 8. 🏗️ Card Vaulting & Saved Payment Methods

> Enabling saved cards for returning customers (GHL `list_payment_methods` and `charge_payment`).

- [ ] **Implement `list_payment_methods`** — Return saved payment methods for a GHL contact
- [ ] **Implement `charge_payment`** — Charge a saved payment method without checkout
- [ ] **PayMongo Customer creation** — Create PayMongo Customers linked to GHL contacts
- [ ] **Card tokenization** — Save card tokens via PayMongo for future use
- [ ] **Payment method management UI** — Allow customers to view/remove saved cards

---

## 9. 📊 Subscriptions & Recurring Payments

> Support for recurring billing through GHL.

- [ ] **Subscription creation** — Handle GHL subscription initiation
- [ ] **Recurring charge processing** — Charge saved payment methods on schedule
- [ ] **Subscription status tracking** — Track active/paused/cancelled subscriptions
- [ ] **Subscription webhook handling** — Handle GHL subscription lifecycle events
- [ ] **PayMongo recurring payments** — Integrate with PayMongo's recurring payment features if available

---

## 10. 🛡️ Security & Reliability

> Hardening the integration for production use.

- [ ] **HTTPS enforcement** — Ensure all endpoints require HTTPS (currently relies on ngrok)
- [ ] **Rate limiting** — Add rate limits on checkout creation and webhook endpoints
- [ ] **Input sanitization** — Validate and sanitize all incoming data from GHL and PayMongo
- [ ] **PayMongo webhook signature verification** — Validate webhook authenticity
- [ ] **GHL request origin validation** — Verify postMessage origin in checkout iFrame
- [ ] **Encrypt stored tokens** — Encrypt `access_token` and `refresh_token` at rest
- [ ] **CSRF protection on API routes** — Review CSRF exclusions for webhook endpoints
- [ ] **Logging & monitoring** — Structured logging for production debugging
- [ ] **Error alerting** — Send notifications (Slack/email) on critical failures

---

## 11. 🚀 Deployment & DevOps

> Production readiness and deployment pipeline.

- [ ] **Production deployment guide** — Document server requirements, env setup, and deployment steps
- [ ] **Migrate from ngrok to production domain** — Set up a real HTTPS domain
- [ ] **Database backups** — Automated backup strategy for transaction data
- [ ] **Queue worker setup** — Configure Laravel queues for webhook processing
- [ ] **Health check endpoint** — `/health` endpoint for uptime monitoring
- [ ] **CI/CD pipeline** — Automated testing and deployment
- [ ] **Environment-specific configs** — Separate configs for staging and production

---

## 12. 📄 Documentation & Testing

> Project documentation and test coverage.

- [ ] **README.md** — Setup instructions, environment requirements, architecture overview
- [ ] **API documentation** — Document all endpoints (Postman collection or OpenAPI spec)
- [ ] **Unit tests** — Test services (PayMongoService, GhlService, ProviderConfigService)
- [ ] **Integration tests** — End-to-end test for OAuth → checkout → payment → verify flow
- [ ] **Webhook testing** — Mock PayMongo webhook events for automated testing
- [ ] **GHL sandbox testing guide** — Step-by-step guide for testing the full flow on GHL sandbox
