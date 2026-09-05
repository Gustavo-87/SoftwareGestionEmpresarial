<x-guest-layout>
    <div class="mb-7 text-center">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
            Iniciar sesión
        </h2>

        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Bienvenido al Sistema de Gestión Empresarial
        </p>
    </div>

    <!-- Estado de la sesión -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Correo electrónico -->
        <div>
            <x-input-label
                for="email"
                value="Correo electrónico"
            />

            <x-text-input
                id="email"
                class="block mt-1 w-full"
                type="email"
                name="email"
                :value="old('email')"
                required
                autofocus
                autocomplete="username"
                placeholder="usuario@empresa.com"
            />

            <x-input-error
                :messages="$errors->get('email')"
                class="mt-2"
            />
        </div>

        <!-- Contraseña -->
        <div class="mt-5">
            <x-input-label
                for="password"
                value="Contraseña"
            />

            <x-text-input
                id="password"
                class="block mt-1 w-full"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="Ingresa tu contraseña"
            />

            <x-input-error
                :messages="$errors->get('password')"
                class="mt-2"
            />
        </div>

        <!-- Recordarme -->
        <div class="mt-5">
            <label for="remember_me" class="inline-flex items-center">
                <input
                    id="remember_me"
                    type="checkbox"
                    class="rounded border-gray-300 dark:border-gray-600
                           bg-white dark:bg-slate-900
                           text-blue-600 shadow-sm
                           focus:ring-blue-500"
                    name="remember"
                >

                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">
                    Recordarme
                </span>
            </label>
        </div>

        <div class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">

            @if (Route::has('password.request'))
                <a
                    href="{{ route('password.request') }}"
                    class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                >
                    ¿Olvidaste tu contraseña?
                </a>
            @endif

            <button
                type="submit"
                class="inline-flex justify-center items-center
                       px-6 py-3
                       bg-blue-600 hover:bg-blue-700
                       text-white font-semibold
                       rounded-lg shadow-md
                       transition duration-200
                       focus:outline-none focus:ring-2
                       focus:ring-blue-500 focus:ring-offset-2"
            >
                Iniciar sesión
            </button>
        </div>

        <div class="mt-7 pt-6 border-t border-gray-200 dark:border-slate-700 text-center">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                ¿Aún no tienes una cuenta?

                <a
                    href="{{ route('register') }}"
                    class="font-semibold text-blue-600 dark:text-blue-400 hover:underline"
                >
                    Crear cuenta
                </a>
            </p>
        </div>
    </form>
</x-guest-layout>