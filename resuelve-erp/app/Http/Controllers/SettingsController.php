<?php

namespace App\Http\Controllers;

use App\Application\Configuracion\ActualizarConfiguracionCopropiedadInicial;
use App\Application\Autorizacion\AutorizacionContextual;
use App\Application\Contexto\ContextoInstitucionalNoConfigurado;
use App\Application\Contexto\ContextoOperativo;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly ActualizarConfiguracionCopropiedadInicial $actualizarConfiguracion
    ) {}

    public function edit(Request $request): View
    {
        abort_unless(app(AutorizacionContextual::class)->tienePermiso(app(ContextoOperativo::class), 'configuracion.gestionar'), 403);

        return view('settings.edit', ['settings' => SiteSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        try {
            $contexto = app(ContextoOperativo::class);
        } catch (ContextoInstitucionalNoConfigurado $exception) {
            return back()->withErrors(['contexto' => $exception->getMessage()])->withInput();
        }

        abort_unless(app(AutorizacionContextual::class)->tienePermiso($contexto, 'configuracion.gestionar'), 403);
        $data = $request->validate([
            'nombre_conjunto' => ['required', 'string', 'max:150'],
            'nit' => ['nullable', 'string', 'max:40'],
            'representante_legal' => ['nullable', 'string', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:180'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'dias_respuesta' => ['required', 'integer', 'between:1,120'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'logo_scale' => ['nullable', 'numeric', 'min:0.75', 'max:2.0'],
            'logo_offset_x' => ['nullable', 'integer', 'min:-50', 'max:50'],
            'logo_offset_y' => ['nullable', 'integer', 'min:-50', 'max:50'],
        ]);
        $data['color_principal'] = SiteSetting::COLOR_INSTITUCIONAL;
        $data['logo_scale'] = $data['logo_scale'] ?? 1.0;
        $data['logo_offset_x'] = $data['logo_offset_x'] ?? 0;
        $data['logo_offset_y'] = $data['logo_offset_y'] ?? 0;
        $settings = SiteSetting::first() ?? new SiteSetting();
        unset($data['logo']);
        $newLogoPath = null;
        $previousLogoPath = $settings->logo_path;

        if ($request->hasFile('logo')) {
            $newLogoPath = $request->file('logo')->store('branding', 'public');
            $data['logo_path'] = $newLogoPath;
        }

        $this->actualizarConfiguracion->execute($settings, $data);

        if ($newLogoPath && $previousLogoPath) {
            Storage::disk('public')->delete($previousLogoPath);
        }

        return back()->with('success', 'La configuración del conjunto fue actualizada.');
    }
}
