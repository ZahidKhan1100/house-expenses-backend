<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Successful | HabiMate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Sora', sans-serif; 
            background-color: #0f172a; 
        }
        .mesh-gradient {
            background-color: #0f172a;
            background-image: 
                radial-gradient(at 0% 0%, rgba(78, 84, 200, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(255, 107, 107, 0.15) 0px, transparent 50%);
        }
        .glass-card { 
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .success-icon {
            background: linear-gradient(135deg, #FF6B6B 0%, #4E54C8 100%);
            box-shadow: 0 10px 25px rgba(255, 107, 107, 0.3);
        }
    </style>
</head>
<body class="mesh-gradient text-slate-200 min-h-screen flex items-center justify-center p-6">

    <div class="max-w-md w-full glass-card rounded-[2.5rem] p-10 text-center shadow-2xl relative overflow-hidden">
        <div class="absolute -top-24 -right-24 w-48 h-48 bg-indigo-600/20 blur-[80px] rounded-full"></div>
        
        <div class="relative z-10">
            <div class="w-20 h-20 success-icon rounded-3xl flex items-center justify-center mx-auto mb-8">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>

            @if ($status === 'success')
                <h1 class="text-4xl font-extrabold text-white mb-4 tracking-tight leading-tight">
                    Congrats, <span class="bg-clip-text text-transparent bg-gradient-to-r from-rose-400 to-rose-500">{{ $name }}!</span>
                </h1>
                <p class="text-slate-400 text-lg leading-relaxed mb-10">
                    Your email is verified. Your habitat is now ready for shared living, house or away.
                </p>
            @else
                <h1 class="text-3xl font-extrabold text-white mb-4 tracking-tight">
                    Already Verified
                </h1>
                <p class="text-slate-400 text-lg leading-relaxed mb-10">
                    Hello {{ $name }}, your account is already active. You're good to go!
                </p>
            @endif

            {{-- <a href="habimate://login" class="block w-full py-4 rounded-2xl bg-white text-slate-900 font-extrabold text-lg shadow-xl hover:bg-slate-100 transition transform hover:-translate-y-1">
                Open HabiMate App
            </a> --}}

            <p class="mt-8 text-xs font-bold text-slate-500 uppercase tracking-widest">
                Thank you for joining <span class="text-rose-500">HabiMate</span>
            </p>
        </div>
    </div>

</body>
</html>