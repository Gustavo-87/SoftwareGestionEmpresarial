@php
    // Etiquetas de presentación; los valores enviados conservan el identificador original.
    $etiquetasPermisos = [
        'auditoria.ver' => 'Consultar el historial de acciones',
        'configuracion.gestionar' => 'Administrar la configuración general',
        'documentos.aprobar' => 'Aprobar o rechazar versiones de documentos',
        'documentos.archivar' => 'Archivar documentos',
        'documentos.consultar' => 'Consultar documentos',
        'documentos.gestionar' => 'Administrar documentos y sus versiones',
        'gestion.carga_ver' => 'Ver la carga de trabajo del equipo',
        'gestion.herramientas_gestionar' => 'Administrar plantillas, etiquetas y reglas',
        'informes.exportar' => 'Descargar informes',
        'mantenimiento.crear' => 'Crear solicitudes de mantenimiento',
        'mantenimiento.gestionar' => 'Gestionar solicitudes de mantenimiento',
        'mantenimiento.ver_propias' => 'Ver mis solicitudes de mantenimiento',
        'mantenimiento.ver_todas' => 'Ver todas las solicitudes de mantenimiento',
        'notificaciones.consultar' => 'Consultar notificaciones',
        'pqrs.crear' => 'Registrar PQRS',
        'pqrs.eliminar' => 'Eliminar PQRS',
        'pqrs.gestionar' => 'Gestionar PQRS',
        'pqrs.gestionar_asignadas' => 'Gestionar PQRS asignadas a mí o sin responsable',
        'pqrs.listar' => 'Consultar el listado de PQRS',
        'pqrs.ver_borradores' => 'Ver borradores de otros usuarios',
        'pqrs.ver_propias' => 'Ver mis PQRS',
        'pqrs.ver_todas' => 'Ver todas las PQRS',
        'residentes.gestionar' => 'Consultar residentes y actualizar su unidad',
        'usuarios.gestionar' => 'Administrar usuarios y sus roles',
        'roles.gestionar' => 'Administrar roles y permisos',
    ];
@endphp
<div class="role-permissions-grid">
    @foreach($permisosPorModulo as $modulo => $permisos)
        <fieldset class="role-permission-card">
            <legend>{{ ['pqrs' => 'PQRS', 'auditoria' => 'Auditoría', 'configuracion' => 'Configuración', 'gestion' => 'Gestión'] [$modulo] ?? ucfirst($modulo) }}</legend>
            @foreach($permisos as $permiso)
                <label class="role-permission-option">
                    <span>{{ $etiquetasPermisos[$permiso->name] ?? Str::ucfirst(str_replace(['.', '_'], ' ', $permiso->name)) }}</span>
                    <input type="checkbox" name="permisos[]" value="{{ $permiso->name }}" @checked(in_array($permiso->name, old('permisos', $seleccionados ?? [])))>
                </label>
            @endforeach
        </fieldset>
    @endforeach
</div>
@error('permisos')<p class="field-error" role="alert">{{ $message }}</p>@enderror
