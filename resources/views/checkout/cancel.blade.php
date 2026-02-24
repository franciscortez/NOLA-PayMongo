<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Payment Cancelled</title>

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
                  'slide-down-fade': 'slideDownFade 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards',
                  'pulse-subtle': 'pulseSubtle 2.5s infinite',
               },
               keyframes: {
                  slideDownFade: {
                     '0%': { opacity: '0', transform: 'translateY(-20px) scale(0.95)' },
                     '100%': { opacity: '1', transform: 'translateY(0) scale(1)' },
                  },
                  pulseSubtle: {
                     '0%, 100%': { opacity: '1', transform: 'scale(1)' },
                     '50%': { opacity: '0.9', transform: 'scale(0.98)' },
                  }
               }
            }
         }
      }
   </script>
</head>

<body
   class="bg-gradient-to-br from-red-50 to-rose-100 min-h-screen font-sans text-slate-800 flex items-center justify-center p-6 antialiased">

   <div class="max-w-md w-full animate-slide-down-fade">
      <div
         class="bg-white/85 backdrop-blur-xl shadow-2xl shadow-rose-900/10 rounded-3xl p-8 text-center border border-rose-100">

         <div
            class="w-16 h-16 bg-gradient-to-br from-rose-100 to-red-200 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-5 shadow-inner ring-4 ring-rose-50 animate-pulse-subtle">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
               stroke="currentColor" stroke-width="2.5">
               <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
         </div>

         <h2 class="text-2xl font-bold text-slate-900 mb-3 tracking-tight">Payment Cancelled</h2>
         <p class="text-slate-500 leading-relaxed mb-6 text-sm">
            You've cancelled the checkout process. No charges have been made to your account.
         </p>

         <div class="w-full bg-slate-50 rounded-xl p-3 text-xs text-slate-500 border border-slate-100">
            This window will automatically close in a moment.
         </div>
      </div>
   </div>

   <script>
      // The iFrame JS will detect the popup closed and handle the status check.
      // Close the popup after a brief delay.
      setTimeout(function () {
         window.close();
      }, 3500);
   </script>
</body>

</html>