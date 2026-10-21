<?php

namespace App\Http\Controllers;

use App\Application\Roles\GestionarRolActivo;
use App\Models\MembresiaCopropiedad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AuthController extends Controller
{
    /** Intentos fallidos permitidos por cuenta antes del bloqueo temporal. */
    private const INTENTOS_CUENTA = 3;

    /** Intentos fallidos permitidos por IP: limita intentos distribuidos sin
     * bloquear a toda una red por un único error. */
    private const INTENTOS_IP = 20;

    /** Solicitudes de recuperación de contraseña permitidas por cuenta. */
    private const SOLICITUDES_CONTRASENA = 3;

    /** Solicitudes de recuperación de contraseña permitidas por IP. */
    private const SOLICITUDES_CONTRASENA_IP = 10;

    /** Duración del bloqueo temporal: 15 minutos. */
    private const BLOQUEO_SEGUNDOS = 900;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $claveCuenta = $this->loginRateLimitKey('cuenta', $credentials['email']);
        $claveIp = $this->loginRateLimitKey('ip', (string) $request->ip());

        if (RateLimiter::tooManyAttempts($claveCuenta, self::INTENTOS_CUENTA)
            || RateLimiter::tooManyAttempts($claveIp, self::INTENTOS_IP)) {
            $espera = max(RateLimiter::availableIn($claveCuenta), RateLimiter::availableIn($claveIp));
            $minutos = max(1, (int) ceil($espera / 60));

            // Mensaje idéntico exista o no la cuenta: no revela su existencia.
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Demasiados intentos fallidos de inicio de sesión. '
                        .'Podrás intentarlo de nuevo en '.$minutos.' '.($minutos === 1 ? 'minuto' : 'minutos').'.',
                ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($claveCuenta, self::BLOQUEO_SEGUNDOS);
            RateLimiter::hit($claveIp, self::BLOQUEO_SEGUNDOS);

            return back()
                ->withErrors(['email' => 'El correo o la contraseña no son correctos.'])
                ->onlyInput('email');
        }

        // HU-USR-08: los usuarios con users.estado ≠ 'activo' no inician sesión.
        // Auth::logout() rota el remember_token, por lo que un intento con
        // "recordarme" de una cuenta desactivada tampoco deja acceso persistente.
        if (Auth::user()->estado !== 'activo') {
            Auth::logout();

            return back()
                ->withErrors(['email' => 'Tu cuenta está desactivada. Contacta con el administrador del sistema.'])
                ->onlyInput('email');
        }

        // Los ingresos correctos no acumulan intentos ni quedan limitados.
        RateLimiter::clear($claveCuenta);
        RateLimiter::clear($claveIp);
        $request->session()->regenerate();

        $usuario = Auth::user();

        // Flujo específico del administrador de plataforma.
        if ($usuario->esAdministradorSistema()) {
            return redirect()->intended(route('admin.index'));
        }

        $membresias = $this->membresiasVigentes($usuario);

        // Flujo específico de usuarios sin membresía operativa.
        if ($membresias->isEmpty()) {
            return redirect()->intended(route('panel'));
        }

        $membresia = $membresias->count() === 1 ? $membresias->first() : null;
        if ($membresia !== null) {
            $request->session()->put('copropiedad_activa_id', $membresia->copropiedad_id);
            $roles = collect(app(GestionarRolActivo::class)->rolesAsignados($usuario, $membresia));

            if ($roles->isEmpty()) {
                return redirect()->intended(route('panel'));
            }

            // Con un único Rol autorizado se selecciona automáticamente.
            if ($roles->count() === 1) {
                $rol = $roles->first();
                if ((int) $membresia->rol_activo_id !== (int) $rol->id) {
                    app(GestionarRolActivo::class)->ejecutar($usuario, $membresia, (int) $rol->id);
                }

                return redirect()->intended(route('panel'));
            }
        }

        // Varios Roles o varias Copropiedades: se exige elegir antes de operar.
        $request->session()->put('acceso_seleccion_pendiente', true);

        return redirect()->route('acceso.seleccion');
    }

    private function loginRateLimitKey(string $alcance, string $valor): string
    {
        return 'login:'.$alcance.':'.sha1($valor);
    }

    /** @return \Illuminate\Support\Collection<int, MembresiaCopropiedad> */
    private function membresiasVigentes($usuario): \Illuminate\Support\Collection
    {
        return MembresiaCopropiedad::query()
            ->where('usuario_id', $usuario->id)
            ->where('estado', 'activa')
            ->where('vigente_desde', '<=', now())
            ->where(fn ($query) => $query
                ->whereNull('vigente_hasta')
                ->orWhere('vigente_hasta', '>', now()))
            ->orderBy('id')
            ->get();
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Tu sesión se cerró correctamente.');
    }

    public function forgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);
        $request->validate(['email' => ['required', 'email']]);

        $claveCuenta = 'password.email:cuenta:'.sha1((string) $request->input('email'));
        $claveIp = 'password.email:ip:'.sha1((string) $request->ip());

        if (! RateLimiter::tooManyAttempts($claveCuenta, self::SOLICITUDES_CONTRASENA)
            && ! RateLimiter::tooManyAttempts($claveIp, self::SOLICITUDES_CONTRASENA_IP)) {
            RateLimiter::hit($claveCuenta, self::BLOQUEO_SEGUNDOS);
            RateLimiter::hit($claveIp, self::BLOQUEO_SEGUNDOS);
            Password::sendResetLink($request->only('email'));
        }

        // Respuesta única: no revela si la cuenta existe ni si se superó el
        // límite de solicitudes.
        return back()->with('success', 'Si existe una cuenta con ese correo, recibirás un enlace para restablecer la contraseña en unos minutos.');
    }

    public function resetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset($data, function ($user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Tu contraseña fue actualizada correctamente. Ya puedes iniciar sesión.');
        }

        // Mensaje único: no revela si la cuenta existe. El enlace ya utilizado
        // o caducado queda inutilizable y se orienta a solicitar uno nuevo.
        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'El enlace no es válido o ha caducado. Solicita uno nuevo.']);
    }
}
