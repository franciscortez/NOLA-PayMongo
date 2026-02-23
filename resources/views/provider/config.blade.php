<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>NOLA PayMongo — Provider Setup</title>
   <script src="https://cdn.tailwindcss.com"></script>
   <style>
      body {
         font-family: 'Inter', sans-serif;
         background-color: #f3f4f6;
      }
   </style>
</head>

<body class="flex items-center justify-center min-h-screen">
   <div class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full text-center">

      <div class="mb-6">
         <h1 class="text-2xl font-bold text-gray-800">NOLA PayMongo</h1>
         <p class="text-sm text-gray-500 mt-2">Manage the GoHighLevel Custom Payment Provider integration.</p>
      </div>

      @if(session('success'))
         <div class="bg-green-100 text-green-700 p-4 rounded-lg mb-6 text-sm font-medium border border-green-200">
            {{ session('success') }}
         </div>
      @endif

      @if(session('error'))
         <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6 text-sm font-medium border border-red-200">
            {{ session('error') }}
         </div>
      @endif

      {{-- Connect / Register Provider --}}
      <form action="{{ route('provider.connect') }}" method="POST" class="mb-4">
         @csrf
         <input type="hidden" name="location_id" value="{{ $locationId }}">
         <button type="submit"
            class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition shadow-md flex justify-center items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
               <path fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                  clip-rule="evenodd" />
            </svg>
            Connect Provider
         </button>
      </form>

      {{-- Delete / Remove Provider --}}
      <form action="{{ route('provider.delete') }}" method="POST"
         onsubmit="return confirm('Are you sure you want to remove NOLA PayMongo from GoHighLevel?');">
         @csrf
         @method('DELETE')
         <input type="hidden" name="location_id" value="{{ $locationId }}">
         <button type="submit"
            class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-lg transition shadow-md flex justify-center items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
               <path fill-rule="evenodd"
                  d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                  clip-rule="evenodd" />
            </svg>
            Remove Provider
         </button>
      </form>

      <p class="text-xs text-gray-400 mt-6">Location ID: <code class="bg-gray-100 px-1 rounded">{{ $locationId }}</code>
      </p>
   </div>
</body>

</html>