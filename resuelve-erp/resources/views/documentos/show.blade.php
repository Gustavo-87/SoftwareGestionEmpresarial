@extends('layouts.app')
@section('titulo', 'Documento')
@section('titulo_pagina', 'Documento')
@section('contenido')
@php
    $tipo = ['documento_general' => 'Documento general', 'reglamento' => 'Reglamento', 'manual_convivencia' => 'Manual de convivencia', 'acta' => 'Acta'];
    $categoria = ['normativo' => 'Normativo', 'administrativo' => 'Administrativo', 'gobierno_copropiedad' => 'Gobierno de la Copropiedad', 'contractual' => 'Contractual', 'financiero' => 'Financiero', 'comunicaciones' => 'Comunicaciones', 'otro' => 'Otro'];
    $acceso = ['administrativo' => 'Administrativo', 'interno' => 'Interno', 'comunidad' => 'Comunidad'];
    $ambito = ['organizacion' => 'Organización', 'copropiedad' => 'Copropiedad'];
    $estadoVersion = ['borrador' => 'Borrador', 'pendiente_aprobacion' => 'Pendiente de aprobación', 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada'];
    $versiones = $documento->versiones->sortByDesc('numero');
    $vigente = $documento->versionVigente();
    $archivado = $documento->estado->value === 'archivado';
    $borrador = $versiones->first(fn ($version) => $version->estado->value === 'borrador');
    $pendiente = $versiones->first(fn ($version) => $version->estado->value === 'pendiente_aprobacion');
    $hayRechazada = $versiones->contains(fn ($version) => $version->estado->value === 'rechazada');
    $faseActual = $archivado ? 'archivado' : ($vigente ? 'aprobada' : ($pendiente ? 'pendiente_aprobacion' : ($hayRechazada ? 'rechazada' : 'borrador')));
    $usuarioActual = auth()->user();
    $accionPrimaria = null;
    if (! $archivado) {
        if ($pendiente && $usuarioActual->can('approve', $documento)) {
            $accionPrimaria = 'aprobar';
        } elseif ($borrador && $usuarioActual->can('update', $documento)) {
            $accionPrimaria = 'someter';
        } elseif ($usuarioActual->can('update', $documento)) {
            $accionPrimaria = 'cargar';
        } elseif ($usuarioActual->can('archive', $documento)) {
            $accionPrimaria = 'archivar';
        }
    }
    $actuaciones = $documento->actuaciones->sortBy('id');
    $formatoFecha = function ($fecha, string $formato = 'd M Y'): string {
        if (! $fecha) {
            return '';
        }

        try {
            $fecha = $fecha instanceof \Carbon\CarbonInterface ? $fecha : \Illuminate\Support\Carbon::parse($fecha);

            return $fecha->translatedFormat($formato);
        } catch (\Throwable) {
            return '';
        }
    };
@endphp
    @if($errors->any())<x-notice variant="error" title="No fue posible completar la acción documental."><p>Revisa los datos y vuelve a intentarlo.</p></x-notice>@endif
    <section class="page-heading compact detail-heading"><div><a class="back-link" href="{{ route('documentos.index') }}">← Volver a Documentos</a><span class="eyebrow">{{ $tipo[$documento->tipo->value] ?? $documento->tipo->value }}</span><h1>{{ $documento->titulo }}</h1><p>{{ $documento->descripcion ?: 'Sin descripción registrada.' }}</p></div></section>

    <div class="document-detail">
        <section class="detail-card document-flow-card">
            <span class="eyebrow">Ciclo documental</span>
            <ol class="document-flow">
                @foreach(['borrador' => 'Borrador', 'pendiente_aprobacion' => 'Pendiente de aprobación', 'aprobada' => 'Aprobada'] as $fase => $etiqueta)
                    <li class="document-flow-step {{ $faseActual === $fase ? 'current' : '' }}"><span class="status {{ $fase }}"><i></i>{{ $etiqueta }}</span>@if($faseActual === $fase)<small>Estado actual</small>@endif</li>
                @endforeach
                @if($hayRechazada)
                    <li class="document-flow-step {{ $faseActual === 'rechazada' ? 'current' : '' }}"><span class="status rechazada"><i></i>Rechazada</span>@if($faseActual === 'rechazada')<small>Estado actual</small>@endif</li>
                @endif
                <li class="document-flow-step {{ $faseActual === 'archivado' ? 'current' : '' }}"><span class="status archivado"><i></i>Archivado</span>@if($faseActual === 'archivado')<small>Estado actual</small>@endif</li>
            </ol>
        </section>

        <div class="document-overview-grid">
            <aside class="detail-card metadata-card">
                <span class="eyebrow">Ficha del documento</span>
                <h2>{{ $documento->titulo }}</h2>
                <dl>
                    <div><dt>Tipo</dt><dd>{{ $tipo[$documento->tipo->value] ?? $documento->tipo->value }}</dd></div>
                    <div><dt>Categoría</dt><dd>{{ $categoria[$documento->categoria->value] ?? $documento->categoria->value }}</dd></div>
                    <div><dt>Nivel de acceso</dt><dd>{{ $acceso[$documento->nivel_acceso->value] ?? $documento->nivel_acceso->value }}</dd></div>
                    <div><dt>Propietario documental</dt><dd>{{ $documento->propietarioDocumental?->name ?? 'Sin propietario' }}</dd></div>
                    <div><dt>Ámbito</dt><dd>{{ $ambito[$documento->ambito->value] ?? $documento->ambito->value }}{{ $documento->copropiedad?->nombre ? ' · ' . $documento->copropiedad->nombre : '' }}</dd></div>
                    <div><dt>Creado</dt><dd>{{ $formatoFecha($documento->created_at) ?: 'Sin fecha registrada' }}{{ $documento->creador?->name ? ' · ' . $documento->creador->name : '' }}</dd></div>
                    @if($documento->archivado_at)
                        <div><dt>Archivado</dt><dd>{{ $formatoFecha($documento->archivado_at) }}{{ $documento->archivador?->name ? ' · ' . $documento->archivador->name : '' }}</dd></div>
                    @endif
                    <div><dt>Versión vigente</dt><dd>@if($vigente)Versión {{ $vigente->numero }} · desde {{ $formatoFecha($vigente->vigente_desde) ?: 'la fecha registrada' }}@else Sin versión vigente aprobada @endif</dd></div>
                </dl>
            </aside>

            <section class="detail-card metadata-card">
                <span class="eyebrow">Estado</span>
                <h2>{{ $archivado ? 'Archivado' : 'Activo' }}</h2>
                <div class="next-step">
                    <span class="eyebrow">Versión vigente</span>
                    <p>@if($vigente)Versión {{ $vigente->numero }} disponible desde {{ $formatoFecha($vigente->vigente_desde) ?: 'la fecha registrada' }}.@else No hay una versión aprobada y vigente disponible.@endif</p>
                </div>
                <div class="document-primary-action">
                    @if($accionPrimaria === 'aprobar')
                        @can('approve', $documento)<a class="button primary" href="#version-{{ $pendiente->id }}">Aprobar / Rechazar versión {{ $pendiente->numero }}</a>@endcan
                    @elseif($accionPrimaria === 'someter')
                        @can('update', $documento)<form method="POST" action="{{ route('documentos.versions.submit', [$documento, $borrador]) }}" data-confirm="Someter versión" data-confirm-message="La versión quedará pendiente de aprobación.">@csrf<button class="button primary">Someter a aprobación</button></form>@endcan
                    @elseif($accionPrimaria === 'cargar')
                        @can('update', $documento)<a class="button primary" href="#cargar-version">Cargar nueva versión</a>@endcan
                    @elseif($accionPrimaria === 'archivar')
                        @can('archive', $documento)<form method="POST" action="{{ route('documentos.archive', $documento) }}" data-confirm="Archivar Documento" data-confirm-message="El Documento dejará de estar activo para la Copropiedad.">@csrf @method('PATCH')<button class="button primary">Archivar Documento</button></form>@endcan
                    @endif
                    @if($accionPrimaria && $accionPrimaria !== 'archivar')
                        @can('archive', $documento)<form method="POST" action="{{ route('documentos.archive', $documento) }}" data-confirm="Archivar Documento" data-confirm-message="El Documento dejará de estar activo para la Copropiedad.">@csrf @method('PATCH')<button class="button subtle">Archivar Documento</button></form>@endcan
                    @endif
                </div>
            </section>
        </div>

        <section class="detail-card">
            <div class="detail-section">
                <div class="attachments-heading">
                    <div><span class="eyebrow">Historial de versiones</span><h2>Versión vigente y otras versiones</h2></div>
                    <span>{{ $versiones->count() }} registrada(s)</span>
                </div>
                @forelse($versiones as $version)
                    @php($esVigente = $vigente?->is($version))
                    <article class="document-version-card {{ $esVigente ? 'current' : '' }}" id="version-{{ $version->id }}">
                        <div class="document-version-heading">
                            <div><span class="status {{ $version->estado->value }}"><i></i>{{ $estadoVersion[$version->estado->value] ?? $version->estado->value }}</span><strong>Versión {{ $version->numero }}</strong><small>{{ $version->nombre_original }}</small></div>
                            @if($esVigente)<span class="current-version-badge">Vigente</span>@endif
                        </div>
                        <dl class="version-meta">
                            <div><dt>Archivo</dt><dd>{{ strtoupper($version->extension) }} · {{ $version->tamano_bytes >= 1048576 ? round($version->tamano_bytes / 1048576, 1) . ' MB' : max(1, round($version->tamano_bytes / 1024, 1)) . ' KB' }}</dd></div>
                            <div><dt>Origen</dt><dd>{{ $version->origen->value === 'sistema' ? 'Sistema' : 'Usuario' }}</dd></div>
                            <div><dt>Cargada</dt><dd>{{ $formatoFecha($version->created_at) ?: 'Sin fecha registrada' }}{{ $version->nombre_cargador ? ' · ' . $version->nombre_cargador : '' }}</dd></div>
                            @if($version->sometida_at || $version->nombre_sometedor)
                                <div><dt>Sometida</dt><dd>{{ $formatoFecha($version->sometida_at) ?: 'Sin fecha registrada' }}{{ $version->nombre_sometedor ? ' · ' . $version->nombre_sometedor : '' }}</dd></div>
                            @endif
                            @if($version->aprobada_at || $version->nombre_aprobador)
                                <div><dt>Aprobada</dt><dd>{{ $formatoFecha($version->aprobada_at) ?: 'Sin fecha registrada' }}{{ $version->nombre_aprobador ? ' · ' . $version->nombre_aprobador : '' }}</dd></div>
                            @endif
                            @if($version->rechazada_at || $version->nombre_rechazador)
                                <div><dt>Rechazada</dt><dd>{{ $formatoFecha($version->rechazada_at) ?: 'Sin fecha registrada' }}{{ $version->nombre_rechazador ? ' · ' . $version->nombre_rechazador : '' }}</dd></div>
                            @endif
                            <div><dt>Vigencia</dt><dd>{{ $formatoFecha($version->vigente_desde) ?: 'Sin fecha de inicio' }} — {{ $formatoFecha($version->vigente_hasta) ?: 'Sin fecha de finalización' }}</dd></div>
                            <div><dt>Sustitución</dt><dd>@if($version->sustituyeVersion)Sustituye la versión {{ $version->sustituyeVersion->numero }}@elseif($version->sucesora->isNotEmpty())Sustituida por la versión {{ $version->sucesora->first()->numero }}@else Sin sustitución registrada @endif</dd></div>
                        </dl>
                        @if($version->observacion_rechazo)
                            <div class="version-rejection"><span class="eyebrow">Observación de rechazo</span><p>{{ $version->observacion_rechazo }}</p></div>
                        @endif
                        <div class="document-version-actions">
                            @can('view', $documento)<a class="button subtle" href="{{ route('documentos.versions.download', [$documento, $version]) }}">↓ Descargar</a>@if($version->esPrevisualizable())<a class="button subtle" href="{{ route('documentos.versions.preview', [$documento, $version]) }}" target="_blank" rel="noopener">Vista previa</a>@endif @endcan
                            @if($version->estado->value === 'borrador')@can('update', $documento)<form method="POST" action="{{ route('documentos.versions.submit',[$documento,$version]) }}" data-confirm="Someter versión" data-confirm-message="La versión quedará pendiente de aprobación.">@csrf<button class="button subtle">Someter a aprobación</button></form>@endcan @endif
                            @if($version->estado->value === 'pendiente_aprobacion')@can('approve', $documento)<details class="document-action"><summary class="button primary">Aprobar versión</summary><form method="POST" action="{{ route('documentos.versions.approve',[$documento,$version]) }}" data-confirm="Aprobar versión" data-confirm-message="Confirma la vigencia de esta versión.">@csrf<div class="field"><label>Vigente desde<input name="vigente_desde" type="date" required></label></div><div class="field"><label>Vigente hasta (opcional)<input name="vigente_hasta" type="date"></label></div><button class="button primary">Confirmar aprobación</button></form></details><details class="document-action"><summary class="button danger">Rechazar versión</summary><form method="POST" action="{{ route('documentos.versions.reject',[$documento,$version]) }}" data-confirm="Rechazar versión" data-confirm-message="La observación quedará registrada en el historial.">@csrf<div class="field"><label>Observación requerida<textarea name="observacion_rechazo" required></textarea></label></div><button class="button danger">Confirmar rechazo</button></form></details>@endcan @endif
                        </div>
                    </article>
                @empty
                    <div class="empty-state"><span class="empty-illustration"><i></i><b>✓</b></span><h3>Sin versiones registradas</h3><p>Este Documento aún no tiene versiones cargadas.</p></div>
                @endforelse
                @can('update', $documento)
                <details class="document-action" id="cargar-version">
                    <summary class="button subtle">Cargar nueva versión</summary>
                    <form method="POST" action="{{ route('documentos.versions.store',$documento) }}" enctype="multipart/form-data" class="document-upload-form">
                        @csrf
                        <div class="field"><label>Archivo<input type="file" name="archivo" required></label>@error('archivo')<small class="field-error">{{ $message }}</small>@enderror</div>
                        <div class="field"><label>Versión sustituida (opcional)<select name="sustituye_version_id"><option value="">No sustituye una versión</option>@foreach($versiones as $version)<option value="{{ $version->id }}">Versión {{ $version->numero }} · {{ $estadoVersion[$version->estado->value] ?? $version->estado->value }}</option>@endforeach</select></label><small class="field-help">Solo se muestran versiones de este Documento.</small></div>
                        <button class="button primary">Cargar versión</button>
                    </form>
                </details>
                <p class="section-help">La nueva versión quedará en borrador hasta que sea sometida y aprobada.</p>
                @endcan
            </div>
        </section>

        <section class="detail-card">
            <div class="detail-section">
                <span class="eyebrow">Actividad</span>
                <h2>Línea de tiempo del documento</h2>
                <div class="activity-timeline">
                    @forelse($actuaciones as $actuacion)
                        <div class="activity-item"><i></i><div>
                            @if($actuacion->created_at)<small class="activity-date">{{ $formatoFecha($actuacion->created_at, 'd M Y · H:i') }}</small>@endif
                            <strong>{{ $actuacion->detalle ?: $actuacion->accion }}</strong>
                            <span>{{ $actuacion->nombre_actor ?? 'Sistema' }}</span>
                        </div></div>
                    @empty
                        <div class="empty-attachments"><p>Aún no hay actuaciones registradas para este Documento.</p></div>
                    @endforelse
                </div>
            </div>
        </section>

    </div>
@endsection
