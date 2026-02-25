<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Secure Checkout</title>
   <meta name="csrf-token" content="{{ csrf_token() }}">

   <!-- Tailwind CSS for minimal styling -->
   <script src="https://cdn.tailwindcss.com"></script>

   <style>
      /* Ensure full screen without scrollbars */
      html,
      body {
         margin: 0;
         padding: 0;
         width: 100%;
         height: 100%;
         overflow: hidden;
         background-color: transparent;
      }

      /* Simple loading spinner */
      .spinner {
         width: 40px;
         height: 40px;
         border: 4px solid rgba(0, 0, 0, 0.1);
         border-left-color: #0ea5e9;
         /* Light blue */
         border-radius: 50%;
         animation: spin 1s linear infinite;
      }

      @keyframes spin {
         0% {
            transform: rotate(0deg);
         }

         100% {
            transform: rotate(360deg);
         }
      }
   </style>
</head>

<body class="flex items-center justify-center p-0 m-0 bg-transparent">

   <!-- Initial loading state -->
   <div id="loader"
      class="flex flex-col items-center justify-center w-full h-full absolute inset-0 bg-white/50 backdrop-blur-sm z-10 transition-opacity duration-300">
      <div class="spinner mb-4"></div>
      <p class="text-sm font-medium text-slate-500 font-sans tracking-wide">Initializing secure checkout...</p>
   </div>

   <!-- Iframe container: Transparent and fills the entire screen -->
   <div id="iframeContainer" class="w-full h-full hidden absolute inset-0 z-20">
      <iframe id="checkoutIframe" class="w-full h-full border-0 bg-transparent"
         sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-top-navigation allow-popups-to-escape-sandbox"></iframe>
   </div>

   <!-- Error Fallback (Minimal) -->
   <div id="errorBox"
      class="hidden flex-col items-center justify-center w-full h-full absolute inset-0 bg-white z-30 px-6 text-center">
      <div class="w-12 h-12 bg-red-100 text-red-500 rounded-full flex items-center justify-center mb-4">
         <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round"
               d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
         </svg>
      </div>
      <h3 class="text-lg font-bold text-slate-800 mb-2">Checkout Interrupted</h3>
      <p id="errorMsg" class="text-slate-600 text-sm mb-6 max-w-sm">There was a problem initiating your secure checkout
         session.</p>
      <button onclick="retryPayment()"
         class="bg-slate-900 hover:bg-slate-800 text-white font-medium py-2.5 px-5 rounded-lg transition-colors text-sm">
         Try Again
      </button>
   </div>

   <script>
      let paymentData = null;
      let currentCheckoutSessionId = null;
      const CREATE_SESSION_URL = '{{ url("/checkout/create-session") }}';
      const STATUS_URL_BASE = '{{ url("/checkout/status") }}';
      const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

      // 1. Send ready event to GHL parent
      function sendReadyEvent() {
         const msg = { type: 'custom_provider_ready', loaded: true, addCardOnFileSupported: false };
         window.parent.postMessage(msg, '*');
         window.parent.postMessage(JSON.stringify(msg), '*');
      }

      // 2. Listen for GHL messages containing init data
      window.addEventListener('message', function (event) {
         // Because this app will be used across many different GHL accounts and custom funnel domains,
         // we cannot strictly wildcard match every valid origin.
         // We will accept the initialization payload and let the backend validate the location_id and API keys.
         let data = event.data;

         try {
            if (typeof data === 'string') data = JSON.parse(data);
         } catch (e) { }

         if (!data || typeof data !== 'object') return;

         // Check standard structure
         if (['payment_initiate_props', 'setup_initiate_props', 'payment-initiate-props'].includes(data.type)) {
            paymentData = data;
            createCheckoutSession(data);
            return;
         }

         // Check nested structure
         if (data.data && typeof data.data === 'object') {
            if (['payment_initiate_props', 'payment-initiate-props'].includes(data.data.type) || (data.data.amount !== undefined && !paymentData)) {
               paymentData = data.data;
               createCheckoutSession(data.data);
               return;
            }
         }

         // Fallbacks
         if (data.amount !== undefined && data.currency !== undefined && !paymentData) {
            paymentData = data;
            createCheckoutSession(data);
         }

         // 2.a Listen for messages from the checkout iFrame (success/cancel)
         if (data.type === 'checkout_success') {
            const chargeId = currentCheckoutSessionId;
            // Shorter delay since the user is already looking at the success page
            setTimeout(() => {
               notifyGhlSuccess(chargeId);
            }, 3000);
         }

         if (data.type === 'checkout_cancelled') {
            // Give the user a moment to see the cancellation page
            setTimeout(() => {
               document.getElementById('iframeContainer').classList.add('hidden');
               document.getElementById('checkoutIframe').src = '';
               showError('Payment was cancelled.');
               notifyGhlError('Payment not completed.');
            }, 3000);
         }
      });

      // 3. Create Session via our Backend
      async function createCheckoutSession(data) {
         showLoader("Generating secure link...");

         try {
            const amount = data.amount || 0;
            const currency = (data.currency || 'PHP').toUpperCase();

            let description = data.description || 'Payment';
            let validProductDetails = [];
            if (data.productDetails && Array.isArray(data.productDetails)) {
               validProductDetails = data.productDetails.filter(p => p && (p.name || typeof p.price !== 'undefined') && Object.keys(p).length > 0);
               if (validProductDetails.length > 0) description = validProductDetails.map(p => p.name || 'Product').join(', ');
            }

            let address = null;
            if (data.contact && (data.contact.address1 || data.contact.city || data.contact.country)) {
               address = {
                  line1: data.contact.address1 || '',
                  line2: data.contact.address2 || '',
                  city: data.contact.city || '',
                  state: data.contact.state || '',
                  postal_code: data.contact.postalCode || '',
                  country: data.contact.country || ''
               };
            }

            const response = await fetch(CREATE_SESSION_URL, {
               method: 'POST',
               headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': CSRF_TOKEN,
                  'Accept': 'application/json'
               },
               body: JSON.stringify({
                  amount: Math.round(amount * 100),
                  currency: currency,
                  description: description,
                  name: (data.contact && data.contact.name) || 'Customer',
                  email: (data.contact && data.contact.email) || '',
                  phone: (data.contact && data.contact.phone) || '',
                  address: address,
                  transaction_id: data.transactionId || '',
                  order_id: data.orderId || '',
                  location_id: data.locationId || '',
                  publishable_key: data.publishableKey || '',
                  product_details: validProductDetails
               })
            });

            if (!response.ok) {
               const err = await response.json().catch(() => ({}));
               throw new Error(err.error || 'Failed to initialize payment.');
            }

            const result = await response.json();
            currentCheckoutSessionId = result.checkout_session_id;

            loadIframe(result.checkout_url);

         } catch (error) {
            showError(error.message);
            notifyGhlError(error.message);
         }
      }

      // 4. Load the Iframe seamlessly
      function loadIframe(url) {
         const loader = document.getElementById('loader');
         const container = document.getElementById('iframeContainer');
         const iframe = document.getElementById('checkoutIframe');

         // Wait briefly to ensure iframe is rendering before hiding loader
         iframe.onload = () => {
            loader.classList.add('opacity-0');
            setTimeout(() => { loader.classList.add('hidden'); }, 300);
         };

         iframe.src = url;
         container.classList.remove('hidden');

         // Start polling silently
         const pollInterval = setInterval(async () => {
            const isFinished = await checkPaymentStatusSilently();
            if (isFinished) clearInterval(pollInterval);
         }, 3000);

         // Hard timeout
         setTimeout(() => {
            clearInterval(pollInterval);
            if (!container.classList.contains('hidden')) {
               showError('The session timed out.');
            }
         }, 1800000); // 30 mins
      }

      // 5. Silent Polling
      async function checkPaymentStatusSilently() {
         if (!currentCheckoutSessionId) return true;

         try {
            const response = await fetch(`${STATUS_URL_BASE}/${currentCheckoutSessionId}`, {
               headers: { 'Accept': 'application/json' }
            });
            if (!response.ok) return false;

            const result = await response.json();

            if (result.status === 'paid') {
               const chargeId = result.charge_id || currentCheckoutSessionId;
               // Wait 15 seconds to allow the user to see the PayMongo success UI
               // before telling GHL to navigate away.
               setTimeout(() => {
                  notifyGhlSuccess(chargeId);
               }, 15000);
               return true; // Stop polling
            }

            if (['expired', 'failed', 'cancelled'].includes(result.status)) {
               document.getElementById('iframeContainer').classList.add('hidden');
               document.getElementById('checkoutIframe').src = '';
               showError('Payment was interrupted or expired.');
               notifyGhlError('Payment not completed.');
               return true; // Stop polling
            }
         } catch (e) {
            // Ignore network dropouts and keep polling
         }
         return false;
      }

      // Helpers
      function notifyGhlSuccess(chargeId) {
         const msg = { type: 'custom_element_success_response', chargeId: chargeId };
         window.parent.postMessage(msg, '*');
         window.parent.postMessage(JSON.stringify(msg), '*');
         if (window.top !== window.parent) {
            window.top.postMessage(msg, '*');
            window.top.postMessage(JSON.stringify(msg), '*');
         }
      }

      function notifyGhlError(message) {
         const msg = { type: 'custom_element_error_response', error: { description: message } };
         window.parent.postMessage(msg, '*');
         window.parent.postMessage(JSON.stringify(msg), '*');
         if (window.top !== window.parent) {
            window.top.postMessage(msg, '*');
            window.top.postMessage(JSON.stringify(msg), '*');
         }
      }

      function showLoader(text) {
         const loader = document.getElementById('loader');
         loader.querySelector('p').textContent = text;
         loader.classList.remove('hidden', 'opacity-0');
      }

      function showError(msg) {
         document.getElementById('loader').classList.add('hidden');
         document.getElementById('iframeContainer').classList.add('hidden');
         const errorBox = document.getElementById('errorBox');
         document.getElementById('errorMsg').textContent = msg;
         errorBox.classList.remove('hidden');
      }

      function retryPayment() {
         if (paymentData) {
            document.getElementById('errorBox').classList.add('hidden');
            createCheckoutSession(paymentData);
         }
      }

      // Initialization Timeout
      const readyHeartbeat = setInterval(() => {
         if (!paymentData) sendReadyEvent();
         else clearInterval(readyHeartbeat);
      }, 1000);

      setTimeout(() => {
         if (!paymentData) {
            clearInterval(readyHeartbeat);
            showError('Could not establish connection to the checkout interface.');
         }
      }, 15000);

   </script>
</body>

</html>