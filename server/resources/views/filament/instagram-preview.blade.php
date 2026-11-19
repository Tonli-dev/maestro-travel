{{-- Admin-only preview of the real Instagram post from Instagram's public embed page; nothing is stored.
     Popups may escape the sandbox so links inside the preview open Instagram normally in a new tab. --}}
<div class="maestro-instagram-preview">
    <p class="maestro-instagram-preview-label">Pregled originalne objave</p>
    @if($embed)
        <iframe src="{{ $embed }}" title="Pregled Instagram objave" loading="lazy" scrolling="no"
            referrerpolicy="strict-origin-when-cross-origin"
            sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"></iframe>
        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer">Otvori objavu na Instagramu ↗</a>
    @else
        <div class="maestro-instagram-preview-empty">Upišite poveznicu na objavu i pregled će se prikazati ovdje.</div>
    @endif
</div>
