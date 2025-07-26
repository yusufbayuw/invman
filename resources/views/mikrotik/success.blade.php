<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Success - Captive Portal</title>
    @vite('resources/css/app.css')
    <style>
        @supports not (backdrop-filter: blur(1rem)) {
            .bg-glass {
                background-color: rgba(255, 255, 255, 0.5);
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500">
    
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            
            <!-- Success Card with Glassmorphism Effect -->
            <div class="bg-white/20 backdrop-blur-xl rounded-2xl shadow-lg p-8 space-y-6 border border-white/30 bg-glass">
                
                <!-- Logo/Header -->
                <div class="text-center"></div>
                    <h1 class="text-3xl font-bold text-white">Login Successful!</h1>
                    <p class="text-white/80">You are now connected to the hotspot.</p>
                </div>

                <!-- Success Icon -->
                <div class="flex justify-center"></div>
                    <svg class="w-20 h-20 text-green-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/>
                        <path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M8 12l2 2 4-4"/>
                    </svg>
                </div>

                <!-- Info Section -->
                <div class="text-center text-white/90 space-y-2">
                    <p>Welcome, <span class="font-bold">{{ request()->get('username') ?? 'User' }}</span>!</p>
                    <p>You can now access the internet.</p>
                </div>

                <!-- Logout Button -->
                <div>
                    <a 
                        href="$(link-logout)"
                        class="w-full inline-block bg-white text-purple-600 font-bold py-3 px-4 rounded-lg shadow-md hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white/50 transition-transform transform hover:scale-105 text-center">
                        Logout
                    </a>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center mt-6 text-white/70 text-sm">
                <p>Powered by Invman &copy; {{ date('Y') }}</p>
            </div>
        </div>
    </div>

</body>
</html>
