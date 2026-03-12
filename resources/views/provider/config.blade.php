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

   <!-- Beautiful Background Orbs (Blue/Navy Theme) -->
   <div
      class="absolute top-0 left-1/4 w-96 h-96 bg-blue-200/30 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob">
   </div>
   <div
      class="absolute top-0 right-1/4 w-96 h-96 bg-indigo-100/30 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob animation-delay-2000">
   </div>
   <div
      class="absolute -bottom-8 left-1/3 w-96 h-96 bg-blue-100/30 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob animation-delay-4000">
   </div>   <!-- Main Card -->
   <div
      class="bg-white p-10 rounded-[2rem] shadow-2xl ring-1 ring-slate-200 max-w-4xl w-full text-center relative z-10 animate-fade-in-up border-t-8 border-brand-primary">

      <div class="mb-10">
         <!-- Logo Frame Placeholder -->
         <div class="mb-6 relative inline-block">
            <div class="w-24 h-24 bg-brand-surface border-2 border-dashed border-blue-200 rounded-full flex items-center justify-center mx-auto transition-all hover:border-brand-primary group">
               <div class="w-20 h-20 bg-gradient-to-br from-brand-primary to-brand-secondary rounded-full flex items-center justify-center shadow-lg shadow-blue-500/30 transition-transform group-hover:scale-95">
                  <!-- Temporary Logo Placeholder Icon -->
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                     <path stroke-linecap="round" stroke-linejoin="round"
                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                  </svg>
               </div>
               <span class="absolute -bottom-2 right-0 bg-white border border-slate-100 text-[10px] px-2 py-0.5 rounded-full shadow-sm font-bold text-brand-secondary">LOGO</span>
            </div>
         </div>
         <h1 class="text-3xl font-display font-bold text-slate-900 tracking-tight">NOLA PayMongo</h1>
         <p class="text-sm text-slate-500 mt-2 leading-relaxed">Securely manage your GoHighLevel Custom Payment Provider
            integration.</p>
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
         <div class="bg-rose-50 text-rose-700 p-4 rounded-xl mb-6 text-sm font-medium border border-rose-100 text-left">
            <div class="flex items-start gap-3">
               <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-rose-500 shrink-0 mt-0.5" viewBox="0 0 20 20"
                  fill="currentColor">
                  <path fill-rule="evenodd"
                     d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                     clip-rule="evenodd" />
               </svg>
               <span>{{ session('error') }}</span>
            </div>

            @if(session('error_details'))
               <div class="mt-3 pt-3 border-t border-rose-200">
                  <p class="text-[10px] uppercase tracking-wider font-bold text-rose-400 mb-1">Technical Details</p>
                  <pre
                     class="bg-rose-100/50 p-2 rounded-lg overflow-x-auto text-[11px] font-mono text-rose-800">{{ is_array(session('error_details')) ? json_encode(session('error_details'), JSON_PRETTY_PRINT) : session('error_details') }}</pre>
               </div>
            @endif
         </div>
      @endif


      {{-- Connect/Update Provider Form --}}
      <form id="configForm" action="{{ route('provider.connect') }}" method="POST" class="text-left">
         @csrf
         <input type="hidden" name="location_id" value="{{ $locationId }}">

         <div class="flex flex-col md:flex-row gap-8 mb-8">
            <!-- Live Mode Column -->
            <div class="flex-1 space-y-4">
               <h3 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Live Mode Keys
               </h3>
               <div class="space-y-3">
                  <div>
                     <label for="live_secret_key" class="block text-xs font-medium text-slate-500 mb-1">Live Secret Key <span class="text-rose-500">*</span></label>
                     <input type="password" name="live_secret_key" id="live_secret_key" required placeholder="sk_live_..."
                        value="{{ $keys['live_secret_key'] ?? '' }}"
                        {{ $isConnected ? 'readonly' : '' }}
                        class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-lg focus:ring-brand-primary focus:border-brand-primary block p-2.5 transition-colors disabled:opacity-70 read-only:bg-slate-100 read-only:cursor-not-allowed">
                  </div>
                  <div>
                     <label for="live_publishable_key" class="block text-xs font-medium text-slate-500 mb-1">Live Publishable Key <span class="text-rose-500">*</span></label>
                     <input type="text" name="live_publishable_key" id="live_publishable_key" required placeholder="pk_live_..."
                        value="{{ $keys['live_publishable_key'] ?? '' }}"
                        {{ $isConnected ? 'readonly' : '' }}
                        class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-lg focus:ring-brand-primary focus:border-brand-primary block p-2.5 transition-colors disabled:opacity-70 read-only:bg-slate-100 read-only:cursor-not-allowed">
                  </div>
               </div>
            </div>

            <!-- Test Mode Column -->
            <div class="flex-1 space-y-4">
               <h3 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-amber-500"></span> Test Mode Keys
               </h3>
               <div class="space-y-3">
                  <div>
                     <label for="test_secret_key" class="block text-xs font-medium text-slate-500 mb-1">Test Secret Key <span class="text-rose-500">*</span></label>
                     <input type="password" name="test_secret_key" id="test_secret_key" required placeholder="sk_test_..."
                        value="{{ $keys['test_secret_key'] ?? '' }}"
                        {{ $isConnected ? 'readonly' : '' }}
                        class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-lg focus:ring-brand-primary focus:border-brand-primary block p-2.5 transition-colors disabled:opacity-70 read-only:bg-slate-100 read-only:cursor-not-allowed">
                  </div>
                  <div>
                     <label for="test_publishable_key" class="block text-xs font-medium text-slate-500 mb-1">Test Publishable Key <span class="text-rose-500">*</span></label>
                     <input type="text" name="test_publishable_key" id="test_publishable_key" required placeholder="pk_test_..."
                        value="{{ $keys['test_publishable_key'] ?? '' }}"
                        {{ $isConnected ? 'readonly' : '' }}
                        class="w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-lg focus:ring-brand-primary focus:border-brand-primary block p-2.5 transition-colors disabled:opacity-70 read-only:bg-slate-100 read-only:cursor-not-allowed">
                  </div>
               </div>
            </div>
         </div>

         <div class="space-y-3">
            @if($isConnected)
               <button type="button" id="editButton" onclick="enableEditing()"
                  class="w-full bg-white hover:bg-slate-50 text-slate-700 font-medium py-3 px-6 rounded-xl transition-all duration-200 border border-slate-200 flex justify-center items-center gap-2 hover:shadow-sm active:translate-y-0.5">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                     <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                  Edit Keys
               </button>
            @endif

            <button type="submit" id="saveButton"
               class="{{ $isConnected ? 'hidden' : '' }} group w-full bg-white hover:bg-slate-50 text-slate-700 font-medium py-3 px-6 rounded-xl transition-all duration-200 border border-slate-200 flex justify-center items-center gap-2 hover:shadow-sm active:translate-y-0.5">
               <svg xmlns="http://www.w3.org/2000/svg"
                  class="h-5 w-5 text-slate-500 group-hover:scale-110 transition-transform" viewBox="0 0 20 20"
                  fill="currentColor">
                  <path fill-rule="evenodd"
                     d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                     clip-rule="evenodd" />
               </svg>
               {{ $isConnected ? 'Update & Save Keys' : 'Connect & Save Keys' }}
            </button>
         </div>
      </form>

      @if($isConnected)
         <div class="mt-3">
            <form id="removeIntegrationForm" action="{{ route('provider.delete') }}" method="POST">
               @csrf
               @method('DELETE')
               <input type="hidden" name="location_id" value="{{ $locationId }}">
               <button type="button" id="removeBtn" onclick="confirmRemoval()"
                  class="w-full bg-white hover:bg-rose-50 text-rose-600 font-medium py-3 px-6 rounded-xl transition-all duration-200 border border-rose-100 flex justify-center items-center gap-2 hover:shadow-sm active:bg-rose-100">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-rose-500" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="2">
                     <path stroke-linecap="round" stroke-linejoin="round"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                  Remove Integration
               </button>
               <button type="button" id="cancelBtn" onclick="cancelEditing()"
                  class="hidden w-full bg-white hover:bg-rose-50 text-rose-600 font-medium py-3 px-6 rounded-xl transition-all duration-200 border border-rose-100 flex justify-center items-center gap-2 hover:shadow-sm active:bg-rose-100">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                     <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                  Cancel Update
               </button>
            </form>
         </div>
      @endif

      <div class="mt-8 pt-6 border-t border-slate-100 flex flex-col md:flex-row items-center justify-between gap-4">
         <div
            class="inline-flex items-center gap-2 text-xs text-slate-400 bg-slate-50 px-3 py-1.5 rounded-full border border-slate-100 italic">
            <span>Subaccount:</span>
            <span class="font-medium text-slate-600">{{ $locationName }}</span>
         </div>
         <div
            class="inline-flex items-center gap-2 text-[10px] uppercase font-bold tracking-widest leading-none">
            @if($isConnected)
               <span class="flex items-center gap-1.5 text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-100">
                  <span class="w-1 h-1 rounded-full bg-emerald-500 animate-pulse"></span>
                  Connected
               </span>
            @else
               <span class="flex items-center gap-1.5 text-rose-600 bg-rose-50 px-2.5 py-1 rounded-full border border-rose-100">
                  <span class="w-1 h-1 rounded-full bg-rose-500"></span>
                  Disconnected
               </span>
            @endif
         </div>
      </div>
   </div>

   <script>
      function enableEditing() {
         const form = document.getElementById('configForm');
         const inputs = form.querySelectorAll('input:not([type="hidden"])');
         const editBtn = document.getElementById('editButton');
         const saveBtn = document.getElementById('saveButton');
         const removeBtn = document.getElementById('removeBtn');
         const cancelBtn = document.getElementById('cancelBtn');

         inputs.forEach(input => {
            input.removeAttribute('readonly');
            input.classList.remove('read-only:bg-slate-100', 'read-only:cursor-not-allowed');
            // Force focus on the first input
         });
         
         if (inputs.length > 0) inputs[0].focus();

         if (editBtn) editBtn.classList.add('hidden');
         saveBtn.classList.remove('hidden');

         if (removeBtn) removeBtn.classList.add('hidden');
         if (cancelBtn) cancelBtn.classList.remove('hidden');
      }

      function cancelEditing() {
         window.location.reload();
      }

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