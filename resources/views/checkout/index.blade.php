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
   </style>
</head>

<body>
   <div class="container">
      <div class="spinner" id="spinner"></div>
      <p class="status-text" id="statusText">Preparing checkout...</p>
      <div class="error-box" id="errorBox"></div>
   </div>

   <script>
      let paymentData = null;
      const CREATE_SESSION_URL = '{{ url("/checkout/create-session") }}';
      const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

      // 1. Send ready event to GHL parent
      function sendReadyEvent() {
         console.log('[Checkout] Sending custom_provider_ready to parent');
         window.parent.postMessage({ type: 'custom_provider_ready', loaded: true }, '*');
      }

      // 2. Listen for GHL messages
      window.addEventListener('message', function (event) {
         console.log('[Checkout] Received message:', event.data);
         const data = event.data;
         if (!data || !data.type) return;
         if (data.type === 'payment_initiate_props') {
            paymentData = data;
            createCheckoutSession(data);
         }
      });

      // 3. Create Checkout Session and redirect
      async function createCheckoutSession(data) {
         document.getElementById('statusText').textContent = 'Redirecting to payment...';

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
            console.log('[Checkout] Session created, redirecting:', result.checkout_url);

            // Redirect to PayMongo's hosted checkout
            window.location.href = result.checkout_url;

         } catch (error) {
            console.error('[Checkout] Error:', error);
            showError(error.message);
            window.parent.postMessage({
               type: 'custom_element_error_response',
               error: { description: error.message }
            }, '*');
         }
      }

      function showError(msg) {
         document.getElementById('spinner').style.display = 'none';
         document.getElementById('statusText').textContent = 'Payment Error';
         const box = document.getElementById('errorBox');
         box.textContent = msg;
         box.classList.add('visible');
      }

      // 4. Init
      (function init() {
         sendReadyEvent();

         // Demo/Test mode: if no GHL parent responds, use test data
         const params = new URLSearchParams(window.location.search);
         const isDemoMode = params.get('demo') === '1' || window.self === window.top;
         const demoAmount = parseFloat(params.get('amount')) || 50.00;
         const demoCurrency = params.get('currency') || 'PHP';

         setTimeout(() => {
            if (!paymentData) {
               console.log('[Checkout] Test mode activated');
               paymentData = {
                  type: 'payment_initiate_props',
                  amount: demoAmount,
                  currency: demoCurrency,
                  productDetails: [{ name: 'Test Product' }],
                  contact: { name: 'Test User', email: 'test@example.com' },
                  orderId: 'demo_' + Date.now(),
                  transactionId: 'demo_txn_' + Date.now(),
                  locationId: 'demo'
               };
               createCheckoutSession(paymentData);
            }
         }, isDemoMode ? 1500 : 5000);
      })();
   </script>
</body>

</html>