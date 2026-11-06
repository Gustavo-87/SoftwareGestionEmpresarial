<?php

namespace App\Http\Controllers\Api;

use App\Application\Membresias\ConsultaMembresiasVigentes;
use App\Application\Roles\GestionarRolActivo;
use App\Http\Controllers\Controller;
use App\Models\MembresiaCopropiedad;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Autenticación de la API REST v1 (HU-API-01, Sprint 21).
 *
 * Emite tokens personales de Sanctum y resuelve el Rol activo de una Membresía
 * vigente con la misma semántica del flujo web (HU-USR-04 y HU-USR-08,
 * ADR-013): el usuario inactivo no inicia sesión, la selección de Rol solo
 * puede recaer en Roles ya asignados en la Copropiedad de la Membresía y no
 * concede permisos nuevos. El login web no se modifica.
 */
final class AuthController extends Controller
{
    /** Mismos límites que el login web: la protección por cuenta/IP es única entre canales. */
    private const INTENTOS_CUENTA = 3;

    private const INTENTOS_IP = 20;

    private const BLOQUEO_SEGUNDOS = 300;

    public function login(
        Request $request,
        ConsultaMembresiasVigentes $membresiasVigentes,
        GestionarRolActivo $rolActivo,
    ): JsonResponse {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'membresia_id' => ['nullable', 'integer'],
            'rol_id' => ['nullable', 'integer'],
        ], [
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo debe ser válido.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        $claveCuenta = $this->loginRateLimitKey('cuenta', $datos['email']);
        $claveIp = $this->loginRateLimitKey('ip', (string) $request->ip());

        if (RateLimiter::tooManyAttempts($claveCuenta, self::INTENTOS_CUENTA)
            || RateLimiter::tooManyAttempts($claveIp, self::INTENTOS_IP)) {
            $espera = max(RateLimiter::availableIn($claveCuenta), RateLimiter::availableIn($claveIp));
            $minutos = max(1, (int) ceil($espera / 60));

            return response()->json([
                'message' => 'Demasiados intentos fallidos de inicio de sesión. '
                    .'Podrás intentarlo de nuevo en '.$minutos.' '.($minutos === 1 ? 'minuto' : 'minutos').'.',
            ], 429);
        }

        $usuario = User::query()->where('email', $datos['email'])->first();
        if ($usuario === null || ! Hash::check($datos['password'], $usuario->password)) {
            RateLimiter::hit($claveCuenta, self::BLOQUEO_SEGUNDOS);
            RateLimiter::hit($claveIp, self::BLOQUEO_SEGUNDOS);

            return response()->json(['message' => 'El correo o la contraseña no son correctos.'], 401);
        }

        // HU-USR-08: los usuarios con users.estado ≠ 'activo' no inician sesión.
        if ($usuario->estado !== 'activo') {
            return response()->json([
                'message' => 'Tu cuenta está desactivada. Contacta con el administrador del sistema.',
            ], 403);
        }

        // Los ingresos correctos no acumulan intentos ni quedan limitados.
        RateLimiter::clear($claveCuenta);
        RateLimiter::clear($claveIp);

        $vigentes = $membresiasVigentes->paraUsuario($usuario);
        if ($vigentes->isEmpty()) {
            return response()->json([
                'message' => 'Se requiere una Membresía vigente para iniciar sesión en la API.',
            ], 403);
        }

        $membresia = $this->resolverMembresia($vigentes, $datos['membresia_id'] ?? null);

        if (isset($datos['rol_id'])) {
            // Valida que el Rol pertenezca al usuario en la Copropiedad de la
            // Membresía, persiste la selección y la audita (mismo mecanismo
            // web). Un Rol ajeno responde 422 y no concede permisos nuevos.
            $rolActivo->ejecutar($usuario, $membresia, (int) $datos['rol_id']);
        }

        $roles = $rolActivo->rolesAsignados($usuario, $membresia);
        $rolEfectivo = collect($roles)->firstWhere('id', $membresia->rol_activo_id);

        // Revalidación (ADR-013): una selección caducada se descarta y la
        // Membresía recupera la unión de Roles.

        $token = $usuario->createToken('api-v1')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'usuario' => [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'email' => $usuario->email,
            ],
            'membresia' => [
                'id' => $membresia->id,
                'copropiedad_id' => $membresia->copropiedad_id,
                'copropiedad' => $membresia->copropiedad?->nombre,
            ],
            'rol_activo' => $rolEfectivo === null ? null : [
                'id' => $rolEfectivo->id,
                'name' => $rolEfectivo->name,
            ],
            'roles' => collect($roles)
                ->map(fn ($rol): array => ['id' => $rol->id, 'name' => $rol->name])
                ->values()
                ->all(),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'La sesión de la API se cerró correctamente.']);
    }

    /**
     * Resuelve la Membresía vigente: la indicada (pertenencia y vigencia
     * validadas) o la única disponible. Una selección inválida o ambigua
     * responde 422 con el mismo criterio de selección del flujo web.
     */
    private function resolverMembresia(Collection $vigentes, ?int $membresiaId): MembresiaCopropiedad
    {
        if ($membresiaId !== null) {
            $seleccionada = $vigentes->firstWhere('id', $membresiaId);
            if ($seleccionada === null) {
                throw ValidationException::withMessages([
                    'membresia_id' => 'La Membresía seleccionada no pertenece al usuario o no está vigente.',
                ]);
            }

            return $seleccionada;
        }

        if ($vigentes->count() > 1) {
            throw ValidationException::withMessages([
                'membresia_id' => 'Selecciona la Membresía (membresia_id) para iniciar sesión en la API.',
            ]);
        }

        return $vigentes->first();
    }

    private function loginRateLimitKey(string $alcance, string $valor): string
    {
        return 'login:'.$alcance.':'.sha1($valor);
    }
}
