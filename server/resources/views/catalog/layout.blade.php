<!doctype html>
<html lang="hr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#203c72">
    <meta name="description" content="@yield('description', 'Maestro Travel — putovanja i hotelske ponude iz Orašja. Pregledajte termine i cijene.')">
    @if($preview)<meta name="robots" content="noindex, nofollow">@endif
    <title>@yield('title', 'Maestro Travel — Putovanja koja ostaju s vama')</title>
    <link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php($floatingWhatsapp = isset($message) ? $whatsapp : app(\App\Support\ContactLinks::class)->whatsapp('Pozdrav! Zanimaju me ponude agencije Maestro Travel. Možete li mi poslati više informacija?'))
<body @class(['has-whatsapp' => $floatingWhatsapp])>
<a class="skip" href="#main">Preskoči na sadržaj</a>
<header class="header">
    <div class="container header-inner">
        <a class="brand" href="{{ route('home') }}" aria-label="Maestro Travel — početna"><img src="{{ asset('images/maestro-logo.png') }}" alt="Maestro Travel" width="808" height="211"></a>
        <nav aria-label="Glavna navigacija">
            <a href="{{ route('home', ['type' => 'trip']) }}#ponude">Putovanja</a>
            <a href="{{ route('home') }}#o-nama">O nama</a>
            <a href="{{ route('home') }}#kontakt">Kontakt</a>
        </nav>
        <a class="header-phone" href="tel:+38762102006">@include('catalog.icon', ['name' => 'phone']) 062 102 006</a>
    </div>
</header>
<main id="main" tabindex="-1">@yield('content')</main>
<section class="contact" id="kontakt">
    <div class="container contact-inner">
        <div><h2>Najljepši planovi<br><em>počinju razgovorom.</em></h2><p>Nazovite nas ili svratite u agenciju u Orašju.</p></div>
        <div class="contact-data">
            <div><small>NAZOVITE NAS</small><a class="big-phone" href="tel:+38762102006">062 102 006</a></div>
            <div><small>PIŠITE NAM</small><a href="mailto:info@maestro.ba">info@maestro.ba</a></div>
            <div><small>POSJETITE NAS</small><p>Treća ulica br. 44<br>76270 Orašje</p></div>
            <div><small>RADNO VRIJEME</small><p>Ponedjeljak – petak<br>08:00 – 16:00</p></div>
        </div>
    </div>
</section>
<footer class="footer"><div class="container footer-inner"><a class="footer-brand" href="{{ route('home') }}">MAESTRO <span>travel</span></a><p>Iz Orašja. Prema novim uspomenama.</p><a href="{{ route('filament.admin.auth.login') }}">Prijava zaposlenika</a></div></footer>
@if($floatingWhatsapp)
<a class="wa-floating" id="whatsapp-floating" href="{{ $floatingWhatsapp }}" target="_blank" rel="noopener noreferrer" aria-label="Otvori WhatsApp razgovor s agencijom">
    @include('catalog.icon', ['name' => 'whatsapp'])<span class="wa-floating-copy">WhatsApp</span>
</a>
@endif
</body>
</html>
