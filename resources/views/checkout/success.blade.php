<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Payment Successful</title>

   <!-- Google Fonts -->
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

   <!-- Tailwind CSS -->
   <script src="https://cdn.tailwindcss.com"></script>

   <!-- Custom Tailwind Setup -->
   <script>
      tailwind.config = {
         theme: {
            extend: {
               fontFamily: {
                  sans: ['Inter', 'sans-serif'],
               },
               animation: {
                  'slide-up-fade': 'slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards',
                  'bounce-soft': 'bounceSoft 2s infinite',
               },
               keyframes: {
                  slideUpFade: {
                     '0%': { opacity: '0', transform: 'translateY(20px) scale(0.95)' },
                     '100%': { opacity: '1', transform: 'translateY(0) scale(1)' },
                  },
                  bounceSoft: {
                     '0%, 100%': { transform: 'translateY(-5%)' },
                     '50%': { transform: 'translateY(0)' },
                  }
               }
            }
         }
      }
   </script>
</head>

<body
   class="bg-gradient-to-br from-green-50 to-emerald-100 min-h-screen font-sans text-slate-800 flex items-center justify-center p-6 antialiased">

   <div class="max-w-md w-full animate-slide-up-fade">
      <div
         class="bg-white/80 backdrop-blur-md shadow-2xl shadow-emerald-900/10 rounded-3xl p-8 text-center border border-emerald-100/50">

         <div
            class="w-20 h-20 bg-gradient-to-br from-emerald-100 to-green-200 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner ring-8 ring-emerald-50 animate-bounce-soft">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24"
               stroke="currentColor" stroke-width="2.5">
               <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
         </div>

         <h2 class="text-3xl font-bold text-slate-900 mb-3 tracking-tight">Payment Complete</h2>
         <p class="text-slate-500 leading-relaxed mb-8">
            Thank you! Your transaction has been securely processed.
         </p>

         <div
            class="w-full bg-slate-50 rounded-xl p-4 mb-4 text-sm text-slate-600 flex items-center justify-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 animate-spin" fill="none"
               viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
               <path stroke-linecap="round" stroke-linejoin="round"
                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            This window will close automatically...
         </div>
      </div>
   </div>

   <script>
      // The iFrame JS handles notifying GHL via polling.
      // Attempt to close the popup after a brief delay.
      setTimeout(function () {
         window.close();
      }, 3500);
   </script>
</body>

</html>