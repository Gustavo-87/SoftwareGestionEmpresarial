@props(['field'])

@error($field)
    <small id="{{ $field }}-error" class="field-error">{{ $message }}</small>
@enderror
