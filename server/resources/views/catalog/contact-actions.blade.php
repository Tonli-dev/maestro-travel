@if($whatsapp)
    <div class="wa-context"><a class="wa-action" data-whatsapp-context href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer">@include('catalog.icon', ['name' => 'whatsapp'])<span>Upit preko WhatsAppa</span>@include('catalog.icon', ['name' => 'right'])</a></div>
@endif
@if(!($compact ?? false) && $viber)
    <details class="viber-contact">
        <summary>Upit preko Vibera @include('catalog.icon', ['name' => 'right'])</summary>
        <p>Viber: <strong>{{ $viber }}</strong>. Otvorite razgovor s ovim brojem i zalijepite podatke ponude.</p>
        <label class="contact-message">Podaci za Viber<textarea id="contact-message" readonly>{{ $message }}</textarea></label>
        <button class="btn secondary" data-copy-message type="button">Kopiraj podatke za Viber</button>
        <div class="copy-status" id="copy-status" role="status" aria-live="polite"></div>
    </details>
@endif
<a class="{{ ($compact ?? false) ? 'selected-term-phone' : 'direct-call' }}" href="tel:+38762102006">@if($compact ?? false)Ili nazovite 062 102 006 @else Nazovite nas: <strong>062 102 006</strong>@endif</a>
