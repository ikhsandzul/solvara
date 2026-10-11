<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Petugas — Solvara</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full flex items-center justify-center">
<div class="w-full max-w-sm mx-auto px-4">

    {{-- Logo --}}
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-teal-500 text-white font-bold text-xl mb-3">S</div>
        <h1 class="text-2xl font-bold text-slate-800">Solvara</h1>
        <p class="text-slate-500 text-sm mt-1">Portal Petugas</p>
    </div>

    {{-- Card --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
        <h2 class="text-lg font-semibold text-slate-800 mb-6">Masuk ke Dashboard</h2>

        @if (session('error'))
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('dashboard.login') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="email">Email</label>
                <input id="email" name="email" type="email" autocomplete="email" required
                       value="{{ old('email') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500
                              {{ $errors->has('email') ? 'border-red-400 bg-red-50' : '' }}">
                @error('email')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="password">Kata Sandi</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                @error('password')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-2">
                <input id="remember" name="remember" type="checkbox" class="rounded border-slate-300 text-teal-500">
                <label for="remember" class="text-sm text-slate-600">Ingat saya</label>
            </div>

            <button type="submit"
                    class="w-full bg-teal-600 hover:bg-teal-700 text-white font-medium py-2.5 rounded-lg text-sm transition-colors">
                Masuk
            </button>
        </form>
    </div>

    <p class="text-center text-xs text-slate-400 mt-6">
        Solvara — Platform Pengaduan Publik
    </p>
</div>
</body>
</html>
