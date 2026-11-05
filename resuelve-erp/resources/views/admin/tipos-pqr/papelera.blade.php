@extends('layouts.admin')
@section('titulo', 'Papelera de tipos de PQRS')
@section('eyebrow', 'Configuración PQRS')
@section('titulo_pagina', 'Papelera de tipos de PQRS')

@section('contenido')
<x-page-heading title="Papelera de tipos de PQRS" eyebrow="Configuración PQRS">
    <x-slot name="actions"><a class="button subtle" href="{{ route('admin.tipos-pqr.index') }}">Volver al catálogo</a></x-slot>
</x-page-heading>

<p>Tipos de PQRS desactivados. Restaurar vuelve a ponerlos en el catálogo
normal sin crear registros nuevos; ningún registro se elimina físicamente.</p>

@if($tipos->isEmpty())
    <x-empty-state title="La papelera está vacía" description="No hay tipos desactivados.">
        <a href="{{ route('admin.tipos-pqr.index') }}" class="button primary">Volver al catálogo</a>
    </x-empty-state>
@else
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>PQRS asociadas</th>
                    <th>Desactivado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tipos as $tipo)
                    <tr>
                        <td>{{ $tipo->nombre }}</td>
                        <td>{{ $tipo->descripcion ?: '—' }}</td>
                        <td>{{ $tipo->pqrs_count }}</td>
                        <td>{{ $tipo->desactivada_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="actions">
                            <form method="POST" action="{{ route('admin.tipos-pqr.reactivar', $tipo) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="button primary">Restaurar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
