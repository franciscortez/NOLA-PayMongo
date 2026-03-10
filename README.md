# NOLA PayMongo × GoHighLevel Integration

This is a **Laravel 12** application that acts as a **Custom Payment Provider** bridge between the **GoHighLevel (GHL)** CRM and **PayMongo** (a Philippine payment gateway). It enables GoHighLevel sub-accounts to accept payments via PayMongo directly inside the GHL checkout flow.

---

## 🛠️ Tech Stack

- **Framework:** Laravel 12 (PHP 8.2+)
- **Database:** MySQL / SQLite (for testing)
- **Payment Gateway:** PayMongo API v1
- **CRM Platform:** GoHighLevel API v2021-07-28
- **Target Hosting:** Google Cloud Run (Containerized)

---

## 🏗️ Architecture Overview

The system operates as a man-in-the-middle between GoHighLevel and PayMongo:

1.  **OAuth Connection:** GHL Sub-accounts connect to the App via OAuth. The app stores an `access_token` and `refresh_token` per location.
2.  **Provider Configuration:** Valid PayMongo API keys (Live & Test) are pushed to GHL's Connect Config API.
3.  **iFrame Checkout:** When a customer buys something on a GHL funnel or pays an Invoice, GHL loads this app's `/checkout` view inside an iFrame.
4.  **Payment Processing:** The app generates a PayMongo Checkout Session and opens it in a pop-up window so the customer can pay (via Card, GCash, GrabPay, etc.).
5.  **Status Sync:** The app polls the payment status and fires success/error callbacks to the GHL iFrame.
6.  **Webhooks:** PayMongo sends asynchronous webhooks (`checkout_session.payment.paid`) to this app, which then forwards them to GHL as `payment.captured`. For invoices, it uses the `ghl_invoice_id` as the reference.
7.  **Refunds:** Refunds initiated in GHL are processed via PayMongo. The app ensures status persistence by verifying against its internal database and forwarding refund webhooks back to GHL.

---

## ⚙️ Environment Setup

Clone the repository and install dependencies:

```bash
composer install
npm install
npm run build
```

Configure your `.env` file from the example:

```bash
cp .env.example .env
php artisan key:generate
```

### Required Environment Variables

**GHL Custom App Keys:**

```env
GHL_CLIENT_ID=your_client_id
GHL_CLIENT_SECRET=your_client_secret
GHL_REDIRECT_URI=https://your-domain.com/oauth/callback
GHL_API_BASE=https://services.leadconnectorhq.com
GHL_API_VERSION=2021-07-28
```

**PayMongo Keys (Private App):**
Because this is built as a private app for a single org, central keys are used:

```env
PAYMONGO_IS_PRODUCTION=false
PAYMONGO_TEST_SECRET_KEY=sk_test_...
PAYMONGO_TEST_PUBLISHABLE_KEY=pk_test_...
PAYMONGO_LIVE_SECRET_KEY=sk_live_...
PAYMONGO_LIVE_PUBLISHABLE_KEY=pk_live_...
PAYMONGO_WEBHOOK_SECRET=whsk_...
```

Run database migrations:

```bash
php artisan migrate
```

---

## � Local Development (Docker)

You can run the entire application stack locally using Docker Compose. This will automatically spin up the Laravel App (port 8000), a MySQL Database (port 3306), and PhpMyAdmin (port 8081).

1.  **Clone and prepare the environment:**

    ```bash
    cp .env.example .env
    ```

2.  **Update `.env` for Docker:**
    Ensure your database variables match the `docker-compose.yml` configuration:

    ```env
    DB_CONNECTION=mysql
    DB_HOST=db
    DB_PORT=3306
    DB_DATABASE=paymongo_ghl
    DB_USERNAME=laravel
    DB_PASSWORD=secret
    ```

3.  **Start the containers:**

    ```bash
    docker-compose up -d --build
    ```

4.  **Install dependencies and run migrations (inside the container):**
    ```bash
    docker-compose exec app composer install
    docker-compose exec app npm install
    docker-compose exec app npm run build
    docker-compose exec app php artisan key:generate
    docker-compose exec app php artisan migrate
    ```

Your local environment map:

- **Web App:** [http://localhost:8000](http://localhost:8000)
- **PhpMyAdmin:** [http://localhost:8081](http://localhost:8081)

---

## �📖 API Documentation

The application exposes routes for GHL OAuth, the iFrame checkout UI, and asynchronous webhooks.

### 1. OAuth & Setup Endpoints (Web)

#### `GET /oauth/callback`

Handles the OAuth redirect from the GoHighLevel marketplace.

- **Query Params**: `code` (string)
- **Action**: Exchanges code for access tokens and stores them in `location_tokens`. Redirects to `/provider/config`.

#### `GET /provider/config`

The configuration UI where users "Connect" or "Disconnect" PayMongo from their GHL sub-account.

- **Query Params**: `location_id` (string)

#### `POST /provider/register`

Registers the application as a Custom Payment Provider in the GHL Sub-account and pushes the API keys.

- **Body Params**: `location_id` (string)

#### `POST /provider/delete`

Removes the Custom Payment Provider configuration from the GHL Sub-account.

- **Body Params**: `location_id` (string)

### 2. Checkout Endpoints (Web)

#### `GET /checkout`

The primary UI loaded inside the GoHighLevel Funnel/Website iFrame.

#### `POST /checkout/create-session`

AJAX endpoint called by the checkout iFrame to generate a PayMongo checkout session link.

- **Body Params**:
    - `amount` (integer, in centavos)
    - `currency` (string, usually PHP)
    - `description` (string)
    - `name` (string, optional)
    - `email` (string, optional)
    - `location_id` (string)
    - `invoice_id` (string, optional) — Used for GHL Invoice payments
- **Returns**: `{ "checkout_url": "...", "checkout_session_id": "..." }`

#### `GET /checkout/status/{sessionId}`

AJAX endpoint used to silently poll for payment completion after the pop-up opens.

- **Returns**: `{ "status": "pending|paid|failed|expired" }`

### 3. Webhook Endpoints (API)

#### `POST /api/webhook/ghl-query`

GHL's `queryUrl` endpoint used for server-side verification and manual actions.

- **Body Params**:
    - `type`: "verify" | "refund"
    - `transactionId` (string) — Maps to GHL Transaction ID or Invoice ID
    - `chargeId` (string, optional)
    - `amount` (float, required for refund)
- **Returns**: `{ "success": true/false, ... }`

#### `POST /api/webhook/paymongo`

Receives asynchronous event updates directly from PayMongo.

- **Headers Expected**: `X-Paymongo-Signature` for validation.
- **Events Handled**:
    - `checkout_session.payment.paid`
    - `payment.paid`
    - `payment.failed`
    - `payment.refunded`
    - `payment.expired`
