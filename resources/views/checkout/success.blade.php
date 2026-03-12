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
               colors: {
                  brand: {
                     primary: '#2563eb', // blue-600
                     secondary: '#1e3a8a', // blue-900
                     accent: '#ffffff',
                     surface: '#f8fafc', // slate-50
                  }
               },
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
   class="bg-gradient-to-br from-blue-50 to-brand-surface min-h-screen font-sans text-slate-800 flex items-center justify-center p-6 antialiased">

   <div class="max-w-md w-full animate-slide-up-fade">
      <div
         class="bg-white p-8 rounded-[2.5rem] shadow-2xl shadow-blue-900/10 text-center border border-slate-100 relative overflow-hidden">
         
         <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-brand-primary to-brand-secondary"></div>

         <!-- Logo Frame Placeholder -->
         <div class="mb-8 relative scale-75">
            <div class="w-24 h-24 bg-brand-surface border-2 border-dashed border-blue-200 rounded-full flex items-center justify-center mx-auto group">
               <div class="w-20 h-20 bg-gradient-to-br from-brand-primary to-brand-secondary rounded-full flex items-center justify-center shadow-lg shadow-blue-500/30">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                     <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                  </svg>
               </div>
            </div>
         </div>

         <div
            class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-inner ring-4 ring-emerald-50 animate-bounce-soft">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
               stroke="currentColor" stroke-width="3">
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
      // Notify parent that we reached the success page.
      if (window.parent && window.parent !== window) {
         window.parent.postMessage({ type: 'checkout_success' }, '*');
      }

      // Attempt to close the popup after a brief delay (legacy/fallback).
      setTimeout(function () {
         window.close();
      }, 3500);
   </script>
</body>

</html>