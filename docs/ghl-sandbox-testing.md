# End-to-End Testing Guide (GHL Sandbox)

This guide walks you through testing the entire **NOLA PayMongo × GoHighLevel** integration using a GoHighLevel Sandbox location and PayMongo test API keys.

---

## Prerequisites

1. **A GoHighLevel Agency Account** with a dedicated Sandbox Location.
2. **A PayMongo Account** in Test Mode.
3. This Laravel application running locally via **ngrok** (or hosted on a publicly accessible test server).

### 1. Local Application Setup

You must expose your local Laravel environment to the internet so GHL can send OAuth callbacks and PayMongo can send webhooks.

1. Start your local server: `php artisan serve`
2. Start an ngrok tunnel pointing to that port: `ngrok http 8000`
3. Copy your `ngrok` URL (e.g., `https://8a7b-123-456.ngrok-free.app`).

Update your `.env` file with the ngrok URL:

```env
APP_URL=https://your-ngrok-url.ngrok-free.app

# GHL Marketplace App Settings
GHL_REDIRECT_URI=https://your-ngrok-url.ngrok-free.app/oauth/callback
```

_Don't forget to restart your Laravel server to pick up the changes, or run `php artisan config:clear`._

---

## Step 1: Configure GHL Marketplace App Settings

1. Go to your GoHighLevel Marketplace Developer Portal.
2. Select your App.
3. In **App settings**, ensure your **Redirect URL** exactly matches your `.env` (e.g., `https://.../oauth/callback`).
4. Ensure the **Custom Page** or setup URL is also pointing to your application correctly if configured.
5. In **Scopes**, ensure the following are approved:
    - `payments/orders.readonly`
    - `payments/orders.write`
    - `payments/subscriptions.readonly`
    - `payments/transactions.readonly`
    - `payments/custom-provider.readonly`
    - `payments/custom-provider.write`
    - `products.readonly`
    - `products/prices.readonly`

---

## Step 2: PayMongo Webhook Setup

1. Log in to the PayMongo Developer Dashboard (ensure **Test Mode** is toggled ON).
2. Go to **Developers > Webhooks**.
3. Create a new webhook pointing to: `https://your-ngrok-url.app/api/webhook/paymongo`
4. Select these events:
    - `checkout_session.payment.paid`
    - `payment.paid`
    - `payment.failed`
    - `payment.refunded`
5. Save and view the webhook details. Copy the **Webhook Secret Key** (`whsk_...`).
6. Update your `.env`:
    ```env
    PAYMONGO_WEBHOOK_SECRET=whsk_test_...
    ```

---

## Step 3: OAuth & Provider Registration

1. Go to your GHL Agency Dashboard > **Settings** > **Custom Menus** or directly install the app from your Marketplace Portal using your private link.
2. Choose your **Test Location/Sandbox** when prompted.
3. The OAuth flow will redirect you back to your Laravel application (`/provider/config`).
4. On your Custom Provider Config page, click **"Connect NOLA PayMongo Provider"**.
5. You should see a success response.
   _Behind the scenes: Your app just pushed your configured `.env` PayMongo keys (Test and Live) to GHL's Connect Config API._

---

## Step 4: Configure a Product in GHL

1. Switch into your **Sandbox Location** in GHL.
2. Navigate to **Payments** > **Products**.
3. Create a new product (e.g., "Test Consulting Service").
4. Ensure the currency is set to **PHP** (Philippine Peso), as this is PayMongo's primary currency for all local methods.
5. Set a price (e.g., PHP 100).

---

## Step 5: Test the Checkout iFrame flow

1. In GHL, go to **Sites** > **Funnels** or **Websites**.
2. Create a test page.
3. Add a **One-Step or Two-Step Order Form** element to the page.
4. Go to the "Products" tab for that funnel step and link the product you created in Step 4.
5. Preview the live page.
6. Fill in the dummy customer details (Name, Email, Phone, Address).
7. Submit step 1. You should now see the payment methods section.
8. **NOLA PayMongo** should appear as a selectable payment option in the iframe.

### Executing the Payment

1. Select the NOLA PayMongo option and click Place Order/Submit.
2. A popup window should open loading the **PayMongo Hosted Checkout** page.
3. Use a PayMongo Test Card:
    - **Card Number**: `4242 4242 4242 4242`
    - **Expiry Date**: Any future date (e.g., `12/26`)
    - **CVC**: Any 3 digits (`123`)
4. Click **Pay**.
5. The popup will handle the 3DS test prompt. Select "Authenticate".
6. The popup will automatically close and the GHL Order form will transition to the Success/Thank You page!

---

## Step 6: Verify Database & Webhooks

### 1. Check Laravel Transations

In your local database UI (e.g., TablePlus, phpMyAdmin), check the `transactions` table.

- You should see the recent checkout session.
- The `status` should be `paid`.
- `ghl_order_id` and `ghl_transaction_id` should be populated.

### 2. Check PayMongo Dashboard

- In PayMongo Test Mode, go to **Payments**.
- You should see the successful test card charge.

### 3. Check Webhook Logs

Check the `webhook_logs` table in your database.

- You should see the `checkout_session.payment.paid` event logged with `status` = `processed`.

---

## Step 7: Test Refunds (End-to-End)

1. In GHL, navigate to **Payments** > **Transactions**.
2. Locate the successful transaction you just made.
3. Click the 3 dots (action menu) and select **Refund**.
4. Issue a **Partial** or **Full Refund**.
5. Check your `laravel.log` or your Database.
    - The transaction status should update to `partially_refunded` or `refunded`.
    - In PayMongo Test Mode, the payment should show as refunded under the Payments tab.

---

> 🎉 **If all the above steps succeed, your integration is fully verified and ready for production deployment.**
