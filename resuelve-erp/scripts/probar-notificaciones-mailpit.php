<?php

use App\Application\Contexto\ContextoOperativo;
use App\Application\Contexto\ContextResolver;
use App\Application\Pqrs\AplicarAccionRapidaPqrs;
use App\Application\Pqrs\PresentarPqrs;
use App\Models\Copropiedad;
use App\Models\Organizacion;
use App\Models\SiteSetting;
use App\Models\TipoPqr;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$base = dirname(__DIR__);
$db = ':memory:';
$environment = [
    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $db,
    'DB_URL' => '', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array',
    'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'mailpit',
    'MAILPIT_HOST' => '127.0.0.1', 'MAILPIT_PORT' => '1025',
    'MAIL_FROM_ADDRESS' => 'gestionpqrs7@gmail.com', 'MAIL_FROM_NAME' => 'Resuelve ERP',
];
foreach ($environment as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('mail.default') !== 'mailpit' || config('mail.mailers.mailpit.host') !== '127.0.0.1' || (int) config('mail.mailers.mailpit.port') !== 1025 || config('queue.default') !== 'sync') {
    throw new RuntimeException('La demostración exige Mailpit local y cola síncrona. Revisa si hay configuración cacheada.');
}
if (DB::connection()->getDriverName() !== 'sqlite'
    || DB::connection()->getDatabaseName() !== $db) {
    throw new RuntimeException('La prueba exige su propia base SQLite aislada.');
}
// Solo consulta la API del buzón local; no modifica ni reenvía sus mensajes.
$consultarBuzon = static function (): array {
    $respuesta = file_get_contents('http://127.0.0.1:8025/api/v1/messages', false, stream_context_create(['http' => ['timeout' => 5]]));
    if ($respuesta === false) {
        throw new RuntimeException('Inicia Mailpit local antes de ejecutar la demostración.');
    }

    return json_decode($respuesta, true, 512, JSON_THROW_ON_ERROR)['messages'];
};
$mensajesPrevios = array_column($consultarBuzon(), 'ID');
Artisan::call('migrate', ['--force' => true]);
$org = Organizacion::create(['nombre' => 'Organización de prueba SMTP', 'estado' => 'activa']);
$cop = Copropiedad::create(['organizacion_id' => $org->id, 'nombre' => 'Copropiedad de prueba SMTP', 'estado' => 'activa']);
$settings = new SiteSetting(SiteSetting::defaults());
$settings->forceFill(['organizacion_id' => $org->id, 'copropiedad_id' => $cop->id, 'nombre_conjunto' => $cop->nombre])->save();
$user = User::factory()->create([
    'name' => 'Gestión PQRS — prueba aislada', 'email' => 'gestionpqrs7@gmail.com',
    'role' => 'admin', 'password' => bin2hex(random_bytes(16)), 'email_verified_at' => now(),
]);
foreach (['resuelve:crear-identidad-contextual-inicial', 'resuelve:migrar-rbac-spatie'] as $command) {
    if (Artisan::call($command) !== 0) {
        throw new RuntimeException('No se pudo preparar la identidad de prueba: '.$command);
    }
}
$contexto = app(ContextResolver::class)->resolverExplicito($org->id, $cop->id, $user->id);
app()->instance(ContextoOperativo::class, $contexto);
Auth::setUser($user);
$tipo = TipoPqr::create(['nombre' => 'Solicitud de prueba']);
$pqr = app(PresentarPqrs::class)->ejecutar($contexto, $user, [
    'asunto' => 'Prueba aislada de notificaciones', 'descripcion' => 'Datos sintéticos. Verificación de correo y campana.',
    'tipo_pqr_id' => $tipo->id,
]);
app(AplicarAccionRapidaPqrs::class)->ejecutar($contexto, $user, $pqr, ['estado' => 'en_revision']);
$pqr->forceFill(['fecha_limite_respuesta' => today()->addDays(2), 'last_reminder_at' => null])->save();
Artisan::call('pqrs:send-reminders');
$primera = Artisan::output();
Artisan::call('pqrs:send-reminders');
$segunda = Artisan::output();
$notificaciones = $user->notifications()->count();
if ($notificaciones !== 3 || ! str_contains($primera, 'Recordatorios enviados: 1') || ! str_contains($segunda, 'Recordatorios enviados: 0')) {
    throw new RuntimeException('La demostración de notificaciones no produjo los resultados esperados.');
}
$asuntosEsperados = ['Nueva solicitud radicada', 'Estado de solicitud actualizado', 'Solicitud próxima a vencer'];
$correos = array_values(array_filter($consultarBuzon(), static fn (array $mensaje): bool => ! in_array($mensaje['ID'], $mensajesPrevios, true)
    && ($mensaje['From']['Address'] ?? '') === 'gestionpqrs7@gmail.com'
    && in_array('gestionpqrs7@gmail.com', array_column($mensaje['To'], 'Address'), true)
    && in_array($mensaje['Subject'], $asuntosEsperados, true)
));
if (count($correos) !== 3 || array_diff($asuntosEsperados, array_column($correos, 'Subject'))) {
    throw new RuntimeException('Mailpit no confirmó los tres correos SMTP esperados.');
}
Artisan::call('schedule:list');
$resultado = [
    'base_aislada' => 'SQLite en memoria', 'campana' => $notificaciones,
    'eventos' => ['pqr_creada', 'pqr_estado_actualizado', 'pqr_recordatorio_vencimiento'],
    'correo' => 'SMTP local Mailpit; pendiente recepción en Gmail',
    'correos_smtp' => count($correos), 'asuntos' => array_column($correos, 'Subject'),
    'destinatario' => $user->email, 'recordatorio_primera_ejecucion' => 1,
    'recordatorio_reintento' => 0, 'tareas' => trim(Artisan::output()),
];
echo json_encode($resultado, JSON_UNESCAPED_UNICODE).PHP_EOL;
