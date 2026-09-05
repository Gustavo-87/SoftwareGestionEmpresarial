<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Admin\MembresiaController;
use App\Http\Controllers\Admin\MembresiaRolController;
use App\Http\Controllers\Admin\OrganizacionController;
use App\Http\Controllers\Admin\CopropiedadController;
use App\Http\Controllers\Admin\UsuarioGlobalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PqrController;
use App\Http\Controllers\SettingsController;
use App\Models\PqrAttachment;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\PqrReplyController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PqrInternalCommentController;
use App\Http\Controllers\PqrQuickActionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ComplementaryController;
use App\Http\Controllers\ContextoSelectorController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\DocumentoVersionController;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'panel' : 'login');
});

Route::middleware('guest')->group(function () {
    Route::get('/iniciar-sesion', [AuthController::class, 'create'])->name('login');
    Route::post('/iniciar-sesion', [AuthController::class, 'store'])->name('login.store');
    Route::get('/recuperar-contrasena', [AuthController::class, 'forgotPassword'])->name('password.request');
    Route::post('/recuperar-contrasena', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/restablecer-contrasena/{token}', [AuthController::class, 'resetPassword'])->name('password.reset');
    Route::post('/restablecer-contrasena', [AuthController::class, 'updatePassword'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/panel', [PqrController::class, 'panel'])->name('panel');
    Route::post('/cerrar-sesion', [AuthController::class, 'destroy'])->name('logout');
    Route::post('/cambiar-contexto', [ContextoSelectorController::class, 'cambiar'])->name('contexto.cambiar');
    Route::get('/configuracion', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/configuracion', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/adjuntos/{attachment}', function (PqrAttachment $attachment) {
        abort_unless(request()->user()->can('view', $attachment->pqr), 403);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    })->name('attachments.download');
    Route::resource('pqrs', PqrController::class);
    Route::get('/documentos', [DocumentoController::class, 'index'])->name('documentos.index');
    Route::get('/documentos/crear', [DocumentoController::class, 'create'])->name('documentos.create');
    Route::post('/documentos', [DocumentoController::class, 'store'])->name('documentos.store');
    Route::get('/documentos/{documento}', [DocumentoController::class, 'show'])->name('documentos.show');
    Route::patch('/documentos/{documento}/archivar', [DocumentoController::class, 'archive'])->name('documentos.archive');
    Route::post('/documentos/{documento}/versiones', [DocumentoVersionController::class, 'store'])->name('documentos.versions.store');
    Route::post('/documentos/{documento}/versiones/{documentoVersion}/someter', [DocumentoVersionController::class, 'submit'])->name('documentos.versions.submit');
    Route::post('/documentos/{documento}/versiones/{documentoVersion}/aprobar', [DocumentoVersionController::class, 'approve'])->name('documentos.versions.approve');
    Route::post('/documentos/{documento}/versiones/{documentoVersion}/rechazar', [DocumentoVersionController::class, 'reject'])->name('documentos.versions.reject');
    Route::get('/documentos/{documento}/versiones/{documentoVersion}/descargar', [DocumentoVersionController::class, 'download'])->name('documentos.versions.download');
    Route::get('/documentos/{documento}/versiones/{documentoVersion}/preview', [DocumentoVersionController::class, 'preview'])->name('documentos.versions.preview');
    Route::post('/pqrs/{pqr}/respuestas', [PqrReplyController::class, 'store'])->name('pqrs.replies.store');
    Route::put('/pqrs/{pqr}/respuestas/{reply}', [PqrReplyController::class, 'update'])->name('pqrs.replies.update');
    Route::post('/pqrs/{pqr}/respuestas/{reply}/enviar', [PqrReplyController::class, 'send'])->name('pqrs.replies.send');
    Route::delete('/pqrs/{pqr}/respuestas/{reply}', [PqrReplyController::class, 'destroy'])->name('pqrs.replies.destroy');
    Route::post('/pqrs/{pqr}/comentarios-internos', [PqrInternalCommentController::class, 'store'])->name('pqrs.comments.store');
    Route::patch('/pqrs/{pqr}/accion-rapida', [PqrQuickActionController::class, 'update'])->name('pqrs.quick-update');
    Route::get('/pqrs/{pqr}/respuestas/{reply}/archivos/{file}', [PqrReplyController::class, 'download'])->name('pqrs.replies.download');
    Route::get('/notificaciones', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notificaciones/leer-todas', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('/notificaciones/{notification}/leer', [NotificationController::class, 'read'])->name('notifications.read');
    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/usuarios', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('/usuarios', [UserManagementController::class, 'store'])->name('users.store');
    Route::get('/usuarios/{user}/editar', [UserManagementController::class, 'edit'])->name('users.edit');
    Route::put('/usuarios/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::delete('/usuarios/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
    Route::patch('/usuarios/{user}/rol', [UserManagementController::class, 'updateRole'])->name('users.role.update');
    Route::get('/informes/pqrs.csv', [ReportController::class, 'csv'])->name('reports.csv');
    Route::get('/informes/pqrs.xlsx', [ReportController::class, 'xlsx'])->name('reports.xlsx');
    Route::get('/informes/pqrs.pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    Route::get('/gestion/carga', [ComplementaryController::class, 'workload'])->name('management.workload');
    Route::get('/gestion/herramientas', [ComplementaryController::class, 'tools'])->name('management.tools');
    Route::post('/gestion/plantillas', [ComplementaryController::class, 'template'])->name('management.templates.store');
    Route::post('/gestion/etiquetas', [ComplementaryController::class, 'tag'])->name('management.tags.store');
    Route::patch('/gestion/etiquetas/{tag}', [ComplementaryController::class, 'updateTag'])->name('management.tags.update');
    Route::patch('/gestion/etiquetas/{tag}/estado', [ComplementaryController::class, 'tagStatus'])->name('management.tags.status');
    Route::post('/gestion/reglas', [ComplementaryController::class, 'rule'])->name('management.rules.store');
    Route::patch('/pqrs/{pqr}/etiquetas', [ComplementaryController::class, 'syncTags'])->name('pqrs.tags.sync');
    Route::post('/pqrs/{pqr}/satisfaccion', [ComplementaryController::class, 'survey'])->name('pqrs.survey.store');
    Route::get('/residentes', [ComplementaryController::class, 'residents'])->name('management.residents');
    Route::patch('/residentes/{user}/unidad', [ComplementaryController::class, 'resident'])->name('management.residents.update');
    Route::get('/auditoria', [ComplementaryController::class, 'audit'])->name('management.audit');

    Route::middleware('admin.sistema')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::resource('membresias', MembresiaController::class);
        Route::post('membresias/{membresia}/suspender', [MembresiaController::class, 'suspender'])->name('membresias.suspend');
        Route::patch('membresias/{membresia}/suspender', [MembresiaController::class, 'suspender'])->name('membresias.suspend');
        Route::patch('membresias/{membresia}/finalizar', [MembresiaController::class, 'finalizar'])->name('membresias.finalize');
        Route::post('membresias/{membresia}/roles', [MembresiaRolController::class, 'store'])->name('membresias.roles.store');
        Route::delete('membresias/{membresia}/roles/{rol}', [MembresiaRolController::class, 'destroy'])->name('membresias.roles.destroy');
        Route::resource('organizaciones', OrganizacionController::class)->parameters(['organizaciones' => 'organizacion'])->except(['destroy']);
        Route::patch('organizaciones/{organizacion}/desactivar', [OrganizacionController::class, 'desactivar'])->name('organizaciones.desactivar');
        Route::patch('organizaciones/{organizacion}/reactivar', [OrganizacionController::class, 'reactivar'])->name('organizaciones.reactivar');
        Route::resource('copropiedades', CopropiedadController::class)->parameters(['copropiedades' => 'copropiedad'])->except(['destroy']);
        Route::patch('copropiedades/{copropiedad}/desactivar', [CopropiedadController::class, 'desactivar'])->name('copropiedades.desactivar');
        Route::patch('copropiedades/{copropiedad}/reactivar', [CopropiedadController::class, 'reactivar'])->name('copropiedades.reactivar');
        Route::resource('usuarios-globales', UsuarioGlobalController::class)->parameters(['usuarios-globales' => 'usuario'])->except(['destroy']);
        Route::patch('usuarios-globales/{usuario}/desactivar', [UsuarioGlobalController::class, 'desactivar'])->name('usuarios-globales.desactivar');
        Route::patch('usuarios-globales/{usuario}/reactivar', [UsuarioGlobalController::class, 'reactivar'])->name('usuarios-globales.reactivar');
    });
});
// Agregar rutas de copropiedades
