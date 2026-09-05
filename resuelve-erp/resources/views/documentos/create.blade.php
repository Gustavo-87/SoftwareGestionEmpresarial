@extends('layouts.app')
@section('titulo', 'Nuevo documento')
@section('titulo_pagina', 'Nuevo documento')
@section('contenido')
<section class="page-heading compact"><div><a class="back-link" href="{{ route('documentos.index') }}">← Volver a Documentos</a><span class="eyebrow">Gestión documental</span><h1>Crear Documento</h1><p>El Documento se creará en la Copropiedad activa.</p></div></section>
<section class="panel document-create-panel"><form method="POST" action="{{ route('documentos.store') }}">@csrf
<div class="field"><label>Título<input name="titulo" value="{{ old('titulo') }}" required></label>@error('titulo')<small class="field-error">{{ $message }}</small>@enderror</div>
<div class="field"><label>Tipo<select name="tipo" required><option value="documento_general">Documento general</option><option value="reglamento">Reglamento</option><option value="manual_convivencia">Manual de Convivencia</option><option value="acta">Acta</option></select></label></div>
<div class="field"><label>Categoría<select name="categoria" required><option value="normativo">Normativo</option><option value="administrativo">Administrativo</option><option value="gobierno_copropiedad">Gobierno de la Copropiedad</option><option value="contractual">Contractual</option><option value="financiero">Financiero</option><option value="comunicaciones">Comunicaciones</option><option value="otro">Otro</option></select></label></div>
<div class="field"><label>Acceso<select name="nivel_acceso" required><option value="interno">Interno</option><option value="administrativo">Administrativo</option><option value="comunidad">Comunidad</option></select></label></div>
<div class="field"><label>Propietario documental (ID de Usuario)<input type="number" name="propietario_documental_user_id" value="{{ old('propietario_documental_user_id', auth()->id()) }}" required></label><small class="field-help">Se conserva el control actual para no introducir una consulta de Usuarios nueva.</small>@error('propietario_documental_user_id')<small class="field-error">{{ $message }}</small>@enderror</div>
<div class="field"><label>Descripción<textarea name="descripcion">{{ old('descripcion') }}</textarea></label></div><button class="button primary">Crear Documento</button></form></section>
@endsection
