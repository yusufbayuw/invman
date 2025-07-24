<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Captive Portal Login</title>
    @vite('resources/css/app.css')
    <style>
        /* For browsers that don't support backdrop-filter */
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
            
            <!-- The Form Card with Glassmorphism Effect -->
            <div class="bg-white/20 backdrop-blur-xl rounded-2xl shadow-lg p-8 space-y-6 border border-white/30 bg-glass">
                
                <!-- Logo/Header -->
                <div class="text-center">
                    <h1 class="text-3xl font-bold text-white">Welcome</h1>
                    <p class="text-white/80">Please log in to connect</p>
                </div>

                <!-- Login Form -->
                <form action="$(link-login-only)" method="post" class="space-y-6">
                    <!-- These hidden inputs are required by MikroTik -->
                    <input type="hidden" name="dst" value="$(link-orig)">
                    <input type="hidden" name="popup" value="true">

                    <!-- Username Input -->
                    <div>
                        <label for="username" class="block text-sm font-medium text-white/90">Username</label>
                        <input 
                            type="text" 
                            name="username" 
                            id="username"
                            value="$(username)"
                            placeholder="Enter your username"
                            class="mt-1 block w-full bg-white/30 border-white/40 text-white placeholder-white/70 rounded-lg shadow-sm focus:ring-white focus:border-white p-3 transition"
                            required
                            autofocus>
                    </div>

                    <!-- Password Input -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-white/90">Password</label>
                        <input 
                            type="password" 
                            name="password" 
                            id="password"
                            placeholder="••••••••"
                            class="mt-1 block w-full bg-white/30 border-white/40 text-white placeholder-white/70 rounded-lg shadow-sm focus:ring-white focus:border-white p-3 transition"
                            required>
                    </div>
                    
                    <!-- Error Message Placeholder -->
                    @if(request()->get('error'))
                        <div class="text-center text-red-300 bg-red-900/50 p-2 rounded-lg">
                            <p>Login failed: {{ request()->get('error') }}</p>
                        </div>
                    @endif

                    <!-- Submit Button -->
                    <div>
                        <button 
                            type="submit"
                            class="w-full bg-white text-purple-600 font-bold py-3 px-4 rounded-lg shadow-md hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white/50 transition-transform transform hover:scale-105">
                            Connect
                        </button>
                    </div>
                </form>

            </div>

            <!-- Footer -->
            <div class="text-center mt-6 text-white/70 text-sm">
                <p>Powered by Invman &copy; {{ date('Y') }}</p>
            </div>
        </div>
    </div>

</body>
</html>