@extends('layouts.app')
@section('titulo', 'Documentos')
@section('titulo_pagina', 'Documentos')
@section('contenido')
@php($tipo = ['documento_general' => 'Documento general', 'reglamento' => 'Reglamento', 'manual_convivencia' => 'Manual de convivencia', 'acta' => 'Acta'])
@php($categoria = ['normativo' => 'Normativo', 'administrativo' => 'Administrativo', 'gobierno_copropiedad' => 'Gobierno de la Copropiedad', 'contractual' => 'Contractual', 'financiero' => 'Financiero', 'comunicaciones' => 'Comunicaciones', 'otro' => 'Otro'])
@php($acceso = ['administrativo' => 'Administrativo', 'interno' => 'Interno', 'comunidad' => 'Comunidad'])
@php($previewableMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'])

<x-page-heading
    title="Documentos"
    eyebrow="Gestión documental"
>
    <x-slot name="actions">
        @can('create', App\Models\Documento::class)<a class="button primary" href="{{ route('documentos.create') }}">Nuevo documento</a>@endcan
    </x-slot>
</x-page-heading>

<div class="metrics" style="margin-bottom:18px">
    <x-metric-card
        label="Total documentos"
        :value="$totalDocumentos"
        variant="info"
    />
    <x-metric-card
        label="Activos"
        :value="$documentosActivos"
        variant="success"
    />
    <x-metric-card
        label="Archivados"
        :value="$documentosArchivados"
        variant="neutral"
    />
</div>

@if($errors->any())<x-notice variant="error" title="No fue posible completar la acción."><p>Revisa la información y vuelve a intentarlo.</p></x-notice>@endif

<section class="panel documents-panel">
    <div class="panel-header"><div><h2>Biblioteca documental</h2><p>Consulta el estado, acceso y versión disponible de cada Documento.</p></div></div>

    {{-- Vista móvil --}}
    <div class="documents-mobile-list">
        @forelse($documentos as $documento)
            @php($vigente = $documento->versionVigente())
            <article class="document-card">
                @if($vigente && in_array($vigente->mime_type, $previewableMimes))
                    <div class="document-preview-thumb" data-preview-url="{{ route('documentos.versions.preview', [$documento, $vigente]) }}" data-preview-mime="{{ $vigente->mime_type }}" data-preview-title="{{ $documento->titulo }}">
                        @if(Str::startsWith($vigente->mime_type, 'image/'))
                            <img src="{{ route('documentos.versions.preview', [$documento, $vigente]) }}" alt="{{ $documento->titulo }}" loading="lazy">
                        @else
                            <div class="document-preview-pdf-card">
                                <span class="document-preview-pdf-label">PDF</span>
                                <span class="document-preview-pdf-name">{{ Str::limit($documento->titulo, 30) }}</span>
                            </div>
                        @endif
                    </div>
                @endif
                <a href="{{ route('documentos.show',$documento) }}">
                    <span class="type-label">{{ $tipo[$documento->tipo->value] ?? $documento->tipo->value }}</span>
                    <strong>{{ $documento->titulo }}</strong>
                    <p>{{ $categoria[$documento->categoria->value] ?? $documento->categoria->value }} · {{ $acceso[$documento->nivel_acceso->value] ?? $documento->nivel_acceso->value }}</p>
                    <div>
                        <span class="status {{ $documento->estado->value }}"><i></i>{{ $documento->estado->value === 'activo' ? 'Activo' : 'Archivado' }}</span>
                        <small>{{ $vigente ? 'Versión '.$vigente->numero.' vigente' : 'Sin versión vigente' }}</small>
                    </div>
                </a>
            </article>
        @empty
            <x-empty-state title="No hay Documentos disponibles" description="No hay Documentos autorizados para mostrar en la Copropiedad activa.">
                @can('create', App\Models\Documento::class)<a class="button primary" href="{{ route('documentos.create') }}">Crear documento</a>@endcan
            </x-empty-state>
        @endforelse
    </div>

    {{-- Vista desktop --}}
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th class="document-preview-col">Preview</th>
                    <th>Documento</th>
                    <th>Tipo y categoría</th>
                    <th>Estado</th>
                    <th>Acceso</th>
                    <th>Propietario</th>
                    <th>Versión</th>
                </tr>
            </thead>
            <tbody>
                @forelse($documentos as $documento)
                    @php($vigente = $documento->versionVigente())
                    <tr>
                        <td class="document-preview-col">
                            @if($vigente && in_array($vigente->mime_type, $previewableMimes))
                                <div class="document-preview-cell" data-preview-url="{{ route('documentos.versions.preview', [$documento, $vigente]) }}" data-preview-mime="{{ $vigente->mime_type }}" data-preview-title="{{ $documento->titulo }}" role="button" tabindex="0" aria-label="Vista previa de {{ $documento->titulo }}">
                                    @if(Str::startsWith($vigente->mime_type, 'image/'))
                                        <img src="{{ route('documentos.versions.preview', [$documento, $vigente]) }}" alt="{{ $documento->titulo }}" loading="lazy">
                                    @else
                                        <div class="document-preview-pdf-card">
                                            <span class="document-preview-pdf-label">PDF</span>
                                            <span class="document-preview-pdf-name">{{ Str::limit($documento->titulo, 20) }}</span>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <span class="document-preview-unsupported">—</span>
                            @endif
                        </td>
                        <td>
                            <a class="subject-link" href="{{ route('documentos.show',$documento) }}">
                                <strong class="subject">{{ $documento->titulo }}</strong>
                                <small>Abrir detalle</small>
                            </a>
                        </td>
                        <td>
                            <span class="type-label">{{ $tipo[$documento->tipo->value] ?? $documento->tipo->value }}</span>
                            <small>{{ $categoria[$documento->categoria->value] ?? $documento->categoria->value }}</small>
                        </td>
                        <td><span class="status {{ $documento->estado->value }}"><i></i>{{ $documento->estado->value === 'activo' ? 'Activo' : 'Archivado' }}</span></td>
                        <td>{{ $acceso[$documento->nivel_acceso->value] ?? $documento->nivel_acceso->value }}</td>
                        <td>{{ $documento->propietarioDocumental->name ?? 'Sin propietario' }}</td>
                        <td>{{ $vigente ? 'Versión '.$vigente->numero : 'Sin versión' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <x-empty-state title="No hay Documentos disponibles" description="No hay Documentos autorizados para mostrar en la Copropiedad activa.">
                                @can('create', App\Models\Documento::class)<a class="button primary" href="{{ route('documentos.create') }}">Crear documento</a>@endcan
                            </x-empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="panel-footer">{{ $documentos->links() }}</div>
</section>

{{-- Modal de previsualización --}}
<dialog class="preview-dialog" id="previewDialog">
    <div class="preview-dialog-content">
        <div class="preview-dialog-header">
            <h2 id="previewTitle">Vista previa</h2>
            <button class="preview-dialog-close" id="previewClose" type="button" aria-label="Cerrar">✕</button>
        </div>
        <div class="preview-dialog-body" id="previewBody">
        </div>
        <div class="preview-dialog-footer">
            <a class="button subtle" id="previewDownload" href="#" download>Descargar</a>
            <button class="button ghost" id="previewCloseBtn" type="button">Cerrar</button>
        </div>
    </div>
</dialog>
@endsection
