<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Payment Cancelled</title>
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
         background: #fef2f2;
         color: #dc2626;
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
   <div class="container">
      <div class="icon">
         <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd"
               d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
               clip-rule="evenodd" />
         </svg>
      </div>
      <h2>Payment Cancelled</h2>
      <p>You cancelled the payment. You may close this window and try again.</p>
   </div>

   <script>
      // Notify GHL parent that payment was cancelled
      console.log('[Checkout] Payment cancelled — notifying GHL parent');

      window.parent.postMessage({
         type: 'custom_element_error_response',
         error: { description: 'Payment was cancelled by user' }
      }, '*');
   </script>
</body>

</html>