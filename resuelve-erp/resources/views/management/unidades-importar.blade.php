@extends('layouts.app')
@section('titulo', 'Importar unidades privadas')
@section('titulo_pagina', 'Importar unidades privadas')
@section('contenido')
<x-page-heading title="Importar unidades privadas" eyebrow="Residentes y unidades">
    <x-slot name="actions"><a class="button subtle" href="{{ route('management.unidades.index') }}">Volver a unidades</a></x-slot>
</x-page-heading>
<p>Importe el catálogo de Unidades Privadas de la Copropiedad activa desde un archivo XLSX (máximo 2 MB y {{ \App\Application\Residencia\ImportarUnidadesPrivadas::FILAS_MAXIMAS }} filas de datos, sin contar el encabezado). Solo se crean unidades nuevas: una fila inválida no impide procesar las demás y los códigos duplicados se omiten y se reportan.</p>

@if($resultado)
    @php
        $variante = $resultado['estado'] === 'error' ? 'error' : 'success';
        $titulo = [
            'exito' => 'Importación completada',
            'parcial' => 'Importación parcial: revisa las filas no procesadas',
            'error' => 'Importación sin altas',
        ][$resultado['estado']];
    @endphp
    <x-notice :variant="$variante" :title="$titulo">
        Archivo «{{ $resultado['archivo'] }}»: {{ $resultado['leidas'] }} filas leídas, {{ $resultado['creadas'] }} creadas, {{ $resultado['duplicadas'] }} duplicadas omitidas y {{ $resultado['invalidas'] }} inválidas.
    </x-notice>
@endif

@if($errors->any())
    <x-notice variant="error" title="No fue posible importar el archivo">
        {{ $errors->first('archivo') }}
    </x-notice>
@endif

<section class="panel">
    <div class="panel-header"><h2>Archivo de importación</h2></div>
    <form method="POST" action="{{ route('management.unidades.importar.store') }}" enctype="multipart/form-data" class="filters">
        @csrf
        <div class="field">
            <label for="archivo">Archivo XLSX
                <input type="file" name="archivo" id="archivo" accept=".xlsx" required>
            </label>
        </div>
        <button class="button primary">Importar unidades</button>
        @if($tieneReporte)
            <a class="button subtle" href="{{ route('management.unidades.importar.reporte') }}">Descargar reporte de errores (XLSX)</a>
        @endif
    </form>
    <p>Columnas esperadas en la primera fila: <b>Código</b> (obligatoria), <b>Número / Nombre</b>, <b>Torre / Bloque</b> y <b>Tipo</b> (opcionales; tipos válidos: apartamento, casa, local, oficina, deposito, parqueadero). Las demás columnas se ignoran; la Organización y la Copropiedad se asignan del contexto activo, nunca del archivo.</p>
</section>

@if($resultado && count($resultado['errores']) > 0)
    <section class="panel">
        <div class="panel-header"><h2>Filas no procesadas</h2></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Fila</th><th>Campo</th><th>Motivo</th></tr></thead>
            <tbody>
            @foreach($resultado['errores'] as $error)
                <tr><td>{{ $error['fila'] }}</td><td>{{ $error['campo'] ?? '—' }}</td><td>{{ $error['motivo'] }}</td></tr>
            @endforeach
            </tbody>
        </table></div>
    </section>
@endif
@endsection
