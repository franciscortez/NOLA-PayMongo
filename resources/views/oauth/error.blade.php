<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>NOLA PayMongo — Authentication Error</title>

   <!-- Google Fonts -->
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;700&display=swap"
      rel="stylesheet">

   <!-- Tailwind CSS -->
   <script src="https://cdn.tailwindcss.com"></script>

   <script>
      tailwind.config = {
         theme: {
            extend: {
               fontFamily: {
                  sans: ['Inter', 'sans-serif'],
                  display: ['Outfit', 'sans-serif'],
               },
               animation: {
                  'fade-in-up': 'fadeInUp 0.6s ease-out forwards',
               },
               keyframes: {
                  fadeInUp: {
                     '0%': { opacity: '0', transform: 'translateY(15px)' },
                     '100%': { opacity: '1', transform: 'translateY(0)' },
                  }
               }
            }
         }
      }
   </script>
</head>

<body
   class="bg-slate-50 flex items-center justify-center min-h-screen font-sans text-slate-800 antialiased p-6 relative overflow-hidden">

   <!-- Background Orbs -->
   <div
      class="absolute top-0 left-1/4 w-96 h-96 bg-rose-200/30 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob">
   </div>
   <div
      class="absolute -bottom-8 right-1/4 w-96 h-96 bg-orange-200/30 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob">
   </div>

   <!-- Main Card -->
   <div
      class="bg-white/80 backdrop-blur-xl p-10 rounded-[2rem] shadow-2xl shadow-rose-900/5 ring-1 ring-slate-900/5 max-w-md w-full text-center relative z-10 animate-fade-in-up">

      <div class="mb-8">
         <div
            class="w-16 h-16 bg-gradient-to-br from-rose-500 to-orange-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-rose-500/30 transform -rotate-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white transform rotate-6" fill="none"
               viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
               <path stroke-linecap="round" stroke-linejoin="round"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
         </div>
         <h1 class="text-3xl font-display font-bold text-slate-900 tracking-tight">Authentication Error</h1>
         <p class="text-sm text-slate-500 mt-2 leading-relaxed">Something went wrong while connecting to GoHighLevel.
         </p>
      </div>

      <div class="bg-rose-50 text-rose-700 p-4 rounded-xl mb-6 text-sm font-medium border border-rose-100 text-left">
         <div class="flex items-start gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-rose-500 shrink-0 mt-0.5" viewBox="0 0 20 20"
               fill="currentColor">
               <path fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                  clip-rule="evenodd" />
            </svg>
            <span>{{ $error }}</span>
         </div>
         @if(isset($details))
            <div class="mt-3 pt-3 border-t border-rose-200">
               <p class="text-[10px] uppercase tracking-wider font-bold text-rose-400 mb-1">Technical Details</p>
               <pre
                  class="bg-rose-100/50 p-2 rounded-lg overflow-x-auto text-[11px] font-mono text-rose-800">{{ is_array($details) ? json_encode($details, JSON_PRETTY_PRINT) : $details }}</pre>
            </div>
         @endif
      </div>

      <a href="/"
         class="group w-full bg-slate-900 hover:bg-slate-800 text-white font-medium py-3.5 px-6 rounded-xl transition-all duration-200 shadow-md shadow-slate-900/20 flex justify-center items-center gap-2 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 active:shadow-sm">
         Try Again
      </a>

      <p class="mt-6 text-xs text-slate-400">
         If this persists, please contact support with the details shown above.
      </p>
   </div>
</body>

</html>