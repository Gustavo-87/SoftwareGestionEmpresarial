<details class="user-create-panel">
    <summary>Registrar una persona nueva</summary>
    <form method="POST" action="{{ route('management.personas.store') }}">@csrf
        <div class="field">
            <label for="tipo_persona">Tipo de persona</label>
            <select id="tipo_persona" name="tipo_persona" required>
                <option value="natural" @selected(old('tipo_persona') === 'natural')>Natural</option>
                <option value="juridica" @selected(old('tipo_persona') === 'juridica')>Jurídica</option>
            </select>
            @error('tipo_persona')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="field">
            <label for="nombre_razon_social">Nombre / Razón social</label>
            <input id="nombre_razon_social" name="nombre_razon_social" value="{{ old('nombre_razon_social') }}" required maxlength="255">
            @error('nombre_razon_social')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="field">
            <label for="identificacion">Identificación (opcional)</label>
            <input id="identificacion" name="identificacion" value="{{ old('identificacion') }}" maxlength="50">
            <small class="field-help">Única dentro de la Organización.</small>
            @error('identificacion')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="field">
            <label for="email">Correo (opcional)</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" maxlength="255">
            @error('email')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="field">
            <label for="telefono">Teléfono (opcional)</label>
            <input id="telefono" name="telefono" value="{{ old('telefono') }}" maxlength="50">
            @error('telefono')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        <div class="user-create-actions"><button class="button primary">Registrar persona</button></div>
    </form>
</details>
