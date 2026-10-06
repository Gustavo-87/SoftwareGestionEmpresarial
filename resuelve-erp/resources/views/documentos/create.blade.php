@extends('layouts.app')
@section('titulo', 'Nuevo documento')
@section('titulo_pagina', 'Nuevo documento')
@section('contenido')
<section class="page-heading compact"><div><a class="back-link" href="{{ route('documentos.index') }}">← Volver a Documentos</a><span class="eyebrow">Gestión documental</span><h1>Crear Documento</h1><p>El Documento se creará en la Copropiedad activa.</p></div></section>
<section class="panel document-create-panel">
    <form method="POST" action="{{ route('documentos.store') }}" enctype="multipart/form-data">@csrf
        <div class="field"><label for="titulo">Título</label><input id="titulo" name="titulo" value="{{ old('titulo') }}" required>@error('titulo')<small class="field-error">{{ $message }}</small>@enderror</div>
        <div class="field"><label for="tipo">Tipo</label><select id="tipo" name="tipo" required><option value="documento_general">Documento general</option><option value="reglamento">Reglamento</option><option value="manual_convivencia">Manual de Convivencia</option><option value="acta">Acta</option></select></div>
        <div class="field"><label for="categoria">Categoría</label><select id="categoria" name="categoria" required><option value="normativo">Normativo</option><option value="administrativo">Administrativo</option><option value="gobierno_copropiedad">Gobierno de la Copropiedad</option><option value="contractual">Contractual</option><option value="financiero">Financiero</option><option value="comunicaciones">Comunicaciones</option><option value="otro">Otro</option></select>@error('categoria')<small class="field-error">{{ $message }}</small>@enderror</div>
        <div class="field"><label for="nivel_acceso">Acceso</label><select id="nivel_acceso" name="nivel_acceso" required><option value="interno">Interno</option><option value="administrativo">Administrativo</option><option value="comunidad">Comunidad</option></select>@error('nivel_acceso')<small class="field-error">{{ $message }}</small>@enderror</div>
        <div class="field"><label for="propietario_documental_user_id">Propietario documental</label><select id="propietario_documental_user_id" name="propietario_documental_user_id" required>@forelse($usuariosPropietario as $usuario)<option value="{{ $usuario->id }}" @selected((string) old('propietario_documental_user_id', auth()->id()) === (string) $usuario->id)>{{ $usuario->name }} · {{ $usuario->email }}</option>@empty<option value="{{ auth()->id() }}">{{ auth()->user()->name }} · {{ auth()->user()->email }}</option>@endforelse</select><small class="field-help">Usuarios con membresía activa en la Copropiedad activa.</small>@error('propietario_documental_user_id')<small class="field-error">{{ $message }}</small>@enderror</div>
        <div class="field"><label for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" rows="4">{{ old('descripcion') }}</textarea>@error('descripcion')<small class="field-error">{{ $message }}</small>@enderror</div>
        <div class="archivo-inicial">
            <span class="eyebrow">Archivo inicial</span>
            <div class="field"><label for="archivo">Archivo (opcional)</label><input id="archivo" type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png,.docx"><small class="field-help">Si lo adjuntas, se creará automáticamente la Versión 1 en borrador. El Documento puede registrarse sin versión.</small>@error('archivo')<small class="field-error">{{ $message }}</small>@enderror</div>
        </div>
        <button class="button primary">Crear Documento</button>
    </form>
</section>
@endsection
