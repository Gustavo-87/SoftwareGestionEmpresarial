<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PqrController;
use App\Http\Middleware\ResolverContextoApi;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API REST v1 — HU-API-01 (Sprint 21)
|--------------------------------------------------------------------------
| Autenticación con Sanctum sobre el modelo residencial autorizado. Los
| endpoints de consulta y radicación de PQRS no forman parte de este bloque:
| se documentarán al implementarse. Toda operación reutiliza los casos de uso
| y la autorización contextual existentes (ADR-008).
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    });

    // HU-API-01, bloque 2: consulta, detalle y radicación de PQRS. Cada
    // petición revalida usuario activo, Membresía vigente, Rol y contexto.
    // El parámetro es plano ({identificador}) para resolverlo con la misma
    // consulta contextual del binding web, que depende de sesión.
    Route::middleware(['auth:sanctum', ResolverContextoApi::class])->group(function () {
        Route::get('/pqrs', [PqrController::class, 'index'])->name('pqrs.index');
        Route::get('/pqrs/{identificador}', [PqrController::class, 'show'])->name('pqrs.show');
        Route::post('/pqrs', [PqrController::class, 'store'])->name('pqrs.store');
    });
});
