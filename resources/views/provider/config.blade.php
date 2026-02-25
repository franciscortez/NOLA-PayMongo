<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>NOLA PayMongo — Provider Setup</title>

   <!-- Google Fonts -->
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;700&display=swap"
      rel="stylesheet">

   <!-- Tailwind CSS -->
   <script src="https://cdn.tailwindcss.com"></script>

   <!-- SweetAlert2 -->
   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

   <!-- Beautiful Background Orbs -->
   <div
      class="absolute top-0 left-1/4 w-96 h-96 bg-indigo-200/40 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob">
   </div>
   <div
      class="absolute top-0 right-1/4 w-96 h-96 bg-purple-200/40 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob animation-delay-2000">
   </div>
   <div
      class="absolute -bottom-8 left-1/3 w-96 h-96 bg-emerald-200/40 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob animation-delay-4000">
   </div>

   <!-- Main Card -->
   <div
      class="bg-white/80 backdrop-blur-xl p-10 rounded-[2rem] shadow-2xl shadow-indigo-900/5 ring-1 ring-slate-900/5 max-w-md w-full text-center relative z-10 animate-fade-in-up">

      <div class="mb-8">
         <div
            class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-indigo-500/30 transform -rotate-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white transform rotate-6" fill="none"
               viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
               <path stroke-linecap="round" stroke-linejoin="round"
                  d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
         </div>
         <h1 class="text-3xl font-display font-bold text-slate-900 tracking-tight">NOLA PayMongo</h1>
         <p class="text-sm text-slate-500 mt-2 leading-relaxed">Securely manage your GoHighLevel Custom Payment Provider
            integration.</p>

         <div class="mt-4 flex justify-center">
            @if($isConnected)
               <span
                  class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                  Connected to GHL
               </span>
            @else
               <span
                  class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                  <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                  Not Connected
               </span>
            @endif
         </div>
      </div>

      {{-- Success Alert --}}
      @if(session('success'))
         <div
            class="bg-emerald-50 text-emerald-700 p-4 rounded-xl mb-6 text-sm font-medium border border-emerald-100 flex items-start gap-3 text-left">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-500 shrink-0 mt-0.5" viewBox="0 0 20 20"
               fill="currentColor">
               <path fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                  clip-rule="evenodd" />
            </svg>
            <span>{{ session('success') }}</span>
         </div>
      @endif

      {{-- Error Alert --}}
      @if(session('error'))
         <div
            class="bg-rose-50 text-rose-700 p-4 rounded-xl mb-6 text-sm font-medium border border-rose-100 flex items-start gap-3 text-left">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-rose-500 shrink-0 mt-0.5" viewBox="0 0 20 20"
               fill="currentColor">
               <path fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                  clip-rule="evenodd" />
            </svg>
            <span>{{ session('error') }}</span>
         </div>
      @endif

      {{-- Connect Provider Form --}}
      @if(!$isConnected)
         <form action="{{ route('provider.connect') }}" method="POST" class="mb-3">
            @csrf
            <input type="hidden" name="location_id" value="{{ $locationId }}">
            <button type="submit"
               class="group w-full bg-slate-900 hover:bg-slate-800 text-white font-medium py-3.5 px-6 rounded-xl transition-all duration-200 shadow-md shadow-slate-900/20 flex justify-center items-center gap-2 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 active:shadow-sm">
               <svg xmlns="http://www.w3.org/2000/svg"
                  class="h-5 w-5 text-emerald-400 group-hover:scale-110 transition-transform" viewBox="0 0 20 20"
                  fill="currentColor">
                  <path fill-rule="evenodd"
                     d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                     clip-rule="evenodd" />
               </svg>
               Connect to GoHighLevel
            </button>
         </form>
      @endif

      {{-- Remove Provider Form --}}
      @if($isConnected)
         <form id="removeIntegrationForm" action="{{ route('provider.delete') }}" method="POST">
            @csrf
            @method('DELETE')
            <input type="hidden" name="location_id" value="{{ $locationId }}">
            <button type="button" onclick="confirmRemoval()"
               class="w-full bg-red-600 hover:bg-red-700 text-white font-medium py-3.5 px-6 rounded-xl transition-all duration-200 shadow-md shadow-red-600/20 flex justify-center items-center gap-2 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 active:shadow-sm">
               <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round"
                     d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
               </svg>
               Remove Integration
            </button>
         </form>
      @endif

      <div class="mt-8 pt-6 border-t border-slate-100">
         <div
            class="inline-flex items-center gap-2 text-xs text-slate-400 bg-slate-50 px-3 py-1.5 rounded-full border border-slate-100">
            <span>Location:</span>
            <code class="font-mono font-medium text-slate-600">{{ $locationId }}</code>
         </div>
      </div>
   </div>

   <script>
      function confirmRemoval() {
         Swal.fire({
            title: 'Are you sure?',
            text: "This will completely remove the NOLA PayMongo integration from this GoHighLevel location.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626', // bg-red-600
            cancelButtonColor: '#475569', // bg-slate-600
            confirmButtonText: 'Yes, remove it!',
            cancelButtonText: 'No, keep it',
            background: '#ffffff',
            borderRadius: '1.25rem',
            customClass: {
               popup: 'rounded-[2rem] shadow-2xl',
               confirmButton: 'rounded-xl font-medium px-6 py-3',
               cancelButton: 'rounded-xl font-medium px-6 py-3'
            }
         }).then((result) => {
            if (result.isConfirmed) {
               document.getElementById('removeIntegrationForm').submit();
            }
         })
      }
   </script>
</body>

</html>