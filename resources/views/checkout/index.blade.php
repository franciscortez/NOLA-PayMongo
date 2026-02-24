<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>PayMongo Checkout</title>
   <meta name="csrf-token" content="{{ csrf_token() }}">

   <!-- Google Fonts -->
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

   <!-- Tailwind CSS -->
   <script src="https://cdn.tailwindcss.com"></script>

   <!-- Tailwind Config & Custom Styles -->
   <script>
      tailwind.config = {
         theme: {
            extend: {
               fontFamily: {
                  sans: ['Inter', 'sans-serif'],
               },
               colors: {
                  brand: {
                     50: '#eef2ff',
                     100: '#e0e7ff',
                     500: '#6366f1',
                     600: '#4f46e5',
                     700: '#4338ca',
                  }
               },
               animation: {
                  'spin-slow': 'spin 1.5s linear infinite',
                  'fade-in': 'fadeIn 0.3s ease-out',
                  'slide-up': 'slideUp 0.4s ease-out forwards',
               },
               keyframes: {
                  fadeIn: {
                     '0%': { opacity: '0' },
                     '100%': { opacity: '1' },
                  },
                  slideUp: {
                     '0%': { opacity: '0', transform: 'translateY(10px)' },
                     '100%': { opacity: '1', transform: 'translateY(0)' },
                  }
               }
            }
         }
      }
   </script>
</head>

