@extends('layouts.app')
@section('titulo', 'Notificaciones')
@section('titulo_pagina', 'Notificaciones')
@section('contenido')
@php($eventos = ['pqr_creada' => ['Nueva PQRS', '＋'], 'pqr_asignada' => ['PQRS asignada', '↗'], 'pqr_estado_actualizado' => ['Estado actualizado', '✓'], 'pqr_respuesta_enviada' => ['Respuesta enviada', '✉'], 'pqr_recordatorio_vencimiento' => ['Recordatorio de vencimiento', '!']])
    <x-page-heading
        title="Notificaciones"
        eyebrow="Centro de avisos"
    >
        <x-slot name="actions">
            @if($unreadNotificationsCount)<form method="POST" action="{{ route('notifications.read-all') }}" data-confirm="Marcar todas como leídas" data-confirm-message="Solo se marcarán como leídas las notificaciones de la Copropiedad activa.">@csrf @method('PATCH')<button class="button subtle">Marcar todas como leídas ({{ $unreadNotificationsCount }})</button></form>@endif
        </x-slot>
    </x-page-heading>
    <section class="panel notification-list">@forelse($notifications as $notification)@php($evento = $eventos[$notification->data['event'] ?? ''] ?? ['Notificación', '•'])<form method="POST" action="{{ route('notifications.read', $notification) }}">@csrf @method('PATCH')<button type="submit" class="notification-row {{ $notification->read_at ? 'read' : 'unread' }}" style="width:100%;border:0;text-align:left;cursor:pointer"><span class="notification-event-icon" aria-hidden="true">{{ $evento[1] }}</span><span class="notification-dot" aria-hidden="true"></span><div><span class="notification-type">{{ $evento[0] }}</span><strong>{{ $notification->data['title'] ?? 'Notificación' }}</strong><p>{{ $notification->data['message'] ?? '' }}</p><small>{{ $notification->created_at->translatedFormat('d M Y, H:i') }} · {{ $notification->read_at ? 'Leída' : 'Sin leer' }}</small></div><span class="notification-arrow" aria-hidden="true">→</span></button></form>@empty<x-empty-state title="Estás al día" description="No tienes notificaciones para la Copropiedad activa." />@endforelse<div class="panel-footer">{{ $notifications->links() }}</div></section>
@endsection
