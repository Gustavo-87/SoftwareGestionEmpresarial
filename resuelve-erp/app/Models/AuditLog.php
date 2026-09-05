<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; use Illuminate\Support\Str;
class AuditLog extends Model { protected $fillable=['user_id','action','auditable_type','auditable_id','ip_address','metadata']; protected function casts():array{return ['metadata'=>'array'];} public function user(){return $this->belongsTo(User::class);}

    public function getHumanActionAttribute(): string
    {
        $action = $this->action;

        // Traducciones de rutas técnicas a lenguaje humano.
        $map = [
            'pqrs.store' => 'Radicó una PQRS',
            'pqrs.update' => 'Actualizó una PQRS',
            'pqrs.destroy' => 'Eliminó una PQRS',
            'pqrs.quick-update' => 'Actualización rápida de PQRS',
            'pqrs.replies.store' => 'Registró una respuesta',
            'pqrs.replies.update' => 'Actualizó un borrador',
            'pqrs.replies.send' => 'Envió una respuesta oficial',
            'pqrs.replies.destroy' => 'Eliminó un borrador',
            'pqrs.comments.store' => 'Agregó un comentario interno',
            'pqrs.tags.sync' => 'Actualizó etiquetas',
            'pqrs.survey.store' => 'Registró valoración',
            'users.role.update' => 'Cambió rol de usuario',
            'users.update' => 'Actualizó usuario',
            'users.store' => 'Creó usuario',
            'users.destroy' => 'Eliminó usuario',
            'settings.update' => 'Actualizó configuración',
            'management.residents.update' => 'Actualizó datos de residente',
            'system_admin.promoted' => 'Promovió administrador del sistema',
            'organizacion.store' => 'Creó organización',
            'organizacion.update' => 'Actualizó organización',
            'organizacion.deactivate' => 'Desactivó organización',
            'organizacion.reactivate' => 'Reactivó organización',
            'copropiedad.store' => 'Creó copropiedad',
            'copropiedad.update' => 'Actualizó copropiedad',
            'copropiedad.deactivate' => 'Desactivó copropiedad',
            'copropiedad.reactivate' => 'Reactivó copropiedad',
            'membresia.store' => 'Creó membresía',
            'membresia.update' => 'Actualizó membresía',
            'membresia.suspend' => 'Suspendió membresía',
            'membresia.finalize' => 'Finalizó membresía',
            'membresia.role.assign' => 'Asignó rol a membresía',
            'membresia.role.revoke' => 'Revocó rol de membresía',
            'usuario.store' => 'Creó usuario',
            'usuario.update' => 'Actualizó usuario',
            'usuario.deactivate' => 'Desactivó usuario',
            'usuario.reactivate' => 'Reactivó usuario',
            'tag.create' => 'Creó etiqueta',
            'tag.update' => 'Actualizó etiqueta',
            'tag.activate' => 'Reactivó etiqueta',
            'tag.deactivate' => 'Desactivó etiqueta',
            'Etiqueta creada' => 'Etiqueta creada',
            'Etiqueta actualizada' => 'Etiqueta actualizada',
            'Etiqueta reactivada' => 'Etiqueta reactivada',
            'Etiqueta desactivada' => 'Etiqueta desactivada',
            'login.store' => 'Inicio de sesión',
        ];

        // Extraer nombre de ruta después del método HTTP.
        $parts = explode(' ', $action, 2);
        $method = strtoupper($parts[0] ?? '');
        $routeName = $parts[1] ?? '';

        // Si el primer token parece un método HTTP, buscar traducción.
        if (in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true) && isset($map[$routeName])) {
            return $map[$routeName];
        }

        // Si no es un método HTTP, buscar en el mapa directamente o devolver original.
        if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $map[$action] ?? $this->fallbackHumanize($action);
        }

        // Fallback: humanizar el nombre de ruta.
        return $this->fallbackHumanize($routeName);
    }

    public function getModuleAttribute(): string
    {
        $action = $this->action;
        $parts = explode(' ', $action, 2);
        $routeName = $parts[1] ?? $action;

        // Mapeo de prefijos de ruta a módulos legibles.
        if (Str::startsWith($routeName, ['pqrs.', 'pqrs.replies.', 'pqrs.comments.', 'pqrs.tags.', 'pqrs.survey.'])) return 'Gestión PQRS';
        if (Str::startsWith($routeName, ['admin.organizaciones.', 'organizacion.'])) return 'Organizaciones';
        if (Str::startsWith($routeName, ['admin.copropiedades.', 'copropiedad.'])) return 'Copropiedades';
        if (Str::startsWith($routeName, ['admin.membresias.', 'membresia.'])) return 'Membresías';
        if (Str::startsWith($routeName, ['admin.usuarios-globales.', 'usuario.', 'users.'])) return 'Usuarios';
        if (Str::startsWith($routeName, ['settings.'])) return 'Configuración';
        if (Str::startsWith($routeName, ['management.residents.'])) return 'Residentes';
        if (Str::startsWith($routeName, ['documentos.'])) return 'Documentos';
        if (Str::startsWith($routeName, ['login.', 'password.'])) return 'Autenticación';
        if (Str::startsWith($routeName, ['tag.'])) return 'Etiquetas';
        if (Str::startsWith($routeName, ['system_admin.'])) return 'Administración';

        // Fallback: extraer primer segmento.
        $segment = Str::before($routeName, '.');
        return Str::title(str_replace(['_', '-'], ' ', $segment)) ?: 'Sistema';
    }

    private function fallbackHumanize(string $action): string
    {
        // Remover método HTTP si está al inicio.
        $clean = preg_replace('/^(GET|POST|PUT|PATCH|DELETE)\s+/', '', $action);

        // Reemplazar puntos, guiones y underscores por espacios.
        $clean = str_replace(['.', '_', '-'], ' ', $clean);

        // Remover prefijos comunes de ruta.
        $clean = preg_replace('/^(admin|management|pqrs|users|settings)\s+/', '', $clean);

        // Capitalizar palabras.
        $clean = Str::title($clean);

        // Limpiar espacios múltiples.
        $clean = preg_replace('/\s+/', ' ', trim($clean));

        return $clean ?: $action;
    }

    public function getMethodBadgeAttribute(): string
    {
        return Str::before($this->action, ' ');
    }
}
