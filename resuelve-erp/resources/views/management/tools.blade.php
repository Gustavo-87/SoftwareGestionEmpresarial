@extends('layouts.app') @section('titulo','Herramientas') @section('titulo_pagina','Herramientas de gestión')
@section('contenido')
    <section class="page-heading">
        <div>
            <span class="eyebrow">Gestión</span>
            <h1>Herramientas del equipo</h1>
            <p>Plantillas, etiquetas y reportes para la gestión de la Copropiedad activa.</p>
        </div>
    </section>
    <div class="tools-grid">
        @if($puedeExportarInformes)
            <section class="tool-card" aria-labelledby="reportes-indicadores-title">
                <h2 id="reportes-indicadores-title">Reportes e Indicadores</h2>
                <p class="section-help">Consulta los indicadores de la Copropiedad activa y descarga los informes autorizados.</p>
                <div class="tool-card-actions">
                    <a class="button primary" href="{{ route('reports.index') }}">Abrir centro de reportes</a>
                </div>
            </section>
        @endif
        <section class="tool-card" aria-labelledby="plantillas-title">
            <h2 id="plantillas-title">Plantillas de respuesta</h2>
            <p class="section-help">Respuestas listas para acelerar la atención de las PQRS.</p>
            <form method="POST" action="{{ route('management.templates.store') }}">
                @csrf
                <div class="tool-fields-row">
                    <input name="name" placeholder="Nombre" required>
                    <input name="subject" placeholder="Asunto opcional">
                </div>
                <textarea name="body" rows="5" placeholder="Contenido de la respuesta" required></textarea>
                <button class="button primary">Crear plantilla</button>
            </form>
            <div class="tool-list">
                @foreach($templates as $item)
                    <span><b>{{ $item->name }}</b>{{ Str::limit($item->body, 70) }}</span>
                @endforeach
            </div>
        </section>
<section class="tool-card tag-catalog" aria-labelledby="tag-catalog-title"><h2 id="tag-catalog-title">Catálogo de etiquetas</h2><p class="section-help">Administra las etiquetas de esta Copropiedad. El nombre y color actual se muestran en todas las PQRS asociadas.</p>
<form method="POST" action="{{ route('management.tags.store') }}" class="tag-catalog-create">@csrf<div class="field"><label for="tag-name">Nombre</label><input id="tag-name" name="name" value="{{ old('editing_tag') ? '' : old('name') }}" maxlength="60" required aria-describedby="tag-name-help"><small id="tag-name-help">Máximo 60 caracteres; el nombre debe ser único incluso si otra etiqueta está desactivada.</small>@if(!old('editing_tag'))@error('name')<span id="tag-name-error" class="field-error" role="alert" tabindex="-1" data-first-error>{{ $message }}</span>@enderror @endif</div><div class="field"><label for="tag-color">Color</label><div class="tag-color-input"><input id="tag-color" type="color" name="color" value="{{ old('editing_tag') ? '#1F6B57' : old('color','#1F6B57') }}"><code>{{ old('editing_tag') ? '#1F6B57' : old('color','#1F6B57') }}</code></div>@if(!old('editing_tag'))@error('color')<span class="field-error" role="alert" tabindex="-1" data-first-error>{{ $message }}</span>@enderror @endif</div><button class="button primary" data-submit-label="Creando…">Crear etiqueta</button></form>
@if($tags->isEmpty())<div class="tag-catalog-empty"><strong>No hay etiquetas en esta Copropiedad</strong><p>Crea la primera para organizar los expedientes.</p></div>@else<div class="tag-catalog-list">@foreach($tags as $tag)<article class="tag-catalog-item {{ $tag->activo ? '' : 'is-inactive' }}"><header><span class="tag-catalog-swatch" style="--tag:{{ $tag->color }}" aria-hidden="true"></span><div><strong>{{ $tag->name }}</strong><code>{{ $tag->color }}</code></div><span class="status-pill">{{ $tag->activo ? 'Activa' : 'Desactivada' }}</span></header><details @if(old('editing_tag') == $tag->id) open @endif><summary class="button subtle">Editar</summary><form method="POST" action="{{ route('management.tags.update',$tag) }}">@csrf @method('PATCH')<div class="field"><label for="tag-name-{{ $tag->id }}">Nombre</label><input id="tag-name-{{ $tag->id }}" name="name" value="{{ old('editing_tag') == $tag->id ? old('name') : $tag->name }}" maxlength="60" required>@if(old('editing_tag') == $tag->id)@error('name')<span class="field-error" role="alert" tabindex="-1" data-first-error>{{ $message }}</span>@enderror @endif</div><div class="field"><label for="tag-color-{{ $tag->id }}">Color</label><div class="tag-color-input"><input id="tag-color-{{ $tag->id }}" type="color" name="color" value="{{ old('editing_tag') == $tag->id ? old('color') : $tag->color }}"><code>{{ old('editing_tag') == $tag->id ? old('color') : $tag->color }}</code></div>@if(old('editing_tag') == $tag->id)@error('color')<span class="field-error" role="alert" tabindex="-1" data-first-error>{{ $message }}</span>@enderror @endif</div><p class="section-help">Los cambios de nombre o color se reflejan también en las PQRS donde está asignada.</p><button class="button primary" data-submit-label="Guardando…">Guardar cambios</button></form></details><form method="POST" action="{{ route('management.tags.status',$tag) }}" data-confirm="{{ $tag->activo ? 'Desactivar etiqueta' : '' }}" data-confirm-message="Desactivarla impide nuevas asignaciones, pero conserva las asociaciones existentes.">@csrf @method('PATCH')<input type="hidden" name="activo" value="{{ $tag->activo ? 0 : 1 }}"><button class="button {{ $tag->activo ? 'danger-outline' : 'subtle' }}" data-submit-label="Guardando…">{{ $tag->activo ? 'Desactivar' : 'Reactivar' }}</button></form></article>@endforeach</div>@endif</section>
</div>@endsection
