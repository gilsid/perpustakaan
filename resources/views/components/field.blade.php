@props([
    'name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null,
    'rows' => 4, 'options' => [], 'id' => null, 'bag' => 'default',
])
@php
    $id ??= 'f-'.$name;
    $error = $errors->getBag($bag)->first($name);
    $describedBy = trim(($hint ? 'hint-'.$name : '').' '.($error ? 'err-'.$name : ''));
    $current = $type === 'password' ? null : old($name, $value);
@endphp
<div @class(['field', 'has-error' => $error])>
    <label for="{{ $id }}">{{ $label }}@if ($required)<span class="req" title="wajib diisi"> *</span>@endif</label>
    @if ($hint)<p class="hint" id="hint-{{ $name }}">{{ $hint }}</p>@endif

    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @required($required) @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif @if ($error) aria-invalid="true" @endif {{ $attributes }}>{{ $current }}</textarea>
    @elseif ($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" @required($required) @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif @if ($error) aria-invalid="true" @endif {{ $attributes }}>
            @foreach ($options as $optValue => $optLabel)
                <option value="{{ $optValue }}" @selected((string) $optValue === (string) $current)>{{ $optLabel }}</option>
            @endforeach
        </select>
    @else
        <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}" @if ($type !== 'password') value="{{ $current }}" @endif @required($required) @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif @if ($error) aria-invalid="true" @endif {{ $attributes }}>
    @endif

    @if ($error)<p class="error" id="err-{{ $name }}"><x-icon name="alert" /><span>{{ $error }}</span></p>@endif
</div>
