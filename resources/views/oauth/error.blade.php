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
                  display: ['Outfit', 'sans-serif'],
               },
               animation: {
                  'fade-in-up': 'fadeInUp 0.6s ease-out forwards',
               },
               keyframes: {
                  fadeInUp: {
                     '0%': {
                        opacity: '0',
                        transform: 'translateY(15px)'
                     },
                     '100%': {
                        opacity: '1',
                        transform: 'translateY(0)'
                     },
                  }
               }
            }
         }
      }
   </script>
</head>

<body
   class="bg-slate-50 flex items-center justify-center min-h-screen font-sans text-slate-800 antialiased p-6 relative overflow-hidden">

   <!-- Background Orbs (Blue Theme) -->
   <div
      class="absolute top-0 left-1/4 w-96 h-96 bg-blue-100/40 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob">
   </div>
   <div
      class="absolute -bottom-8 right-1/4 w-96 h-96 bg-indigo-50/40 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob">
   </div>

   <!-- Main Card -->
   <div
      class="bg-white/80 backdrop-blur-xl p-10 rounded-[2rem] shadow-2xl shadow-rose-900/5 ring-1 ring-slate-900/5 max-w-md w-full text-center relative z-10 animate-fade-in-up">

      <div class="mb-10">
         <!-- Logo Frame Placeholder -->
         <div class="mb-6 relative inline-block">
            <div class="w-24 h-24 bg-brand-surface border-2 border-dashed border-blue-200 rounded-full flex items-center justify-center mx-auto transition-all hover:border-brand-primary group">
               <div class="w-20 h-20 bg-gradient-to-br from-brand-primary to-brand-secondary rounded-full flex items-center justify-center shadow-lg shadow-blue-500/30 transition-transform group-hover:scale-95">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                     <path stroke-linecap="round" stroke-linejoin="round"
                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                  </svg>
               </div>
            </div>
         </div>
         <h1 class="text-3xl font-display font-bold text-slate-900 tracking-tight">OAuth Error</h1>
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
         @endif
      </div>
   </div>

   <a href="/"
      class="group w-full bg-brand-secondary hover:bg-brand-primary text-white font-medium py-3.5 px-6 rounded-xl transition-all duration-200 shadow-md shadow-blue-900/20 flex justify-center items-center gap-2 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 active:shadow-sm">
      Try Again
   </a>

   <p class="mt-6 text-xs text-slate-400">
      If this persists, please contact support with the details shown above.
   </p>
   </div>
</body>

</html>