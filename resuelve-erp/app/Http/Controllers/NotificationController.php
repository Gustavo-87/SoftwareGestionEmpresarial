<?php
namespace App\Http\Controllers;
use App\Application\Contexto\ContextoOperativo;
use App\Application\Notificaciones\AbrirNotificacionContextual;
use App\Application\Notificaciones\ConsultaNotificacionesContextuales;
use App\Application\Notificaciones\MarcarNotificacionesContextualesComoLeidas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
class NotificationController extends Controller {
    public function index(Request $request, ContextoOperativo $contexto, ConsultaNotificacionesContextuales $consulta): View
    {
        return view('notifications.index', [
            'notifications' => $consulta->paginar($contexto, $request->user()),
            'unreadNotificationsCount' => $consulta->contarNoLeidas($contexto, $request->user()),
        ]);
    }

    public function read(Request $request, string $notification, ContextoOperativo $contexto, AbrirNotificacionContextual $abrir): RedirectResponse
    {
        $pqr = $abrir->ejecutar($contexto, $request->user(), $notification);

        return redirect()->route('pqrs.show', $pqr);
    }

    public function readAll(Request $request, ContextoOperativo $contexto, MarcarNotificacionesContextualesComoLeidas $marcar): RedirectResponse
    {
        $marcar->ejecutar($contexto, $request->user());

        return back()->with('success', 'Notificaciones marcadas como leídas.');
    }
}
