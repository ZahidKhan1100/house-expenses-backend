<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HabiMate | Shared Living. House or Away.</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sora', sans-serif; background-color: #0f172a; }
        .glass { 
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .mesh-gradient {
            background-color: #0f172a;
            background-image: 
                radial-gradient(at 0% 0%, rgba(78, 84, 200, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(255, 107, 107, 0.15) 0px, transparent 50%);
        }
        .hero-text {
            letter-spacing: -0.05em;
        }
    </style>
</head>
<body class="mesh-gradient text-slate-200 antialiased min-h-screen">

    <nav class="flex items-center justify-between px-8 py-6 max-w-7xl mx-auto">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-gradient-to-br from-rose-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-rose-500/20">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
            </div>
            <span class="text-2xl font-extrabold tracking-tighter text-white">HabiMate</span>
        </div>
        
        <div class="flex items-center gap-6">
            @if (Route::has('login'))
                @auth
                    <a href="{{ url('/dashboard') }}" class="px-5 py-2.5 rounded-full bg-white text-slate-900 text-sm font-bold hover:bg-slate-200 transition">Admin Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-bold text-slate-400 hover:text-white transition">Admin Login</a>
                    <a href="#beta" class="hidden sm:block px-6 py-2.5 rounded-full border border-white/10 glass text-white text-sm font-bold hover:bg-white/5 transition">Join Beta</a>
                @endauth
            @endif
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-8 pt-16 pb-32">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            <div class="space-y-8">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-bold mb-6">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                        </span>
                        NEW VERSION 2.0
                    </div>
                    <h1 class="text-6xl md:text-8xl font-extrabold text-white leading-[0.9] hero-text mb-4">
                        Shared Living.
                    </h1>
                    <h2 class="text-5xl md:text-7xl font-extrabold bg-clip-text text-transparent bg-gradient-to-r from-rose-400 to-indigo-500 hero-text">
                        House or Away.
                    </h2>
                </div>

                <p class="text-xl text-slate-400 leading-relaxed max-w-lg">
                    The ultimate companion for roommates and travelers. Track group expenses, split bills instantly, and manage your household harmony from one premium interface.
                </p>

                <div class="flex flex-col sm:flex-row gap-4">
                    <button class="flex items-center justify-center gap-3 px-8 py-4 rounded-2xl bg-white text-slate-900 font-bold shadow-xl hover:bg-slate-200 transition transform hover:-translate-y-1">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M17.05 20.28c-.98.95-2.05.88-3.08.4-1.09-.5-2.08-.48-3.24 0-1.44.62-2.2.44-3.06-.4C4.83 17.5 4.3 11.23 7.5 9.1c1.55-.83 2.87-.35 3.75.12.82.44 1.48.42 2.05.02.82-.55 2.1-.96 3.86.13 1.27.7 2.05 1.5 2.5 2.5-2.48 1.1-2.9 4.3-.4 5.5-.5.8-1.2 1.8-2.2 2.8zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.3 2.4-2.15 4.38-3.74 4.25z"/></svg>
                        App Store
                    </button>
                    <button class="flex items-center justify-center gap-3 px-8 py-4 rounded-2xl bg-slate-800 text-white font-bold shadow-xl hover:bg-slate-700 transition transform hover:-translate-y-1 border border-white/5">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M3.609 1.814L13.792 12 3.61 22.186a.996.996 0 01-.1-.12c-.273-.37-.417-.833-.417-1.315V3.249c0-.483.144-.946.417-1.315.03-.04.064-.08.1-.12zM14.781 12.989l3.412 1.993-9.562 5.579 6.15-7.572zm4.434-2.583c.48.28.785.8.785 1.594s-.305 1.314-.785 1.594l-2.422 1.414-3.413-1.993 3.413-1.993 2.422 1.414zm-8.572-4.985l9.562 5.579-3.412 1.993-6.15-7.572z"/></svg>
                        Play Store
                    </button>
                </div>
            </div>

            <div class="relative">
                <div class="absolute -inset-4 bg-gradient-to-r from-rose-500 to-indigo-600 rounded-[3rem] blur-2xl opacity-20"></div>
                <div class="glass relative rounded-[2.5rem] p-10 overflow-hidden shadow-2xl">
                    <div class="flex items-center justify-between mb-10">
                        <div class="space-y-1">
                            <p class="text-xs font-black text-slate-500 uppercase tracking-[0.2em]">Monthly Spending</p>
                            <p class="text-4xl font-extrabold text-white">$2,450.00</p>
                        </div>
                        <div class="w-14 h-14 rounded-2xl bg-rose-500/20 border border-rose-500/40 flex items-center justify-center">
                            <svg class="w-6 h-6 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        </div>
                    </div>
                    
                    <div class="space-y-5">
                        <div class="flex items-center justify-between p-5 rounded-3xl bg-white/5 border border-white/5">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-2xl bg-indigo-500/20 flex items-center justify-center text-indigo-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                </div>
                                <div>
                                    <p class="text-base font-bold text-white">Electricity Bill</p>
                                    <p class="text-xs text-slate-500 font-medium tracking-wide">House • Due in 2 days</p>
                                </div>
                            </div>
                            <span class="text-lg font-black text-rose-400">-$120.00</span>
                        </div>
                        <div class="flex items-center justify-between p-5 rounded-3xl bg-white/5 border border-white/5">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 flex items-center justify-center text-emerald-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                </div>
                                <div>
                                    <p class="text-base font-bold text-white">Groceries</p>
                                    <p class="text-xs text-slate-500 font-medium tracking-wide">Shared • Added by Sarah</p>
                                </div>
                            </div>
                            <span class="text-lg font-black text-indigo-400">-$85.50</span>
                        </div>
                    </div>

                    <div class="mt-8 pt-8 border-t border-white/5 flex justify-center">
                        <div class="flex -space-x-3">
                            <div class="w-10 h-10 rounded-full border-2 border-[#0f172a] bg-slate-800 flex items-center justify-center text-xs font-bold uppercase">JD</div>
                            <div class="w-10 h-10 rounded-full border-2 border-[#0f172a] bg-rose-500 flex items-center justify-center text-xs font-bold uppercase text-white">SA</div>
                            <div class="w-10 h-10 rounded-full border-2 border-[#0f172a] bg-indigo-500 flex items-center justify-center text-xs font-bold uppercase text-white">MK</div>
                            <div class="w-10 h-10 rounded-full border-2 border-[#0f172a] bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-400">+2</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <section class="border-t border-white/5 bg-slate-900/30 backdrop-blur-sm py-16">
        <div class="max-w-7xl mx-auto px-8 grid grid-cols-2 md:grid-cols-4 gap-12">
            <div class="space-y-1">
                <p class="text-4xl font-black text-white tracking-tighter">10k+</p>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">Active Houses</p>
            </div>
            <div class="space-y-1">
                <p class="text-4xl font-black text-white tracking-tighter">99.9%</p>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">Uptime Sync</p>
            </div>
            <div class="space-y-1">
                <p class="text-4xl font-black text-white tracking-tighter">1s</p>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">Real-time Splits</p>
            </div>
            <div class="space-y-1">
                <p class="text-4xl font-black text-white tracking-tighter">Free</p>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">Open Beta</p>
            </div>
        </div>
    </section>

</body>
</html>