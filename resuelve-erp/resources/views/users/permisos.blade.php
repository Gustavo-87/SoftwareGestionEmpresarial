@extends('layouts.app')

@section('titulo','Matriz de permisos')
@section('titulo_pagina','Matriz de permisos')

@section('contenido')

<a class="back-link" href="{{ route('users.edit', $user) }}">← Volver a {{ $user->name }}</a>

<section class="page-heading compact">
    <div>
        <span class="eyebrow">Ver como usuario</span>
        <h1>Matriz de permisos de {{ $user->name }}</h1>
        <p>Origen de cada permiso efectivo en la Copropiedad activa. Solo lectura.</p>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Resolución de permisos</h2>
            <p>
                @if($matriz['rolActivo'] !== null)
                    Rol activo seleccionado: <strong>{{ $matriz['rolActivo'] }}</strong>.
                    Los permisos efectivos son los del rol activo más los permisos directos.
                @else
                    Sin rol activo seleccionado: se conserva la unión de roles
                    (<strong>{{ $matriz['rolesAsignados'] ? implode(', ', $matriz['rolesAsignados']) : 'sin roles asignados' }}</strong>).
                @endif
            </p>
        </div>
    </div>
</section>

@if(! $matriz['membresiaVigente'])
    <section class="panel"><x-empty-state title="Sin Membresía vigente" description="El usuario no tiene Membresía vigente en la Copropiedad activa, por lo que ningún permiso es efectivo en este contexto." /></section>
@else
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Permiso</th>
                    <th>Descripción</th>
                    <th>Origen</th>
                    <th>Efectivo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($matriz['modulos'] as $modulo => $permisos)
                    <tr class="matriz-modulo"><td colspan="4"><strong>{{ $modulo }}</strong></td></tr>
                    @foreach($permisos as $fila)
                        <tr>
                            <td>{{ $fila['nombre'] }} <small>{{ $fila['clave'] }}</small></td>
                            <td>{{ $fila['descripcion'] ?: '—' }}</td>
                            <td>
                                @if($fila['estado'] !== 'activo')
                                    Permiso desactivado
                                @elseif($fila['origenes'] !== [])
                                    {{ implode(' · ', $fila['origenes']) }}
                                @else
                                    Sin origen
                                @endif
                            </td>
                            <td>{{ $fila['efectivo'] ? 'Sí' : 'No' }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@endsection
