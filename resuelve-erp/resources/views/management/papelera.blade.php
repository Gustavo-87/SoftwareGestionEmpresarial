@extends('layouts.app')
@section('titulo', $titulo)
@section('titulo_pagina', $titulo)
@section('contenido')
<x-page-heading :title="$titulo" eyebrow="Catálogos maestros">
    <x-slot name="actions"><a class="button subtle" href="{{ $volver }}">{{ $volverTexto }}</a></x-slot>
</x-page-heading>
<p>{{ $descripcion }}</p>
<section class="panel">
    <div class="panel-header"><h2>Registros desactivados</h2></div>
    <div class="table-wrap"><table>
        <thead><tr><th>{{ $columna }}</th><th>Desactivado</th><th></th></tr></thead>
        <tbody>
        @forelse($registros as $registro)
            <tr>
                <td>{{ $registro['resumen'] }}</td>
                <td>{{ $registro['fecha'] ?? '—' }}</td>
                <td class="actions">
                    <form method="POST" action="{{ $registro['restaurar'] }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="activo" value="1">
                        <button class="button primary">Restaurar</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="3"><x-empty-state title="La papelera está vacía" description="No hay registros desactivados en este contexto." /></td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
@endsection