<body
   class="bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen text-slate-800 flex items-center justify-center p-4 pb-48 font-sans antialiased">

   <!-- Main Container -->
   <div class="w-full max-w-sm mx-auto text-center transform transition-all duration-300">

      <!-- Loading Spinner -->
      <div id="spinnerContainer" class="flex flex-col items-center justify-center animate-fade-in">
         <div class="relative w-14 h-14 mb-6">
            <div class="absolute inset-0 border-4 border-brand-100 rounded-full"></div>
            <div class="absolute inset-0 border-4 border-brand-500 rounded-full border-t-transparent animate-spin">
            </div>
         </div>
         <p class="text-slate-500 font-medium text-sm tracking-wide" id="statusText">Securely preparing checkout...</p>
      </div>

      <!-- Error State -->
      <div id="errorBox"
         class="hidden animate-slide-up bg-white/80 backdrop-blur-sm border border-red-100 shadow-xl shadow-red-500/5 rounded-2xl p-6 relative overflow-hidden ring-1 ring-black/5">
         <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-red-400 to-rose-500"></div>
         <div class="w-12 h-12 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
               stroke="currentColor" stroke-width="2">
               <path stroke-linecap="round" stroke-linejoin="round"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
         </div>
         <h3 class="text-slate-900 font-bold text-lg mb-2">Payment Interrupted</h3>
         <p id="errorMsg" class="text-slate-600 text-sm leading-relaxed mb-6"></p>
         <button id="retryBtn" onclick="retryPayment()"
            class="w-full bg-brand-600 hover:bg-brand-700 text-white font-medium py-3 px-4 rounded-xl shadow-sm shadow-brand-500/20 transition-all duration-200 hover:shadow-md hover:shadow-brand-500/30 active:scale-[0.98]">
            Try Again
         </button>
      </div>

      <!-- Success State -->
      <div id="successBox"
         class="hidden animate-slide-up bg-white/80 backdrop-blur-sm border border-emerald-100 shadow-xl shadow-emerald-500/5 rounded-2xl p-6 relative overflow-hidden ring-1 ring-black/5">
         <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-emerald-400 to-teal-500"></div>
         <div
            class="w-16 h-16 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-5 ring-8 ring-emerald-50/50">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
               stroke="currentColor" stroke-width="2">
               <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
         </div>
         <h3 class="text-slate-900 font-bold text-xl mb-2">Payment Successful!</h3>
         <p class="text-slate-500 text-sm leading-relaxed">Your order is confirmed. This window will close shortly.</p>
      </div>

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
            window.parent.postMessage(msg, '*');
            window.parent.postMessage(JSON.stringify(msg), '*');

            if (window.top !== window.parent) {
               window.top.postMessage(msg, '*');
               window.top.postMessage(JSON.stringify(msg), '*');
            }
         });
      }

      // 2. Listen for GHL messages
      window.addEventListener('message', function (event) {
         var rawStr;
         try {
            rawStr = JSON.stringify(event.data);
         } catch (e) {
            rawStr = String(event.data);
         }

         var data = event.data;

         if (typeof data === 'string') {
            try {
               data = JSON.parse(data);
            } catch (e) {
               return;
            }
         }

         if (!data || typeof data !== 'object') return;

         if (data.type === 'payment_initiate_props' || data.type === 'setup_initiate_props' || data.type === 'payment-initiate-props') {
            paymentData = data;
            createCheckoutSession(data);
            return;
         }

         if (data.data && typeof data.data === 'object') {
            var inner = data.data;
            if (inner.type === 'payment_initiate_props' || inner.type === 'payment-initiate-props' || (inner.amount !== undefined && !paymentData)) {
               paymentData = inner;
               createCheckoutSession(inner);
               return;
            }
         }

         if (data.amount !== undefined && data.currency !== undefined && !paymentData) {
            paymentData = data;
            createCheckoutSession(data);
            return;
         }

         if (data.transactionId && data.amount !== undefined && !paymentData) {
            paymentData = data;
            createCheckoutSession(data);
            return;
         }
      });

      // 3. Create Checkout Session and open in popup
      async function createCheckoutSession(data) {
         document.getElementById('statusText').textContent = 'Generating secure payment link...';

         try {
            const amount = data.amount || 0;
            const currency = (data.currency || 'PHP').toUpperCase();
            const amountInCents = Math.round(amount * 100);

            let description = data.description || 'Payment';
            let validProductDetails = [];

            if (data.productDetails && Array.isArray(data.productDetails)) {
               validProductDetails = data.productDetails.filter(p => p && (p.name || typeof p.price !== 'undefined') && Object.keys(p).length > 0);
               if (validProductDetails.length > 0) {
                  description = validProductDetails.map(p => p.name || 'Product').join(', ');
               }
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

            openCheckoutPopup(result.checkout_url);

         } catch (error) {
            showError(error.message);
            notifyGhlError(error.message);
         }
      }

      // 4. Open PayMongo in popup and poll for completion
      function openCheckoutPopup(checkoutUrl) {
         document.getElementById('statusText').textContent = 'Please complete the payment in the popup window.';

         // Visual transition
         setTimeout(() => {
            const spinner = document.querySelector('#spinnerContainer .animate-spin');
            if (spinner) {
               spinner.classList.remove('border-brand-500');
               spinner.classList.add('border-emerald-500');
            }
         }, 500);

         const width = 500;
         const height = 750;
         const left = (screen.width - width) / 2;
         const top = (screen.height - height) / 2;

         const popup = window.open(
            checkoutUrl,
            'paymongo_checkout',
            `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes,status=no,toolbar=no,menubar=no`
         );

         if (!popup || popup.closed) {
            // Popup was blocked
            document.getElementById('statusText').textContent = 'Redirecting to payment securely...';
            window.location.href = checkoutUrl;
            return;
         }

         const pollInterval = setInterval(async () => {
            if (popup.closed) {
               clearInterval(pollInterval);

               // Restore spinner color
               const spinner = document.querySelector('#spinnerContainer .animate-spin');
               if (spinner) {
                  spinner.classList.remove('border-emerald-500');
                  spinner.classList.add('border-brand-500');
               }
               document.getElementById('statusText').textContent = 'Verifying your payment...';

               await checkPaymentStatus();
            }
         }, 1000);

         setTimeout(() => {
            clearInterval(pollInterval);
         }, 600000);
      }

      // 5. Check payment status after popup closes
      async function checkPaymentStatus() {
         if (!currentCheckoutSessionId) {
            showError('Unable to locate the checkout session.');
            notifyGhlError('Session lost');
            return;
         }

         const maxAttempts = 5;
         const delayMs = 2000;

         for (let attempt = 1; attempt <= maxAttempts; attempt++) {
            try {
               const response = await fetch(`${STATUS_URL_BASE}/${currentCheckoutSessionId}`, {
                  headers: { 'Accept': 'application/json' }
               });
               if (!response.ok) throw new Error('Status iteration failed');

               const result = await response.json();

               if (result.status === 'paid') {
                  const chargeId = result.charge_id || currentCheckoutSessionId;
                  showSuccess();
                  notifyGhlSuccess(chargeId);
                  return;
               }

               if (result.status === 'expired' || result.status === 'failed' || result.status === 'cancelled') {
                  showError('Payment session was interrupted or expired.');
                  notifyGhlError('Payment not completed.');
                  return;
               }

               if (attempt < maxAttempts) await sleep(delayMs);

            } catch (error) {
               if (attempt < maxAttempts) await sleep(delayMs);
            }
         }

         // Timeout resolution
         document.getElementById('spinnerContainer').classList.add('hidden');
         showError('Payment status is pending verification. Please try again if no receipt is received.');
      }

      // 6. Resets UI and attempts again
      function retryPayment() {
         if (paymentData) {
            document.getElementById('errorBox').classList.add('hidden');
            document.getElementById('spinnerContainer').classList.remove('hidden');
            createCheckoutSession(paymentData);
         }
      }

      /* Helpers */
      function notifyGhlSuccess(chargeId) {
         window.parent.postMessage({ type: 'custom_element_success_response', chargeId: chargeId }, '*');
      }

      function notifyGhlError(message) {
         window.parent.postMessage({ type: 'custom_element_error_response', error: { description: message } }, '*');
      }

      function showError(msg) {
         document.getElementById('spinnerContainer').classList.add('hidden');
         document.getElementById('errorBox').classList.remove('hidden');
         document.getElementById('errorMsg').textContent = msg;
      }

      function showSuccess() {
         document.getElementById('spinnerContainer').classList.add('hidden');
         document.getElementById('errorBox').classList.add('hidden');
         document.getElementById('successBox').classList.remove('hidden');
      }

      function sleep(ms) {
         return new Promise(resolve => setTimeout(resolve, ms));
      }

      // 7. Initialization Heartbeat
      (function init() {
         const readyHeartbeat = setInterval(function () {
            if (!paymentData) {
               sendReadyEvent();
            } else {
               clearInterval(readyHeartbeat);
            }
         }, 1000);

         setTimeout(function () {
            if (!paymentData) {
               clearInterval(readyHeartbeat);
               showError('Connection timed out. No payment initialization properties received from the host.');
            }
         }, 30000);
      })();
   </script>
</body>

</html>