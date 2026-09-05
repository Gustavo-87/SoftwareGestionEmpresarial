<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SGE') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 flex items-center justify-center px-4 py-10">

            <div class="w-full max-w-md">

                <div class="text-center mb-8">
                    <a href="/" class="inline-flex items-center justify-center">
                        <div class="w-16 h-16 rounded-2xl bg-blue-600 text-white flex items-center justify-center text-2xl font-bold shadow-lg">
                            SGE
                        </div>
                    </a>

                    <h1 class="mt-4 text-3xl font-bold text-white">
                        Sistema de Gestión Empresarial
                    </h1>

                    <p class="mt-2 text-sm text-slate-300">
                        Gestión centralizada para tu organización
                    </p>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-2xl rounded-2xl px-8 py-8 border border-slate-200 dark:border-slate-700">
                    {{ $slot }}
                </div>

                <p class="mt-6 text-center text-sm text-slate-400">
                    © {{ date('Y') }} SGE · Software de Gestión Empresarial
                </p>

            </div>
        </div>
    </body>
</html>