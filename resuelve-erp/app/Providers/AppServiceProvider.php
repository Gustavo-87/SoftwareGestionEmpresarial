<?php

namespace App\Providers;

use App\Application\Contexto\ContextoOperativo;
use App\Application\Contexto\ContextResolver;
use App\Application\Autorizacion\AutorizacionContextual;
use App\Models\MembresiaCopropiedad;
use App\Application\Documentos\ConsultaDocumentosContextuales;
use App\Application\Pqrs\ConsultaPqrsContextuales;
use App\Application\Notificaciones\ConsultaNotificacionesContextuales;
use App\Application\Notificaciones\ResolverDestinatariosNotificacionPqrs;
use App\Application\Notificaciones\EmitirNotificacionPqrs;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\PqrAttachment;
use App\Models\SiteSetting;
use App\Policies\DocumentoPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(ContextResolver::class, fn () => new ContextResolver());
        $this->app->scoped(AutorizacionContextual::class, fn () => new AutorizacionContextual());
        $this->app->scoped(ConsultaDocumentosContextuales::class, fn () => new ConsultaDocumentosContextuales());
        $this->app->scoped(ConsultaPqrsContextuales::class, fn () => new ConsultaPqrsContextuales());
        $this->app->scoped(ConsultaNotificacionesContextuales::class, fn ($app) => new ConsultaNotificacionesContextuales($app->make(AutorizacionContextual::class)));
        $this->app->scoped(ResolverDestinatariosNotificacionPqrs::class, fn ($app) => new ResolverDestinatariosNotificacionPqrs($app->make(ContextResolver::class), $app->make(AutorizacionContextual::class)));
        $this->app->scoped(EmitirNotificacionPqrs::class, fn ($app) => new EmitirNotificacionPqrs(
            $app->make(ConsultaPqrsContextuales::class),
            $app->make(ResolverDestinatariosNotificacionPqrs::class),
            $app->make(ContextResolver::class),
        ));
        $this->app->scoped(
            ContextoOperativo::class,
            fn ($app) => $app->make(ContextResolver::class)
                ->resolverParaHttp($app->make('request'))
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Pagination\AbstractPaginator::defaultView('vendor.pagination.default');
        Gate::policy(Documento::class, DocumentoPolicy::class);
        Gate::define('administrar-sistema', fn ($user) => $user->esAdministradorSistema());
        Route::bind('pqr', fn (string $value) => app(ConsultaPqrsContextuales::class)
            ->resolver(app(ContextoOperativo::class), $value));
        Route::bind('documento', fn (string $value) => app(ConsultaDocumentosContextuales::class)
            ->resolver(app(ContextoOperativo::class), $value));
        Route::bind('documentoVersion', function (string $value): DocumentoVersion {
            $documento = request()->route('documento');
            abort_unless($documento instanceof Documento, 404);

            return app(ConsultaDocumentosContextuales::class)
                ->resolverVersion(app(ContextoOperativo::class), $documento, $value);
        });
        Route::bind('attachment', function (string $value): PqrAttachment {
            $contexto = app(ContextoOperativo::class);
            $consulta = app(ConsultaPqrsContextuales::class);

            return PqrAttachment::query()
                ->whereKey($value)
                ->whereIn('pqr_id', $consulta->para($contexto)->select('id'))
                ->firstOrFail();
        });
        View::composer('*', fn ($view) => $view->with('siteSettings', SiteSetting::current()));
        View::composer('layouts.app', function ($view): void {
            $contador = 0;
            $navegacion = [];
            if (Auth::check()) {
                try {
                    $contexto = app(ContextoOperativo::class);
                    $autorizacion = app(AutorizacionContextual::class);
                    $navegacion = [
                        'pqrs' => Auth::user()->can('viewAny', \App\Models\Pqr::class),
                        'verTodasPqrs' => $autorizacion->tienePermiso($contexto, 'pqrs.ver_todas'),
                        'crearPqrs' => Auth::user()->can('create', \App\Models\Pqr::class),
                        'gestionarPqrs' => $autorizacion->tienePermiso($contexto, 'pqrs.gestionar'),
                        'exportarInformes' => $autorizacion->tienePermiso($contexto, 'informes.exportar'),
                        'documentos' => Auth::user()->can('viewAny', Documento::class),
                        'notificaciones' => $autorizacion->tienePermiso($contexto, 'notificaciones.consultar'),
                        'configuracion' => $autorizacion->tienePermiso($contexto, 'configuracion.gestionar'),
                        'usuarios' => $autorizacion->tienePermiso($contexto, 'usuarios.gestionar'),
                        'carga' => $autorizacion->tienePermiso($contexto, 'gestion.carga_ver'),
                        'herramientas' => $autorizacion->tienePermiso($contexto, 'gestion.herramientas_gestionar'),
                        'residentes' => $autorizacion->tienePermiso($contexto, 'residentes.gestionar'),
                        'auditoria' => $autorizacion->tienePermiso($contexto, 'auditoria.ver'),
                        'organizacion' => $contexto->organizacion,
                        'copropiedad' => $contexto->copropiedad,
                        'roles' => $contexto->clavesRoles(),
                    ];
                    if ($navegacion['notificaciones']) {
                        $contador = app(ConsultaNotificacionesContextuales::class)->contarNoLeidas(
                            $contexto,
                            Auth::user(),
                        );
                    }
                } catch (AuthorizationException) {
                    $contador = 0;
                }
                $navegacion['esAdministradorSistema'] = Auth::user()->esAdministradorSistema();
            }

            $copropiedadesDisponibles = [];
            if (Auth::check()) {
                $copropiedadesDisponibles = MembresiaCopropiedad::query()
                    ->where('usuario_id', Auth::id())
                    ->where('estado', 'activa')
                    ->where('vigente_desde', '<=', now())
                    ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>', now()))
                    ->with('copropiedad')
                    ->get()
                    ->pluck('copropiedad')
                    ->filter()
                    ->values()
                    ->all();
            }

            $view->with([
                'unreadNotificationsCount' => $contador,
                'navegacion' => $navegacion,
                'copropiedadesDisponibles' => $copropiedadesDisponibles,
            ]);
        });
    }
}
