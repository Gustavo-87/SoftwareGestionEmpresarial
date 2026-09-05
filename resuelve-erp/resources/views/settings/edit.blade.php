@extends(auth()->user()->esAdministradorSistema() ? 'layouts.admin' : 'layouts.app')
@section('titulo', 'Configuración')
@section('titulo_pagina', 'Configuración del conjunto')
@section('contenido')
    <section class="page-heading compact"><div><span class="eyebrow">Administración</span><h1>Identidad del conjunto</h1><p>Estos datos aparecerán en la plataforma y podrán adaptarse para cada propiedad residencial.</p></div></section>
    <form method="POST" action="{{ route('settings.update') }}" class="form-panel settings-panel" enctype="multipart/form-data">
        @csrf @method('PUT')
        <div class="form-intro"><span class="form-step">01</span><div><h2>Información institucional</h2><p>Configura el nombre visible, la información legal y los canales de contacto.</p></div></div>
        @if($errors->any())<x-notice variant="error" title="Revisa la información ingresada."><p>Hay campos que necesitan tu atención.</p></x-notice>@endif
        <div class="form-grid">
            <div class="field span-2 brand-upload">
                <label for="logo">Logo institucional</label>
                <div class="brand-preview-card">
                    <div class="brand-preview" id="brandPreview">
                        <img class="brand-logo-image" id="logoPreviewImg" src="{{ $settings->logo_path ? Storage::url($settings->logo_path) : asset('logo-resuelve.png') }}" data-brand-fallback="{{ asset('logo-resuelve.png') }}" onerror="this.onerror=null;this.src=this.dataset.brandFallback" alt="Vista previa del logo de {{ $settings->nombre_conjunto }}" style="transform:scale({{ $settings->logo_scale ?? 1 }}) translate({{ $settings->logo_offset_x ?? 0 }}px, {{ $settings->logo_offset_y ?? 0 }}px)">
                    </div>
                    <div>
                        <strong>Imagen de la propiedad</strong>
                        <p>PNG, JPG o WEBP de hasta 2 MB. Se conserva la proporción original en toda la plataforma.</p>
                        <label for="logo" class="button subtle">Seleccionar imagen</label>
                        <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only">
                        @error('logo')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </div>

            {{-- Controles de presentación del logo --}}
            <div class="field span-2">
                <label>Ajuste visual del logo</label>
                <div class="logo-presentation-controls">
                    <div class="logo-control">
                        <label for="logo_scale">Zoom</label>
                        <input type="range" id="logo_scale" name="logo_scale" min="0.75" max="2.0" step="0.05" value="{{ $settings->logo_scale ?? 1 }}">
                        <span id="logo_scale_value">{{ $settings->logo_scale ?? 1 }}</span>
                    </div>
                    <div class="logo-control">
                        <label for="logo_offset_x">Horizontal</label>
                        <input type="range" id="logo_offset_x" name="logo_offset_x" min="-50" max="50" step="1" value="{{ $settings->logo_offset_x ?? 0 }}">
                        <span id="logo_offset_x_value">{{ $settings->logo_offset_x ?? 0 }}</span>
                    </div>
                    <div class="logo-control">
                        <label for="logo_offset_y">Vertical</label>
                        <input type="range" id="logo_offset_y" name="logo_offset_y" min="-50" max="50" step="1" value="{{ $settings->logo_offset_y ?? 0 }}">
                        <span id="logo_offset_y_value">{{ $settings->logo_offset_y ?? 0 }}</span>
                    </div>
                    <button type="button" class="button subtle" id="logoReset">Restablecer</button>
                </div>
            </div>

            {{-- Previsualizaciones --}}
            <div class="field span-2">
                <label>Previsualización</label>
                <div class="logo-previews">
                    <div class="logo-preview-card">
                        <strong>Login</strong>
                        <div class="logo-preview-login">
                            <span class="brand-mark image-mark">
                                <img id="previewLogin" src="{{ $settings->logo_path ? Storage::url($settings->logo_path) : asset('logo-resuelve.png') }}" data-brand-fallback="{{ asset('logo-resuelve.png') }}" onerror="this.onerror=null;this.src=this.dataset.brandFallback" alt="Logo" style="transform:scale({{ $settings->logo_scale ?? 1 }}) translate({{ $settings->logo_offset_x ?? 0 }}px, {{ $settings->logo_offset_y ?? 0 }}px)">
                            </span>
                            <span><strong>Resuelve</strong><small>{{ $settings->nombre_conjunto }}</small></span>
                        </div>
                    </div>
                    <div class="logo-preview-card">
                        <strong>Navbar</strong>
                        <div class="logo-preview-navbar">
                            <span class="brand-mark image-mark">
                                <img id="previewNavbar" src="{{ $settings->logo_path ? Storage::url($settings->logo_path) : asset('logo-resuelve.png') }}" data-brand-fallback="{{ asset('logo-resuelve.png') }}" onerror="this.onerror=null;this.src=this.dataset.brandFallback" alt="Logo" style="transform:scale({{ $settings->logo_scale ?? 1 }}) translate({{ $settings->logo_offset_x ?? 0 }}px, {{ $settings->logo_offset_y ?? 0 }}px)">
                            </span>
                            <span><strong>Resuelve</strong></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="field span-2"><label for="nombre_conjunto">Nombre del conjunto *</label><input id="nombre_conjunto" name="nombre_conjunto" value="{{ old('nombre_conjunto', $settings->nombre_conjunto) }}" required placeholder="Ej. Conjunto Residencial Los Robles">@error('nombre_conjunto')<small class="field-error">{{ $message }}</small>@enderror</div>
            <div class="field"><label for="nit">NIT</label><input id="nit" name="nit" value="{{ old('nit', $settings->nit) }}" placeholder="900.000.000-0"></div>
            <div class="field"><label for="representante_legal">Representante legal</label><input id="representante_legal" name="representante_legal" value="{{ old('representante_legal', $settings->representante_legal) }}"></div>
            <div class="field"><label for="direccion">Dirección</label><input id="direccion" name="direccion" value="{{ old('direccion', $settings->direccion) }}"></div>
            <div class="field"><label for="ciudad">Ciudad</label><input id="ciudad" name="ciudad" value="{{ old('ciudad', $settings->ciudad) }}"></div>
            <div class="field"><label for="telefono">Teléfono</label><input id="telefono" name="telefono" value="{{ old('telefono', $settings->telefono) }}"></div>
            <div class="field"><label for="email">Correo institucional</label><input id="email" name="email" type="email" value="{{ old('email', $settings->email) }}"></div>
            <div class="field"><label for="dias_respuesta">Plazo estándar de respuesta</label><div class="input-suffix"><input id="dias_respuesta" name="dias_respuesta" type="number" min="1" max="120" value="{{ old('dias_respuesta', $settings->dias_respuesta) }}" required><span>días</span></div></div>
        </div>
        <div class="form-actions"><div><a href="{{ route('pqrs.index') }}" class="button ghost">Cancelar</a><button class="button primary" type="submit">Guardar configuración <span>→</span></button></div></div>
    </form>
@endsection
