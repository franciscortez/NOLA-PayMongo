<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>PayMongo Checkout</title>
   <meta name="csrf-token" content="{{ csrf_token() }}">
   <style>
      * {
         margin: 0;
         padding: 0;
         box-sizing: border-box;
      }

      body {
         font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
         background: #f8f9fa;
         color: #1a1a2e;
         min-height: 100vh;
         display: flex;
         align-items: center;
         justify-content: center;
         padding: 16px;
         padding-bottom: 220px;
      }

      .container {
         text-align: center;
         max-width: 400px;
      }

      .spinner {
         width: 40px;
         height: 40px;
         border: 3px solid #e2e8f0;
         border-top-color: #4f46e5;
         border-radius: 50%;
         animation: spin 0.8s linear infinite;
         margin: 0 auto 16px;
      }

      @keyframes spin {
         to {
            transform: rotate(360deg);
         }
      }

      .status-text {
         color: #64748b;
         font-size: 14px;
      }

      .error-box {
         background: #fef2f2;
         color: #dc2626;
         border: 1px solid #fecaca;
         border-radius: 8px;
         padding: 16px;
         margin-top: 16px;
         font-size: 14px;
         display: none;
      }

      .error-box.visible {
         display: block;
      }

      .success-box {
         background: #f0fdf4;
         color: #16a34a;
         border: 1px solid #bbf7d0;
         border-radius: 8px;
         padding: 16px;
         margin-top: 16px;
         font-size: 14px;
         display: none;
      }

      .success-box.visible {
         display: block;
      }

      .retry-btn {
         display: none;
         margin-top: 12px;
         padding: 10px 24px;
         background: #4f46e5;
         color: #fff;
         border: none;
         border-radius: 6px;
         font-size: 14px;
         cursor: pointer;
      }

      .retry-btn:hover {
         background: #4338ca;
      }

      .retry-btn.visible {
         display: inline-block;
      }
   </style>
</head>

