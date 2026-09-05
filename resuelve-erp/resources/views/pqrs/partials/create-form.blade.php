@php
    $fieldLabels = [
        'asunto' => 'Asunto',
        'tipo_pqr_id' => 'Tipo de solicitud',
        'descripcion' => 'Descripción',
        'adjuntos' => 'Archivos adjuntos',
        'adjuntos.0' => 'Archivos adjuntos',
    ];
@endphp

<form method="POST" action="{{ route('pqrs.store') }}" class="form-panel pqr-create-form" enctype="multipart/form-data" data-pqr-create-form @if($errors->any()) data-has-errors @endif>
    @csrf

    <div class="form-intro pqr-create-intro">
        <span class="form-step" aria-hidden="true">01</span>
        <div>
            <h2>Información de la solicitud</h2>
            <p>Los campos con <span aria-hidden="true">*</span><span class="sr-only"> obligatorio</span> son necesarios para radicar.</p>
        </div>
    </div>

    @if($errors->any())
        <section class="notice error pqr-error-summary" id="pqr-error-summary" role="alert" tabindex="-1" aria-labelledby="pqr-error-summary-title">
            <span aria-hidden="true">!</span>
            <div>
                <h2 id="pqr-error-summary-title">Revisa la información ingresada</h2>
                <p>Corrige los campos indicados antes de volver a radicar.</p>
                <ul>
                    @foreach($errors->keys() as $field)
                        @php($fieldId = str_starts_with($field, 'adjuntos.') ? 'adjuntos' : str_replace(['.', '*'], ['-', ''], $field))
                        <li><a href="#{{ $fieldId }}">{{ str_starts_with($field, 'adjuntos.') ? 'Archivos adjuntos' : ($fieldLabels[$field] ?? 'Campo por corregir') }}</a></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <div class="form-grid pqr-create-grid">
        @php($asuntoError = $errors->has('asunto'))
        <div class="field span-2">
            <label for="asunto">Asunto <span aria-hidden="true">*</span><span class="sr-only"> obligatorio</span></label>
            <input id="asunto" name="asunto" type="text" maxlength="150" value="{{ old('asunto') }}" placeholder="Ej. Solicitud de revisión de factura" required aria-invalid="{{ $asuntoError ? 'true' : 'false' }}" aria-describedby="asunto-help{{ $asuntoError ? ' asunto-error' : '' }}" class="@error('asunto') invalid @enderror">
            <small id="asunto-help" class="field-help">Máximo 150 caracteres.</small>
            @error('asunto')<small id="asunto-error" class="field-error">{{ $message }}</small>@enderror
        </div>

        @php($tipoError = $errors->has('tipo_pqr_id'))
        <div class="field">
            <label for="tipo_pqr_id">Tipo de solicitud <span aria-hidden="true">*</span><span class="sr-only"> obligatorio</span></label>
            <select id="tipo_pqr_id" name="tipo_pqr_id" required aria-invalid="{{ $tipoError ? 'true' : 'false' }}" aria-describedby="tipo-pqr-help{{ $tipoError ? ' tipo-pqr-error' : '' }}" class="@error('tipo_pqr_id') invalid @enderror">
                <option value="">Selecciona una opción</option>
                @foreach($tipos as $tipo)<option value="{{ $tipo->id }}" @selected(old('tipo_pqr_id') == $tipo->id)>{{ $tipo->nombre }}</option>@endforeach
            </select>
            <small id="tipo-pqr-help" class="field-help">Selecciona la clasificación existente que mejor describe tu caso.</small>
            @error('tipo_pqr_id')<small id="tipo-pqr-error" class="field-error">{{ $message }}</small>@enderror
        </div>

        <div class="field pqr-automatic-deadline" role="note"><strong>Fechas calculadas por el sistema</strong><p>Al radicar, el sistema registra la fecha actual de Bogotá y calcula el plazo máximo de respuesta en días hábiles de Colombia.</p></div>

        @php($descripcionError = $errors->has('descripcion'))
        <div class="field span-2">
            <label for="descripcion">Descripción <span aria-hidden="true">*</span><span class="sr-only"> obligatorio</span></label>
            <textarea id="descripcion" name="descripcion" rows="7" placeholder="Describe la situación, incluyendo los datos relevantes para atenderla..." required aria-invalid="{{ $descripcionError ? 'true' : 'false' }}" aria-describedby="descripcion-help{{ $descripcionError ? ' descripcion-error' : '' }}" class="@error('descripcion') invalid @enderror">{{ old('descripcion') }}</textarea>
            <div class="field-hint"><span id="descripcion-help">Incluye fechas, lugares y personas relacionadas.</span><span id="charCount" aria-live="polite">0 caracteres</span></div>
            @error('descripcion')<small id="descripcion-error" class="field-error">{{ $message }}</small>@enderror
        </div>

        @php($adjuntosMessage = $errors->first('adjuntos') ?: $errors->first('adjuntos.*'))
        @php($adjuntosError = filled($adjuntosMessage))
        <div class="field span-2 pqr-attachments-field">
            <label for="adjuntos">Archivos adjuntos <span class="optional-label">Opcional</span></label>
            <p id="adjuntos-help" class="field-help">Puedes adjuntar hasta 8 archivos de máximo 10 MB cada uno. Se permiten imágenes, PDF, Word, Excel, texto y ZIP.</p>
            <label class="file-drop" for="adjuntos">
                <span class="file-icon" aria-hidden="true">＋</span>
                <strong>Seleccionar fotos o documentos</strong>
                <small>Los archivos se cargarán únicamente al radicar la solicitud.</small>
                <span class="button subtle" aria-hidden="true">Explorar archivos</span>
            </label>
            <input class="sr-only" id="adjuntos" name="adjuntos[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip" aria-invalid="{{ $adjuntosError ? 'true' : 'false' }}" aria-describedby="adjuntos-help fileList{{ $adjuntosError ? ' adjuntos-error' : '' }}">
            <div id="fileList" class="file-list" aria-live="polite" aria-label="Archivos seleccionados"></div>
            @if($adjuntosError)<small id="adjuntos-error" class="field-error">{{ $adjuntosMessage }}</small>@endif
        </div>
    </div>

    <div class="form-actions pqr-create-actions">
        <p>Al radicar, el sistema registrará la solicitud y te llevará a su expediente.</p>
        <div>
            <a href="{{ route('pqrs.index') }}" class="button ghost">Cancelar</a>
            <button type="submit" class="button primary" data-radicacion-submit><span data-submit-label>Radicar solicitud</span><span aria-hidden="true">→</span></button>
        </div>
    </div>
</form>

<dialog class="confirm-dialog pqr-submit-dialog" id="pqrSubmitDialog" aria-labelledby="pqrSubmitDialogTitle" aria-describedby="pqrSubmitDialogMessage">
    <div>
        <span class="confirm-icon" aria-hidden="true">!</span>
        <h2 id="pqrSubmitDialogTitle">Confirmar radicación</h2>
        <p id="pqrSubmitDialogMessage">¿Confirmas que deseas enviar esta PQRS con la información registrada?<br>Después de radicarla no podrás modificarla.</p>
        <div>
            <button class="button ghost" id="pqrSubmitCancel" type="button">Cancelar</button>
            <button class="button primary" id="pqrSubmitAccept" type="button">Sí, radicar PQRS</button>
        </div>
    </div>
</dialog>
