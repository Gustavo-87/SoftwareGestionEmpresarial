@extends('layouts.admin')
@section('titulo', 'Tipos de PQRS')
@section('eyebrow', 'Configuración PQRS')
@section('titulo_pagina', 'Tipos de PQRS')

@section('contenido')
<x-page-heading title="Tipos de PQRS" eyebrow="Configuración PQRS">
    <x-slot name="actions"><a class="button primary" href="{{ route('admin.tipos-pqr.create') }}">✚ Nuevo tipo</a></x-slot>
</x-page-heading>

<p>Catálogo global de Tipos de PQRS (Petición, Queja, Reclamo, Sugerencia, …),
compartido por todas las Organizaciones de la plataforma.</p>

@error('tipo')
    <p class="field-error" style="margin-bottom:12px" role="alert">{{ $message }}</p>
@enderror

@if($tipos->isEmpty())
    <x-empty-state title="No hay tipos de PQRS" description="Crea el primer tipo para poder radicar PQRS.">
        <a href="{{ route('admin.tipos-pqr.create') }}" class="button primary">✚ Nuevo tipo</a>
    </x-empty-state>
@else
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>PQRS asociadas</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tipos as $tipo)
                    <tr>
                        <td>{{ $tipo->nombre }}</td>
                        <td>{{ $tipo->descripcion ?: '—' }}</td>
                        <td>{{ $tipo->pqrs_count }}</td>
                        <td class="actions">
                            <a href="{{ route('admin.tipos-pqr.edit', $tipo) }}" class="icon-button" title="Editar">✎</a>
                            <form method="POST" action="{{ route('admin.tipos-pqr.destroy', $tipo) }}" style="display:inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="icon-button danger" title="Eliminar"
                                        onclick="return confirm('¿Eliminar definitivamente el tipo «{{ $tipo->nombre }}»?\n\nNo puede eliminarse si está siendo utilizado por alguna PQRS.')">✕</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
