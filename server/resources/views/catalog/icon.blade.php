@php
    $paths = [
        'diagonal' => 'M5 19 19 5M5 5h14v14',
        'right' => 'M4 12h16m-6-6 6 6-6 6',
        'left' => 'M20 12H4m6-6-6 6 6 6',
        'search' => 'm20 20-5-5M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0',
        'close' => 'M6 6l12 12M18 6 6 18',
        'check' => 'm5 12 4 4L19 6',
        'phone' => 'm5 3 4 1 1 5-3 2a15 15 0 0 0 6 6l2-3 5 1 1 4c-1 3-4 3-7 2A20 20 0 0 1 3 10C2 7 2 4 5 3Z',
    ];
@endphp
<svg class="icon {{ ($name ?? '') === 'whatsapp' ? 'wa-icon' : '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @if(($name ?? '') === 'whatsapp')
        <path d="M20.5 11.7a8.5 8.5 0 0 1-12.6 7.5L3 20.5l1.3-4.8a8.5 8.5 0 1 1 16.2-4Z"/><path d="m8 7.4 1.6 2.7-1 1.2a7.9 7.9 0 0 0 4.1 4.1l1.2-1 2.7 1.6c-.4 1.5-1.5 2-3 1.5-3.7-1.2-6.4-3.9-7.5-7.5-.5-1.3.2-2.3 1.9-2.6Z"/>
    @elseif(($name ?? '') === 'instagram')
        <rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".9" fill="currentColor" stroke="none"/>
    @else
        <path d="{{ $paths[$name ?? 'diagonal'] ?? $paths['diagonal'] }}"/>
    @endif
</svg>
