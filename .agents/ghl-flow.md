# How to build a custom payments integration on the platform

> **Source**: [HighLevel Support Portal](https://help.gohighlevel.com/support/solutions/articles/155000002620-how-to-build-a-custom-payments-integration-on-the-platform)

## 1. Overview

HighLevel’s custom payment provider framework lets you integrate any payment gateway with the platform and use it wherever HighLevel processes payments. Once integrated, your payment provider can:

- Appear in the App Marketplace and Payments > Integrations
- Power one-time and recurring payments
- Handle off-session charges (with stored payment methods)
- Support manual subscriptions and subscription schedules
- Sync updates to HighLevel via webhooks

## 2. Key Concepts

- **Marketplace App**: The container for your integration in HighLevel’s Marketplace. It defines OAuth, scopes, category, and custom pages.
- **Custom Payment Provider**: The payments configuration that tells HighLevel your app is a payment provider and what payment types you support (one-time, recurring, off-session).
- **queryUrl**: A backend endpoint (your server) that HighLevel calls for all server-side payment operations (verify, list_payment_methods, charge_payment, create_subscription, cancel_subscription, refund).
- **paymentsUrl**: A public URL that HighLevel loads inside an iframe to collect payments from customers via your provider.
- **Custom Page**: A public URL that HighLevel loads inside an iframe in the Marketplace UI for configuration (API keys).
- **Test vs Live Mode**: Each location can have both a test and live configuration.

## 3. High-Level Integration Flow

1. Create a Marketplace App in the custom payment provider category.
2. Create a backend service that handles OAuth, webhooks, and queryUrl requests.
3. Create public pages for configuring the integration and collecting payments via iframe.
4. Configure test and live payment modes and test the flow end-to-end.

## 4. Step 1 – Create Your Marketplace App

Required scopes:

```text
payments/orders.readonly
payments/orders.write
payments/subscriptions.readonly
payments/transactions.readonly
payments/custom-provider.readonly
payments/custom-provider.write
products.readonly
products/prices.readonly
```

## 5. Step 2 – Implement Authentication & Installation Flow

1. HighLevel opens your redirect URL with an OAuth `code`.
2. Backend exchanges `code` for an access token.
3. HighLevel loads your Custom Page.
4. HighLevel expects an API call to create a **public provider config** (Name, description, imageUrl, locationId, queryUrl, paymentsUrl).

## 6. Step 3 – Connect Test & Live Configuration

Users configure test and live credentials (e.g. `apiKey` and `publishableKey`). HighLevel expects a test and live configuration update via the **Connect Config API**.

## 7. Step 4 – Implement the Checkout iFrame Integration

### 7.1 Ready Event: `custom_provider_ready`

Iframe sends:

```json
{
    "type": "custom_provider_ready",
    "loaded": true,
    "addCardOnFileSupported": true // Optional
}
```

### 7.2 Payment Initiation Event: `payment_initiate_props`

HighLevel sends:

```json
{
  "type": "payment_initiate_props",
  "publishableKey": "PUBLIC_KEY",
  "amount": 100,
  "currency": "USD",
  "mode": "payment", // "payment" or "subscription"
  "productDetails": [...],
  "contact": {...},
  "orderId": "ORDER_ID",
  "transactionId": "TRANSACTION_ID",
  "locationId": "LOCATION_ID"
}
```

### 7.3 Outcome Events

- **Success**: `{ "type": "custom_element_success_response", "chargeId": "GATEWAY_CHARGE_ID" }`
- **Failed**: `{ "type": "custom_element_error_response", "error": { "description": "Card was declined" } }`
- **Canceled**: `{ "type": "custom_element_close_response" }`

### 7.4 Verification Call: `type: "verify"`

HighLevel calls your `queryUrl`:

```json
{
    "type": "verify",
    "transactionId": "ghl_transaction_id",
    "apiKey": "YOUR_API_KEY",
    "chargeId": "gateway_charge_id"
}
```

Response: `{ "success": true }`, `{ "failed": true }`, or `{ "success": false }` (pending).

## 8. Step 5 – Saved Payment Methods & Manual Subscriptions

### 8.1 Setup Event: `setup_initiate_props`

When HighLevel sees `addCardOnFileSupported: true`, it can send:

```json
{
  "type": "setup_initiate_props",
  "publishableKey": "PUBLIC_KEY",
  "currency": "USD",
  "mode": "setup",
  "contact": {...},
  "locationId": "LOCATION_ID"
}
```

### 8.2 List Payment Methods: `list_payment_methods`

HighLevel calls `queryUrl`:

```json
{
    "locationId": "Ktkq...",
    "contactId": "W1nP...",
    "apiKey": "API_KEY",
    "type": "list_payment_methods"
}
```

Returns an array of saved cards (`id`, `type`, `title`, `subTitle`, `expiry`).

### 8.3 Charge Payment Method: `charge_payment`

HighLevel calls `queryUrl` for off-session charges:

```json
{
    "type": "charge_payment",
    "paymentMethodId": "payment_method_id",
    "contactId": "W1nP...",
    "transactionId": "...",
    "amount": 100.0,
    "currency": "USD",
    "apiKey": "API_KEY"
}
```

Returns `{ "success": true, "chargeId": "...", "chargeSnapshot": {...} }`.

### 8.4 Manual Subscriptions: `create_subscription`

HighLevel calls `queryUrl`:

```json
{
  "type": "create_subscription",
  "apiKey": "API_KEY",
  "locationId": "...",
  "contactId": "...",
  "paymentMethodId": "payment_method_id",
  "subscriptionId": "...",
  "transactionId": "...",
  "startDate": "2025-09-22",
  "amount": 100.0,
  "recurringAmount": "80.00",
  "productDetails": [...]
}
```

Returns `{ "success": true, "transaction": {...}, "subscription": {...} }`.

### 8.5 Cancel Subscription: `cancel_subscription`

HighLevel calls `queryUrl`:

```json
{
    "type": "cancel_subscription",
    "subscriptionId": "...",
    "apiKey": "API_KEY"
}
```

Returns `{ "status": "canceled" }`.

## 9. Step 6 – Refunds (`type: "refund"`)

HighLevel calls `queryUrl`:

```json
{
    "type": "refund",
    "amount": 50.0,
    "transactionId": "TRANSACTION_ID",
    "chargeId": "CHARGE_ID",
    "apiKey": "API_KEY"
}
```

Returns `{ "success": true, "message": "Refund successful", "id": "REFUND_ID", "amount": 50.0, "currency": "USD" }`.

## 10. Step 7 – Webhooks from HighLevel

Your system must POST to `https://backend.leadconnectorhq.com/payments/custom-provider/webhook`.
**Supported Events**:

- `payment.captured`
- `subscription.trialing`
- `subscription.active`
- `subscription.updated`
- `subscription.charged`

Payload includes `event`, `chargeId`, `ghlTransactionId`, `chargeSnapshot`, `locationId`, and `apiKey`.