<body>
   <div class="container">
      <div class="spinner" id="spinner"></div>
      <p class="status-text" id="statusText">Preparing checkout...</p>
      <div class="error-box" id="errorBox"></div>
      <div class="success-box" id="successBox">Payment successful! This window will close shortly.</div>
      <button class="retry-btn" id="retryBtn" onclick="retryPayment()">Try Again</button>
   </div>

   <script>
      let paymentData = null;
      let currentCheckoutSessionId = null;
      const CREATE_SESSION_URL = '{{ url("/checkout/create-session") }}';
      const STATUS_URL_BASE = '{{ url("/checkout/status") }}';
      const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

      // 1. Send ready event to GHL parent in MULTIPLE formats
      function sendReadyEvent() {
         const readyMsgs = [
            { type: 'custom_provider_ready', loaded: true, addCardOnFileSupported: false },
            { type: 'custom-provider-ready', loaded: true, addCardOnFileSupported: false },
            { loaded: true, addCardOnFileSupported: false },
         ];
         readyMsgs.forEach(function (msg) {
            // Send as object
            window.parent.postMessage(msg, '*');
            // Send as string
            window.parent.postMessage(JSON.stringify(msg), '*');

            if (window.top !== window.parent) {
               window.top.postMessage(msg, '*');
               window.top.postMessage(JSON.stringify(msg), '*');
            }
         });
      }

      // 2. Listen for GHL messages (handle ALL possible formats)
      window.addEventListener('message', function (event) {
         var rawStr;
         try {
            rawStr = JSON.stringify(event.data);
         } catch (e) {
            rawStr = String(event.data);
         }

         var data = event.data;

         // Handle stringified JSON
         if (typeof data === 'string') {
            try {
               data = JSON.parse(data);
            } catch (e) {
               return;
            }
         }

         if (!data || typeof data !== 'object') return;

         // Standard GHL format: { type: 'payment_initiate_props', ... }
         if (data.type === 'payment_initiate_props' || data.type === 'setup_initiate_props') {
            paymentData = data;
            createCheckoutSession(data);
            return;
         }

         // Hyphen variant
         if (data.type === 'payment-initiate-props' || data.type === 'setup-initiate-props') {
            paymentData = data;
            createCheckoutSession(data);
            return;
         }

         // Wrapped: { data: { type: 'payment_initiate_props', ... } }
         if (data.data && typeof data.data === 'object') {
            var inner = data.data;
            if (inner.type === 'payment_initiate_props' || inner.type === 'payment-initiate-props') {
               paymentData = inner;
               createCheckoutSession(inner);
               return;
            }
            // Inner has amount — treat it as payment data
            if (inner.amount !== undefined && !paymentData) {
               paymentData = inner;
               createCheckoutSession(inner);
               return;
            }
         }

         // Direct payment data with amount + currency (no type field)
         if (data.amount !== undefined && data.currency !== undefined && !paymentData) {
            paymentData = data;
            createCheckoutSession(data);
            return;
         }

         // Catch-all: has transactionId + amount (strong signal it's payment data)
         if (data.transactionId && data.amount !== undefined && !paymentData) {
            paymentData = data;
            createCheckoutSession(data);
            return;
         }
      });

      // 3. Create Checkout Session and open in popup
      async function createCheckoutSession(data) {
         document.getElementById('statusText').textContent = 'Creating payment session...';

         try {
            const amount = data.amount || 0;
            const currency = (data.currency || 'PHP').toUpperCase();
            const amountInCents = Math.round(amount * 100);

            let description = 'Payment';
            if (data.productDetails && data.productDetails.length > 0) {
               description = data.productDetails.map(p => p.name).join(', ');
            }

            const name = (data.contact && data.contact.name) || 'Customer';
            const email = (data.contact && data.contact.email) || 'customer@example.com';
            const phone = (data.contact && data.contact.phone) || '';

            const response = await fetch(CREATE_SESSION_URL, {
               method: 'POST',
               headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': CSRF_TOKEN,
                  'Accept': 'application/json'
               },
               body: JSON.stringify({
                  amount: amountInCents,
                  currency: currency,
                  description: description,
                  name: name,
                  email: email,
                  phone: phone,
                  transaction_id: data.transactionId || '',
                  order_id: data.orderId || '',
                  location_id: data.locationId || ''
               })
            });

            if (!response.ok) {
               const err = await response.json().catch(() => ({}));
               throw new Error(err.error || 'Failed to create checkout session');
            }

            const result = await response.json();
            currentCheckoutSessionId = result.checkout_session_id;

            // Open PayMongo checkout in a popup window instead of redirecting the iFrame
            openCheckoutPopup(result.checkout_url);

         } catch (error) {
            showError(error.message);
            notifyGhlError(error.message);
         }
      }

      // 4. Open PayMongo in popup and poll for completion
      function openCheckoutPopup(checkoutUrl) {
         document.getElementById('statusText').textContent = 'Complete payment in the popup window...';
         document.getElementById('spinner').style.display = 'none';

         const width = 500;
         const height = 700;
         const left = (screen.width - width) / 2;
         const top = (screen.height - height) / 2;

         const popup = window.open(
            checkoutUrl,
            'paymongo_checkout',
            `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`
         );

         if (!popup || popup.closed) {
            // Popup was blocked — fall back to redirect
            document.getElementById('statusText').textContent = 'Redirecting to payment...';
            document.getElementById('spinner').style.display = 'block';
            window.location.href = checkoutUrl;
            return;
         }

         // Poll: check if popup closed, then verify payment status
         const pollInterval = setInterval(async () => {
            if (popup.closed) {
               clearInterval(pollInterval);
               document.getElementById('spinner').style.display = 'block';
               document.getElementById('statusText').textContent = 'Verifying payment...';
               await checkPaymentStatus();
            }
         }, 1000);

         // Safety timeout: stop polling after 10 minutes
         setTimeout(() => {
            clearInterval(pollInterval);
         }, 600000);
      }

      // 5. Check payment status after popup closes
      async function checkPaymentStatus() {
         if (!currentCheckoutSessionId) {
            showError('No checkout session to verify');
            notifyGhlError('No checkout session to verify');
            return;
         }

         // Retry a few times (webhook might take a moment to update the DB)
         const maxAttempts = 5;
         const delayMs = 2000;

         for (let attempt = 1; attempt <= maxAttempts; attempt++) {
            try {
               const response = await fetch(`${STATUS_URL_BASE}/${currentCheckoutSessionId}`, {
                  headers: { 'Accept': 'application/json' }
               });

               if (!response.ok) {
                  throw new Error('Status check failed');
               }

               const result = await response.json();

               if (result.status === 'paid') {
                  // Success! Notify GHL
                  const chargeId = result.charge_id || currentCheckoutSessionId;
                  showSuccess();
                  notifyGhlSuccess(chargeId);
                  return;
               }

               if (result.status === 'expired') {
                  showError('Payment session expired. Please try again.');
                  showRetryButton();
                  notifyGhlError('Payment session expired');
                  return;
               }

               // Still pending — wait and retry
               if (attempt < maxAttempts) {
                  await sleep(delayMs);
               }

            } catch (error) {
               if (attempt < maxAttempts) {
                  await sleep(delayMs);
               }
            }
         }

         // After all attempts, status is still not "paid"
         // The payment may still complete via webhook — show a neutral message
         document.getElementById('spinner').style.display = 'none';
         document.getElementById('statusText').textContent =
            'Payment status is pending. If you completed the payment, it will be processed shortly.';
         showRetryButton();
      }

      // 6. Retry payment
      function retryPayment() {
         if (paymentData) {
            hideError();
            hideRetryButton();
            document.getElementById('spinner').style.display = 'block';
            createCheckoutSession(paymentData);
         }
      }

      // ===== Helpers =====

      function notifyGhlSuccess(chargeId) {
         window.parent.postMessage({
            type: 'custom_element_success_response',
            chargeId: chargeId
         }, '*');
      }

      function notifyGhlError(message) {
         window.parent.postMessage({
            type: 'custom_element_error_response',
            error: { description: message }
         }, '*');
      }

      function showError(msg) {
         document.getElementById('spinner').style.display = 'none';
         document.getElementById('statusText').textContent = 'Payment Error';
         const box = document.getElementById('errorBox');
         box.textContent = msg;
         box.classList.add('visible');
      }

      function hideError() {
         document.getElementById('errorBox').classList.remove('visible');
      }

      function showSuccess() {
         document.getElementById('spinner').style.display = 'none';
         document.getElementById('statusText').textContent = 'Payment Successful!';
         document.getElementById('successBox').classList.add('visible');
      }

      function showRetryButton() {
         document.getElementById('retryBtn').classList.add('visible');
      }

      function hideRetryButton() {
         document.getElementById('retryBtn').classList.remove('visible');
      }

      function sleep(ms) {
         return new Promise(resolve => setTimeout(resolve, ms));
      }

      // 7. Init
      (function init() {
         // HEARTBEAT: GHL sometimes misses the first "ready" event if the iframe loads too fast.
         const readyHeartbeat = setInterval(function () {
            if (!paymentData) {
               sendReadyEvent();
            } else {
               clearInterval(readyHeartbeat);
            }
         }, 1000);

         // If no response from GHL within 30 seconds, show a message
         setTimeout(function () {
            if (!paymentData) {
               clearInterval(readyHeartbeat);
               showError('Wait timed out: No session data received from GoHighLevel.');
            }
         }, 30000);
      })();
   </script>
</body>

</html>