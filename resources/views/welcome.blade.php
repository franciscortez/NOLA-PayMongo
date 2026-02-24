<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install NOLA PayMongo</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;700;800&display=swap"
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
                        'fade-in-up': 'fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards',
                        'float': 'float 6s ease-in-out infinite',
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-10px)' },
                        }
                    }
                }
            }
        }
    </script>
</head>

<body
    class="bg-slate-50 min-h-screen font-sans text-slate-800 antialiased relative overflow-hidden flex flex-col items-center justify-center p-6">

    <!-- Beautiful Background Decor -->
    <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-indigo-300/30 rounded-full mix-blend-multiply filter blur-[80px] animate-pulse"
        style="animation-duration: 8s;"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] bg-emerald-300/30 rounded-full mix-blend-multiply filter blur-[100px] animate-pulse"
        style="animation-duration: 10s; animation-delay: 2s;"></div>

    <!-- Content Container -->
    <div class="relative z-10 max-w-2xl w-full mx-auto text-center animate-fade-in-up">

        <!-- App Icon/Logo Area -->
        <div
            class="w-24 h-24 bg-gradient-to-br from-slate-900 to-slate-800 rounded-3xl mx-auto mb-8 shadow-2xl shadow-slate-900/20 flex items-center justify-center transform -rotate-3 hover:rotate-0 transition-transform duration-500 hover:scale-105">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-emerald-400 transform rotate-3" fill="none"
                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
        </div>

        <h1 class="text-5xl font-display font-extrabold text-slate-900 tracking-tight mb-4">
            NOLA PayMongo
        </h1>
        <p class="text-lg text-slate-500 mb-12 max-w-lg mx-auto leading-relaxed">
            Seamlessly integrate PayMongo payments directly into your GoHighLevel funnels and websites.
        </p>

        <!-- Installation Options -->
        <div class="grid md:grid-cols-2 gap-6 w-full">

            <!-- Standard GHL -->
            <a href="{{ $standardUrl }}"
                class="group relative bg-white/60 backdrop-blur-md p-8 rounded-[2rem] border border-slate-200 shadow-xl shadow-slate-200/50 hover:shadow-2xl hover:bg-white hover:border-indigo-100 transition-all duration-300 hover:-translate-y-1 text-left flex flex-col h-full overflow-hidden">
                <div
                    class="absolute top-0 right-0 w-32 h-32 bg-indigo-50 rounded-bl-[100px] -z-10 transition-transform group-hover:scale-110">
                </div>

                <div class="mb-6 flex justify-between items-start">
                    <div
                        class="p-3 bg-indigo-100/50 text-indigo-600 rounded-xl group-hover:bg-indigo-600 group-hover:text-white transition-colors duration-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                        </svg>
                    </div>
                </div>
                <h2 class="text-2xl font-bold text-slate-900 mb-2">Standard GHL</h2>
                <p class="text-sm text-slate-500 mb-8 flex-grow">Install the app directly into your primary GoHighLevel
                    account.</p>

                <div
                    class="inline-flex items-center gap-2 text-indigo-600 font-semibold group-hover:gap-3 transition-all">
                    Install App
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M12.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-2.293-2.293a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
            </a>

            <!-- White Label -->
            <a href="{{ $whiteLabelUrl }}"
                class="group relative bg-white/60 backdrop-blur-md p-8 rounded-[2rem] border border-slate-200 shadow-xl shadow-slate-200/50 hover:shadow-2xl hover:bg-white hover:border-emerald-100 transition-all duration-300 hover:-translate-y-1 text-left flex flex-col h-full overflow-hidden">
                <div
                    class="absolute top-0 right-0 w-32 h-32 bg-emerald-50 rounded-bl-[100px] -z-10 transition-transform group-hover:scale-110">
                </div>

                <div class="mb-6 flex justify-between items-start">
                    <div
                        class="p-3 bg-emerald-100/50 text-emerald-600 rounded-xl group-hover:bg-emerald-600 group-hover:text-white transition-colors duration-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <span
                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-800">
                        Agency Use
                    </span>
                </div>
                <h2 class="text-2xl font-bold text-slate-900 mb-2">White Label</h2>
                <p class="text-sm text-slate-500 mb-8 flex-grow">Install via LeadConnector for white-labeled SaaS
                    agencies.</p>

                <div
                    class="inline-flex items-center gap-2 text-emerald-600 font-semibold group-hover:gap-3 transition-all">
                    Install App
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M12.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-2.293-2.293a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
            </a>

        </div>

        <div class="mt-12 text-sm text-slate-400 font-medium">
            NOLA PayMongo Integration Version 1.0.0
        </div>

    </div>
</body>

</html>