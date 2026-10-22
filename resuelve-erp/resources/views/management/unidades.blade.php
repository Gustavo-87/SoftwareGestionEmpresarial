@extends('layouts.app')
@section('titulo', 'Unidades privadas')
@section('titulo_pagina', 'Unidades privadas')
@section('contenido')
<x-page-heading title="Unidades privadas" eyebrow="Residentes y unidades">
    <x-slot name="actions"><a class="button primary" href="{{ route('management.unidades.create') }}">Nueva unidad</a></x-slot>
</x-page-heading>
<p>Unidades Privadas de la Copropiedad activa. Los vínculos de propietarios y residentes se administran por separado.</p>
<section class="panel">
    <div class="panel-header">
        <form method="GET" action="{{ route('management.unidades.index') }}" class="filters">
            <div class="search-box"><input type="search" name="q" value="{{ $q }}" placeholder="Código, nombre o torre" aria-label="Buscar unidades"></div>
            <select name="estado" aria-label="Filtrar por estado">
                <option value="">Todos los estados</option>
                <option value="activa" @selected($estado === 'activa')>Activas</option>
                <option value="desactivada" @selected($estado === 'desactivada')>Desactivadas</option>
            </select>
            <button class="button subtle">Filtrar</button>
        </form>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>Código</th><th>Nombre / Número</th><th>Torre / Bloque</th><th>Tipo</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @forelse($unidades as $unidad)
            <tr>
                <td><strong>{{ $unidad->codigo }}</strong></td>
                <td>{{ $unidad->numero_nombre ?: '—' }}</td>
                <td>{{ $unidad->torre_bloque ?: '—' }}</td>
                <td><span class="type-label">{{ $unidad->tipo ? ucfirst($unidad->tipo->value) : '—' }}</span></td>
                <td><span class="status {{ $unidad->estado === 'activa' ? 'activo' : 'archivado' }}"><i></i>{{ $unidad->estado === 'activa' ? 'Activa' : 'Desactivada' }}</span></td>
                <td class="actions">
                    <a class="button subtle" href="{{ route('management.unidades.show', $unidad) }}">Vínculos</a>
                    <a class="button subtle" href="{{ route('management.unidades.edit', $unidad) }}">Editar</a>
                    @if($unidad->estado === 'activa')
                        <form method="POST" action="{{ route('management.unidades.desactivar', $unidad) }}">@csrf @method('PATCH')
                            <button class="button subtle danger" data-confirm="Desactivar unidad" data-confirm-message="La unidad dejará de admitir nuevas vinculaciones. No se elimina ningún registro y sus vínculos se conservan sin cambios.">Desactivar</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('management.unidades.reactivar', $unidad) }}">@csrf @method('PATCH')
                            <button class="button subtle">Reactivar</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><x-empty-state title="No hay unidades privadas" description="Crea la primera unidad de la Copropiedad activa o ajusta los filtros." /></td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="panel-footer"><span>{{ $unidades->total() }} {{ $unidades->total() === 1 ? 'unidad' : 'unidades' }}</span>{{ $unidades->links() }}</div>
</section>
@endsection
