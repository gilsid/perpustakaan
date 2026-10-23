@php
    $icons = ['success' => 'check', 'error' => 'alert', 'warning' => 'alert', 'info' => 'info'];
@endphp
@if (collect(array_keys($icons))->contains(fn ($t) => session()->has($t)))
    <div class="flashes container">
        @foreach ($icons as $type => $icon)
            @if (session()->has($type))
                <div class="alert alert--{{ $type }}" role="{{ $type === 'error' ? 'alert' : 'status' }}">
                    <x-icon :name="$icon" /><p>{{ session($type) }}</p>
                    <button type="button" class="alert__close" data-dismiss aria-label="Tutup pesan"><x-icon name="x" /></button>
                </div>
            @endif
        @endforeach
    </div>
@endif
