<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Payment Successful</title>
   <style>
      * {
         margin: 0;
         padding: 0;
         box-sizing: border-box;
      }

      body {
         font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
         background: #f8f9fa;
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

      .icon {
         width: 64px;
         height: 64px;
         background: #dcfce7;
         color: #16a34a;
         border-radius: 50%;
         display: flex;
         align-items: center;
         justify-content: center;
         margin: 0 auto 20px;
      }

      h2 {
         font-size: 22px;
         font-weight: 700;
         color: #1e293b;
         margin-bottom: 8px;
      }

      p {
         font-size: 15px;
         color: #64748b;
         line-height: 1.4;
      }
   </style>
</head>

<body>
   <div class="container" data-transaction-id="{{ $transactionId ?? '' }}" data-order-id="{{ $orderId ?? '' }}">
      <div class="icon">
         <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd"
               d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
               clip-rule="evenodd" />
         </svg>
      </div>
      <h2>Payment Successful!</h2>
      <p>Your payment has been received. You may close this window.</p>
   </div>

   <script>
      // Notify GHL parent that payment succeeded
      var container = document.querySelector('.container');
      var transactionId = container.getAttribute('data-transaction-id');
      var orderId = container.getAttribute('data-order-id');
      var chargeId = transactionId || orderId || 'checkout_success_' + Date.now();

      console.log('[Checkout] Payment success — notifying GHL parent, chargeId:', chargeId);

      window.parent.postMessage({
         type: 'custom_element_success_response',
         chargeId: chargeId
      }, '*');
   </script>
</body>

</html>