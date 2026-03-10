# NOLA PayMongo × GoHighLevel — Codebase Context

> **Read this file at the start of every prompt to understand the project.**

---

## Project Overview

This is a **Laravel 12** application that acts as a **Custom Payment Provider** bridge between **GoHighLevel (GHL)** CRM and **PayMongo** (a Philippine payment gateway). It allows GHL sub-accounts to accept payments via PayMongo directly inside the GHL checkout flow.

### Core Idea

```
GHL CRM ←→ This Laravel App ←→ PayMongo API
```

- **GHL** is the business user's CRM (contacts, sales, invoices, etc.)
- **This App** registers itself as a custom payment provider inside GHL
- **PayMongo** handles the actual payment processing (cards, GCash, GrabPay, Maya, QRPH)

---

## Tech Stack

| Layer           | Technology                                     |
| --------------- | ---------------------------------------------- |
| Framework       | Laravel 12 (PHP 8.2+)                          |
| Database        | MySQL                                          |
| Frontend        | Blade templates, vanilla JS, TailwindCSS (CDN) |
| Payment Gateway | PayMongo API v1                                |
| CRM Platform    | GoHighLevel API v2021-07-28                    |
| Tunneling       | ngrok (for HTTPS in localhost)                 |
| Hosting         | Google Cloud Run (Target Environment)          |
| Tunneling       | ngrok (for HTTPS in local development)         |

---

## Architecture

### Directory Structure (Key Files Only)

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── GhlOAuthController.php      — OAuth callback from GHL
│   │   ├── ProviderConfigController.php — Register/delete provider in GHL
│   │   ├── CheckoutController.php       — Checkout iFrame + PayMongo sessions
│   │   ├── QueryController.php          — GHL queryUrl handler (verify, refund, list_payment_methods, charge_payment)
│   │   ├── PayMongoWebhookController.php — PayMongo webhook event handler
│   │   └── HomeController.php           — OAuth link generator + Health Check
│   ├── Middleware/
│   │   ├── AllowIframeEmbedding.php     — Removes X-Frame-Options for GHL iFrame
│   │   ├── CheckGhlToken.php            — Auto-refreshes GHL tokens before API calls
│   │   ├── EnsureHttps.php              — Redirects HTTP to HTTPS in production
│   │   └── VerifyPayMongoSignature.php  — Validates webhook authenticity
├── Models/
│   ├── LocationToken.php               — GHL OAuth tokens per location
│   ├── Transaction.php                 — Payment transaction records
│   └── WebhookLog.php                  — Webhook event logs (idempotency)
├── Services/
│   ├── CheckoutService.php             — Checkout creation and status logic
│   ├── GhlQueryService.php             — GHL queryUrl verify and refund logic
│   ├── GhlService.php                  — OAuth token exchange & refresh
│   ├── GhlWebhookService.php           — Sends payment.captured events to GHL
│   ├── PayMongoService.php             — PayMongo API wrapper (checkout, verify, refund)
│   ├── ProviderConfigService.php       — GHL custom provider registration API
│   └── WebhookProcessingService.php    — PayMongo webhook event processing
routes/
├── web.php                             — OAuth, provider config, checkout routes
└── api.php                             — GHL query webhook, PayMongo webhook
resources/views/
├── checkout/
│   ├── index.blade.php                 — Main checkout iFrame (GHL embeds this)
│   ├── success.blade.php               — Post-payment success page (popup)
│   └── cancel.blade.php                — Post-payment cancel page (popup)
├── provider/
│   └── config.blade.php                — Provider setup UI (connect/disconnect)
└── welcome.blade.php                   — Installation welcome page (OAuth links)
database/migrations/
├── 2026_02_23_015622_create_location_tokens_table.php — GHL location OAuth tokens
├── 2026_02_23_043648_create_transactions_table.php — Payment transactions
└── 2026_02_25_030914_create_webhook_logs_table.php — PayMongo webhook audit log
```

---

## Key Flows

### 1. OAuth & Provider Registration

```
GHL Marketplace → Install App → /oauth/callback
  → Exchange code for access_token (GhlService)
  → Save LocationToken in DB
  → Redirect to /provider/config?location_id=xxx
  → User clicks "Connect Provider"
  → Register Custom Provider in GHL (ProviderConfigService)
  → Push PayMongo API keys to GHL Connect Config
```

### 2. Checkout / Payment Flow

```
GHL loads iFrame → /checkout (CheckoutController::show)
  → JS sends `custom_provider_ready` via postMessage
  → GHL sends `payment_initiate_props` back (amount, currency, contact, etc.)
  → JS posts to /checkout/create-session (parsing `productDetails` into line items)
  → CheckoutController accesses CheckoutService to create PayMongo Checkout Session (with fallback for missing item arrays)
  → Transaction saved to DB (status: pending)
  → JS opens PayMongo checkout URL in popup window
  → Customer pays → PayMongo redirects to /checkout/success
  → Popup closes → iFrame JS polls /checkout/status/{sessionId}
  → If paid → notifies GHL via postMessage (`custom_element_success_response`)
  → For Invoices: JS extracts `invoiceId` from `payment_initiate_props` and passes it to `/checkout/create-session`.
