@extends('layouts.app')
@section('titulo', 'Personas')
@section('titulo_pagina', 'Personas de la Organización')
@section('contenido')
<x-page-heading title="Personas" eyebrow="Residentes y unidades">
    <x-slot name="actions"><a class="button secondary" href="{{ route('management.unidades.index') }}">Volver a unidades</a></x-slot>
</x-page-heading>
<p>Personas de la Organización activa. Desactivar una persona no elimina sus vínculos: se conservan como historia.</p>
<section class="panel">
    <div class="panel-header">
        <form method="GET" action="{{ route('management.personas.index') }}" class="filters">
            <div class="search-box"><input type="search" name="q" value="{{ $q }}" placeholder="Nombre, identificación o correo" aria-label="Buscar personas"></div>
            <select name="estado" aria-label="Filtrar por estado">
                <option value="">Todos los estados</option>
                <option value="activa" @selected($estado === 'activa')>Activas</option>
                <option value="desactivada" @selected($estado === 'desactivada')>Desactivadas</option>
            </select>
            <button class="button subtle">Filtrar</button>
        </form>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>Nombre / Razón social</th><th>Tipo</th><th>Identificación</th><th>Correo</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @forelse($personas as $persona)
            <tr>
                <td><strong>{{ $persona->nombre_razon_social }}</strong></td>
                <td><span class="type-label">{{ ucfirst($persona->tipo_persona->value) }}</span></td>
                <td>{{ $persona->identificacion ?: '—' }}</td>
                <td>{{ $persona->email ?: '—' }}</td>
                <td><span class="status {{ $persona->estado === 'activa' ? 'activo' : 'archivado' }}"><i></i>{{ $persona->estado === 'activa' ? 'Activa' : 'Desactivada' }}</span></td>
                <td class="actions">
                    @if($persona->estado === 'activa')
                        <form method="POST" action="{{ route('management.personas.desactivar', $persona) }}">@csrf @method('PATCH')
                            <button class="button subtle danger" data-confirm="Desactivar persona" data-confirm-message="La persona dejará de admitir nuevos vínculos. No se elimina ningún registro y sus vínculos se conservan como historia.">Desactivar</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('management.personas.reactivar', $persona) }}">@csrf @method('PATCH')
                            <button class="button subtle">Reactivar</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><x-empty-state title="No hay personas registradas" description="Registra una persona o ajusta los filtros." /></td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="panel-footer"><span>{{ $personas->total() }} {{ $personas->total() === 1 ? 'persona' : 'personas' }}</span>{{ $personas->links() }}</div>
</section>
<section class="panel" style="margin-top:18px">
    @include('management.partials.persona-form')
</section>
@endsection
