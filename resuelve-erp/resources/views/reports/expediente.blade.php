<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
@page{margin:25px 28px 30px}*{box-sizing:border-box}body{font-family:DejaVu Sans,sans-serif;color:#24332e;font-size:8px;margin:0}.header{background:#1e3a5f;color:#fff;padding:18px 20px;border-radius:8px}.header h1{margin:0 0 4px;font-size:19px}.header h2{margin:0;font-size:11px;font-weight:normal}.meta{margin-top:7px;color:#d6e4df}.company{width:100%;margin:14px 0;border-collapse:collapse}.company td{padding:3px 8px 3px 0}.company b{color:#61716b}.section-title{font-size:11px;margin:13px 0 6px;color:#1e3a5f;border-bottom:1px solid #dce4e0;padding-bottom:5px}table.data{width:100%;border-collapse:collapse}table.data th{background:#1e3a5f;color:#fff;text-align:left;padding:6px;font-size:7px}table.data td{padding:5px 6px;border-bottom:1px solid #e3e9e6;vertical-align:top}table.data tr:nth-child(even) td{background:#f7f9f8}.status{font-weight:bold}.footer{position:fixed;bottom:-18px;left:0;right:0;color:#718079;font-size:7px;border-top:1px solid #dce4e0;padding-top:5px}.desc{color:#6b7772;font-size:7px;margin-top:2px}.empty{padding:12px;background:#f7f9f8;color:#6b7772}.restricted{padding:8px 10px;background:#f1f6f3;color:#61716b;border-left:3px solid #94a3b8}
</style></head><body>
<header class="header"><h1>{{ $settings->nombre_conjunto }}</h1><h2>Expediente integrado de PQRS</h2><div class="meta">Copropiedad: {{ $copropiedad->nombre }} · PQRS #{{ $pqr->id }} · Generado el {{ $generatedAt->format('d/m/Y \a \l\a\s H:i') }}</div></header>
<table class="company"><tr><td><b>NIT</b><br>{{ $settings->nit ?: 'No registrado' }}</td><td><b>Representante legal</b><br>{{ $settings->representante_legal ?: 'No registrado' }}</td><td><b>Dirección</b><br>{{ $settings->direccion ?: 'No registrada' }} {{ $settings->ciudad }}</td><td><b>Contacto</b><br>{{ $settings->email ?: $settings->telefono ?: 'No registrado' }}</td></tr></table>

<h3 class="section-title">1. PQRS de origen</h3>
<table class="data"><thead><tr><th>Asunto</th><th>Tipo</th><th>Estado</th><th>Solicitante</th><th>Responsable</th></tr></thead><tbody>
<tr><td><b>{{ $pqr->asunto }}</b><div class="desc">{{ Str::limit($pqr->descripcion ?? '', 220) }}</div></td><td>{{ $pqr->tipoPqr?->nombre }}</td><td class="status">{{ $pqr->estado }}</td><td>{{ $pqr->user?->name }}</td><td>{{ $pqr->assignee?->name ?? 'Sin asignar' }}</td></tr>
</tbody></table>

<h3 class="section-title">2. Mantenimientos vinculados</h3>
<table class="data"><thead><tr><th>Mantenimiento</th><th>Estado</th><th>Fecha programada</th><th>Finalización</th><th>Resultado público</th><th>Resultado interno</th></tr></thead><tbody>
@forelse($mantenimientos as $item)
<tr>
    <td><b>{{ $item['datos']['titulo'] ?? 'Mantenimiento vinculado' }}</b>@if($item['puede_gestionar'] && ($item['datos']['responsable'] ?? null) !== null)<div class="desc">Responsable: {{ $item['datos']['responsable'] }}</div>@endif</td>
    <td class="status">{{ $item['datos']['estado_label'] }}</td>
    <td>{{ $item['datos']['fecha_programada']?->format('d/m/Y') ?? 'Sin programar' }}</td>
    <td>{{ $item['datos']['finalizado_at']?->format('d/m/Y H:i') ?? '—' }}</td>
    <td>{{ $item['datos']['resultado_publico'] ?? '—' }}</td>
    <td>{{ $item['puede_gestionar'] ? ($item['datos']['resultado_interno'] ?? '—') : 'Información interna reservada al personal autorizado.' }}</td>
</tr>
@empty<tr><td colspan="6"><div class="empty">Sin mantenimientos vinculados a esta PQRS.</div></td></tr>@endforelse
</tbody></table>

<h3 class="section-title">3. Evidencia documental</h3>
@forelse($mantenimientos as $item)
    <table class="data"><thead><tr><th>Mantenimiento</th><th>Documento</th><th>Versión</th><th>Estado de versión</th><th>Vinculada</th></tr></thead><tbody>
    @forelse($item['evidencias'] as $evidencia)
        <tr><td>{{ $item['datos']['titulo'] ?? 'Mantenimiento vinculado' }}</td><td>{{ $evidencia['documento'] }}</td><td>{{ $evidencia['version'] ?? '—' }}</td><td class="status">{{ $evidencia['version_estado'] ?? '—' }}</td><td>{{ $evidencia['vinculada']?->format('d/m/Y H:i') }}</td></tr>
    @empty
        <tr><td colspan="5"><div class="empty">Sin evidencia documental visible para este usuario.</div></td></tr>
    @endforelse
    @if($item['evidencias_restringidas'])
        <tr><td colspan="5"><div class="restricted">Evidencia documental disponible solo para personal autorizado.</div></td></tr>
    @endif
    </tbody></table>
@empty
    <div class="empty">Sin mantenimientos vinculados: no hay evidencia documental asociada.</div>
@endforelse

<h3 class="section-title">4. Respuestas oficiales</h3>
<table class="data"><thead><tr><th>Fecha</th><th>Respuesta</th></tr></thead><tbody>
@forelse($respuestas as $respuesta)
<tr><td>{{ $respuesta->sent_at?->format('d/m/Y H:i') ?? $respuesta->created_at->format('d/m/Y H:i') }}</td><td>{{ $respuesta->body }}</td></tr>
@empty<tr><td colspan="2"><div class="empty">Sin respuestas oficiales registradas.</div></td></tr>@endforelse
</tbody></table>

<h3 class="section-title">5. Actividad del expediente</h3>
<table class="data"><thead><tr><th>Fecha</th><th>Acción</th><th>Detalle</th></tr></thead><tbody>
@forelse($actividades as $actividad)
<tr><td>{{ $actividad->created_at->format('d/m/Y H:i') }}</td><td class="status">{{ $actividad->action }}</td><td>{{ $actividad->description }}</td></tr>
@empty<tr><td colspan="3"><div class="empty">Sin actividad registrada.</div></td></tr>@endforelse
</tbody></table>

<div class="footer">{{ $settings->nombre_conjunto }} · Expediente integrado de PQRS #{{ $pqr->id }} · {{ $generatedAt->format('d/m/Y H:i') }}</div></body></html>