```

### 3. Webhook Flow (PayMongo → App → GHL)

```
PayMongo sends webhook to /api/webhook/paymongo
  → VerifyPayMongoSignature middleware validates `X-Paymongo-Signature` against `webhook_secret`
  → PayMongoWebhookController:
    → Checks `webhook_logs` for duplicate `event_id` (Idempotency)
    → Creates log with status 'pending'
    → Handlers:
      → checkout_session.payment.paid — updates Transaction status to 'paid'
      → payment.paid — fallback payment confirmation
      → payment.failed — marks transaction as 'failed'
      → payment.refunded — marks transaction as 'refunded'
    → On success: Updates log to 'processed'
    → If paid: Dispatches `SendGhlWebhookJob` (Queue) to send `payment.captured` event to GHL (includes automatic retries on failure)
```

### 4. GHL Query Handler

```
GHL sends POST to /api/webhook/ghl-query with { type: "..." }
  → QueryController dispatches by type:
    → "verify" — Confirms payment status (Checks DB for `paid`, `refunded`, or `partially_refunded` first)
    → "refund" — Processes refund via PayMongo, updates DB
    → "list_payment_methods" — Placeholder (returns empty array)
    → "charge_payment" — Placeholder (not yet implemented)
```

---

## Database Schema

### `location_tokens`

| Column        | Type            | Description                                  |
| ------------- | --------------- | -------------------------------------------- |
| location_id   | string (unique) | GHL sub-account location ID                  |
| access_token  | text            | GHL OAuth access token (Encrypted)           |
| refresh_token | text            | GHL OAuth refresh token (Encrypted)          |
| expires_at    | timestamp       | Token expiry (Auto-refreshed via middleware) |
| user_type     | string          | Usually "Location"                           |

### `transactions`

| Column              | Type            | Description                                  |
| ------------------- | --------------- | -------------------------------------------- |
| checkout_session_id | string (unique) | PayMongo checkout session ID                 |
| payment_intent_id   | string          | PayMongo payment intent ID                   |
| payment_id          | string          | PayMongo payment ID                          |
| ghl_transaction_id  | string          | GHL transaction reference                    |
| ghl_order_id        | string          | GHL order reference                          |
| ghl_invoice_id      | string          | GHL invoice reference                        |
| ghl_location_id     | string          | GHL location ID                              |
| amount              | integer         | Amount in cents (centavos)                   |
| currency            | string(3)       | Default: PHP                                 |
| description         | string          | Payment description                          |
| status              | string          | pending / paid / failed / refunded / expired |
| payment_method      | string          | card / qrph / gcash / grab_pay / paymaya     |
| customer_name       | string          | Customer name                                |
| customer_email      | string          | Customer email                               |
| metadata            | json            | Raw webhook data                             |
| paid_at             | timestamp       | When payment was confirmed                   |

### `webhook_logs`

| Column        | Type            | Description                            |
| ------------- | --------------- | -------------------------------------- |
| event_id      | string (unique) | PayMongo unique event ID               |
| event_type    | string          | Type of webhook event                  |
| payload       | json            | Full raw payload                       |
| status        | string          | pending / processed / failed / skipped |
| error_message | text            | If failed, why                         |

---

## Environment Variables

```env
# GHL OAuth
GHL_CLIENT_ID=
GHL_CLIENT_SECRET=
GHL_REDIRECT_URI=https://your-domain.com/oauth/callback
GHL_API_BASE=https://services.leadconnectorhq.com
GHL_API_VERSION=2021-07-28
GHL_MARKETPLACE_APP_ID=

# PayMongo
PAYMONGO_IS_PRODUCTION=false
PAYMONGO_TEST_SECRET_KEY=sk_test_xxx
PAYMONGO_TEST_PUBLISHABLE_KEY=pk_test_xxx
PAYMONGO_LIVE_SECRET_KEY=sk_live_xxx
PAYMONGO_LIVE_PUBLISHABLE_KEY=pk_live_xxx
PAYMONGO_WEBHOOK_SECRET=whsk_xxx
```

---

## External API References

### PayMongo API (v1)

- **Docs**: https://developers.paymongo.com/docs/introduction
- **API Reference**: https://developers.paymongo.com/reference
- **Base URL**: `https://api.paymongo.com/v1`
- **Auth**: HTTP Basic Auth (secret key as username, empty password)
- **Key Endpoints Used**:
    - `POST /checkout_sessions` — Create checkout session
    - `GET /checkout_sessions/{id}` — Retrieve session status
    - `GET /payment_intents/{id}` — Retrieve payment intent
    - `POST /refunds` — Create a refund
