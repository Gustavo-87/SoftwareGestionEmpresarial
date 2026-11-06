<?php

namespace App\Application\Auditoria;

use App\Models\AuditLog;
use App\Models\MembresiaCopropiedad;
use App\Models\User;
use Illuminate\Http\Request;

final class RegistrarEventoSeguridad
{
    public function ejecutar(Request $request, string $evento, ?User $usuario = null, ?MembresiaCopropiedad $membresia = null): void
    {
        // Un acceso fallido no tiene contexto operativo de confianza. Sus
        // registros quedan reservados a la autoridad de plataforma.
        if ($evento === 'auth.login.exitoso' && $usuario && ! $membresia) {
            $opciones = MembresiaCopropiedad::query()->where('usuario_id', $usuario->id)
                ->where('estado', 'activa')->where('vigente_desde', '<=', now())
                ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>', now()))
                ->limit(2)->get();
            $membresia = $opciones->count() === 1 ? $opciones->first() : null;
        }

        $cuenta = mb_strtolower(trim((string) ($request->input('email') ?? $usuario?->email ?? '')));
        AuditLog::create([
            'user_id' => $usuario?->id,
            'action' => $evento,
            'auditable_type' => $usuario ? User::class : null,
            'auditable_id' => $usuario?->id,
            'ip_address' => $request->ip(),
            'organizacion_id' => $membresia?->organizacion_id,
            'copropiedad_id' => $membresia?->copropiedad_id,
            'metadata' => [
                'canal' => $request->is('api/*') ? 'api' : 'web',
                'cuenta_hash' => $cuenta === '' ? null : hash_hmac('sha256', $cuenta, (string) config('app.key')),
            ],
        ]);
    }
}