- **Payment Methods**: card, gcash, grab_pay, paymaya, qrph
- **Currency**: PHP (amounts in centavos)
- **Webhooks Handling (Events)**:
    - `checkout_session.payment.paid` — Fired when a checkout is completed (primary trigger)
    - `payment.paid` — Fired when a payment intent is completed (fallback)
    - `payment.failed` — Fired when a payment fails (e.g., declined card)
    - `payment.refunded` — Fired when a refund is fully or partially processed
- **Testing**: https://developers.paymongo.com/docs/testing (Provides test card numbers for basic, 3DS, and error scenarios like `card_expired`, `cvc_invalid`, `insufficient_funds`, etc.)

### GoHighLevel API (v2021-07-28)

- **Docs**: https://marketplace.gohighlevel.com/docs/
- **Integration Flow Guide**: https://help.gohighlevel.com/support/solutions/articles/155000002620-how-to-build-a-custom-payments-integration-on-the-platform
- **Base URL**: `https://services.leadconnectorhq.com`
- **Auth**: Bearer token (OAuth2 — Location-level)
- **OAuth Scopes (Marketplace App)**:
    - `payments/orders.readonly`
    - `payments/orders.write`
    - `payments/subscriptions.readonly`
    - `payments/transactions.readonly`
    - `payments/custom-provider.readonly`
    - `payments/custom-provider.write`
    - `products.readonly`
    - `products/prices.readonly`
- **Custom Provider Endpoints**:
    - `POST /payments/custom-provider/provider?locationId=` — Register provider
    - `DELETE /payments/custom-provider/provider?locationId=` — Delete provider
    - `POST /payments/custom-provider/connect?locationId=` — Push API keys
    - `POST /payments/custom-provider/webhook` — GHL's webhook endpoint for payment events
- **Custom Provider Lifecycle**:
    1. Register provider (name, queryUrl, paymentsUrl)
    2. Push API key config (live + test)
    3. GHL embeds paymentsUrl as iFrame during checkout
    4. GHL sends `payment_initiate_props` via postMessage
    5. App processes payment and responds with `custom_element_success_response` or `custom_element_error_response`
    6. GHL verifies via queryUrl (`type: verify`)
- **queryUrl Actions**: `verify`, `refund`, `list_payment_methods`, `charge_payment`

### Docker & Google Cloud Platform (GCP) Deployment

- **Hosting Target**: Google Cloud Run (Fully managed serverless container platform)
- **Database**: Google Cloud SQL (MySQL 8.0, connected via Unix sockets)
- **Deployment Flow**: Source Code → Google Cloud Build → Google Artifact Registry → Google Cloud Run
- **Live URLs:**
    - **Service URL:** `https://paymongo-app-205396437939.us-central1.run.app`
    - **GCP Project ID:** `nola-paymongo`
    - **Region:** `us-central1`

**How to Redeploy After Code Changes:**
If you make any changes to the code (PHP, JS, CSS, or Dockerfile), you need to perform a two-step "Build and Release" process to update the live site:

```bash
# STEP 1: Build & Upload the New Image (The "Build" phase)
# This command zips your code, builds the container in the cloud, and saves it to Artifact Registry.
gcloud builds submit --tag us-central1-docker.pkg.dev/nola-paymongo/paymongo-repo/paymongo-app --project=nola-paymongo

# STEP 2: Release to Cloud Run (The "Deploy" phase)
# This command tells Cloud Run to take the image we just built and swap out the old version of your site for the new one.
gcloud run deploy paymongo-app \
  --image us-central1-docker.pkg.dev/nola-paymongo/paymongo-repo/paymongo-app \
  --region us-central1 \
  --project=nola-paymongo
```

**How to Update Production Environment Variables:**
If you only need to change a setting (like an API Key or Webhook Secret) without changing code, you don't need to rebuild. Just update the service configuration:

```bash
# Example: Updating the PayMongo Live Webhook Secret
gcloud run services update paymongo-app \
  --region us-central1 \
  --project nola-paymongo \
  --update-env-vars="PAYMONGO_WEBHOOK_SECRET=whsk_new_secret_here"
```

_Note: Updating an environment variable alone will also trigger a new revision (redeploy) automatically._

---

## Known Issues & Debugging Notes

1. **Handshake timeout**: GHL sometimes misses the first `custom_provider_ready` — solved with heartbeat interval
2. **Popup blocker**: If browser blocks popup, falls back to redirect
3. **`list_payment_methods` and `charge_payment`**: Placeholder only — not yet implemented (card vaulting)
4. **Provider config uses `.env` keys**: PayMongo keys are pushed from server env, not user-input
5. **Security**: HTTPS enforced in non-local/testing via `EnsureHttps` middleware; rate limits on checkout (30/min) and webhooks (120/min); CSRF excluded only for `checkout/create-session`; PayMongo signature verified; structured logs in `storage/logs/laravel.log` (consolidated)
