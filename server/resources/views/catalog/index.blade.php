@extends('catalog.layout')
@section('content')
@if($featured->isNotEmpty())
<section class="destination-hero" aria-label="Izdvojena putovanja" aria-roledescription="karusel" data-catalog-hero>
    <div class="destination-backgrounds" aria-hidden="true">
        @foreach($featured->values() as $index => $hero)
            @if(!empty($hero['images']))<img class="destination-background {{ $index === 0 ? 'is-active' : '' }}" data-hero-background="{{ $index }}" src="{{ $hero['images'][0] }}" alt="" width="1800" height="1100" @if($index === 0) fetchpriority="high" @endif>@endif
        @endforeach
    </div>
    <div class="destination-shade" aria-hidden="true"></div>
    <div class="destination-inner">
        <div class="destination-heading"><span class="destination-kicker">Izdvojena putovanja</span><span class="destination-agency">MAESTRO TRAVEL · ORAŠJE</span></div>
        <div class="destination-body">
            <div>
            @foreach($featured->values() as $index => $hero)
                <div class="destination-copy" data-hero-panel="{{ $index }}" @if($index !== 0) hidden @endif>
                    <p class="destination-country">{{ $hero['record']->destination }} / {{ $hero['transport'] }}</p>
                    <h1 class="destination-title">{{ $hero['title'] }}</h1>
                    <p class="destination-description">{{ $hero['record']->description }}</p>
                    <div class="destination-facts"><span>{{ $hero['date_label'] }}</span><span>{{ $hero['duration_label'] }}</span>@if($hero['departure_place'])<span>Polazak: {{ $hero['departure_place'] }}</span>@endif</div>
                    <div class="destination-actions"><a class="btn yellow" href="{{ $hero['url'] }}">Termini i detalji @include('catalog.icon', ['name' => 'right'])</a><div class="destination-price"><small>Već od</small>@if($hero['minima']->isNotEmpty())@include('catalog.amount', ['amount' => $hero['minima']->first()['price']])@else<strong class="amount-on-request">Cijena na upit</strong>@endif</div></div>
                </div>
            @endforeach
            </div>
            <div class="destination-others" @if($featured->count() < 2) hidden @endif>
                <p>JOŠ IZ PONUDE</p>
                <div class="destination-cards">
                    @foreach($featured->values() as $index => $hero)
                    <button class="destination-card" type="button" data-hero-select="{{ $index }}" aria-label="Izdvoji putovanje: {{ $hero['title'] }}" @if($index === 0 || $index > 2) hidden @endif>
                        @if(!empty($hero['images']))<img data-catalog-image src="{{ $hero['images'][0] }}" alt="" width="400" height="600" loading="lazy">@endif
                        <span class="destination-card-copy"><small>{{ $hero['record']->destination }}</small><strong>{{ $hero['title'] }}</strong><span class="destination-card-price">@if($hero['minima']->isNotEmpty())od {{ preg_replace('/,00 KM$/', ' KM', $hero['minima']->first()['price_label']) }}@else Cijena na upit @endif</span><span class="destination-card-action">Prikaži destinaciju @include('catalog.icon', ['name' => 'right'])</span></span>
                    </button>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="destination-bottom">
            <div class="destination-navigation" @if($featured->count() < 2) hidden @endif>
                <button type="button" class="destination-arrow" data-hero-step="-1" aria-label="Prethodno izdvojeno putovanje">@include('catalog.icon', ['name' => 'left'])</button>
                <button type="button" class="destination-arrow" data-hero-step="1" aria-label="Sljedeće izdvojeno putovanje">@include('catalog.icon', ['name' => 'right'])</button>
                <span id="destination-counter" aria-hidden="true"><strong data-hero-counter>01</strong><span>/ {{ str_pad((string) $featured->count(), 2, '0', STR_PAD_LEFT) }}</span></span>
            </div>
            <a class="destination-all" href="#ponude">Sve ponude @include('catalog.icon', ['name' => 'right'])</a>
        </div>
    </div>
    <p class="sr-only" data-hero-status role="status" aria-live="polite" aria-atomic="true"></p>
</section>
@else
<section class="container featured-empty"><h1>Pronađite svoje sljedeće putovanje.</h1><a class="btn" href="#ponude">Pogledaj ponude @include('catalog.icon', ['name' => 'right'])</a></section>
@endif
<section class="container offers" id="ponude">
    <div class="section-top"><h2>Kamo vas vodi<br><em>sljedeće putovanje?</em></h2><p>Usporedite putovanja i hotelske pakete.<br>Odaberite termin koji vam odgovara.</p></div>
    @include('catalog.filters')
    @if($errors->any())<p class="notice" role="alert">Provjerite filtre i pokušajte ponovno.</p>@endif
    <div id="catalog-results">
        <div class="cards">
            @forelse($offers as $offer)
            <article class="trip-card">
                <a class="card-image" href="{{ $offer['url'] }}" aria-label="Pogledaj ponudu: {{ $offer['title'] }}">
                    @if(!empty($offer['images']))<img data-catalog-image src="{{ $offer['images'][0] }}" alt="{{ $offer['title'] }}" width="600" height="450" loading="lazy">@else<div class="card-image-placeholder">Maestro Travel</div>@endif
                    <span class="tag">{{ $offer['type'] === 'hotel' ? 'Hotel' : $offer['transport'] }}</span><span class="card-image-arrow">@include('catalog.icon', ['name' => 'diagonal'])</span>
                </a>
                <div class="card-copy">
                    <div class="card-meta"><span>{{ $offer['record']->destination }}</span>@if($offer['duration_label'])<span>{{ $offer['duration_label'] }}</span>@endif</div>
                    <h3><a href="{{ $offer['url'] }}">{{ $offer['title'] }}</a></h3>
                    <p>{{ \Illuminate\Support\Str::limit($offer['record']->description, 170) }}</p>
                    <div class="trip-facts"><span>{{ $offer['date_label'] }}</span>@if($offer['departure_place'])<span>Polazište: {{ $offer['departure_place'] }}</span>@endif</div>
                    <div class="card-bottom">@include('catalog.prices')<a href="{{ $offer['url'] }}">Termini i detalji @include('catalog.icon', ['name' => 'right'])</a></div>
                </div>
            </article>
            @empty
            <div class="empty"><h3>Trenutačno nema ponuda za ovaj odabir.</h3><p>Pokušajte drugu destinaciju ili prikažite sve ponude.</p><a class="btn secondary" data-catalog-filter href="{{ route('home', ['type' => $filters['type']]) }}#ponude">Prikaži sve {{ $filters['type'] === 'hotel' ? 'hotele' : 'izlete i putovanja' }}</a></div>
            @endforelse
        </div>
        @if($offers->hasPages())
            <nav class="catalog-pages" aria-label="Stranice ponuda">
                @if($offers->previousPageUrl())<a href="{{ $offers->previousPageUrl() }}">Prethodna</a>@endif
                <span>Stranica {{ $offers->currentPage() }} od {{ $offers->lastPage() }}</span>
                @if($offers->nextPageUrl())<a href="{{ $offers->nextPageUrl() }}">Sljedeća</a>@endif
            </nav>
        @endif
        <p class="result-note" role="status">Ukupno ponuda: {{ $offers->total() }}. Raspoloživost i uvjete potvrđuje agencija.</p>
    </div>
</section>
<section class="container about" id="o-nama">
    <figure class="about-photo"><img src="{{ asset('images/editorial/cinque-terre.jpg') }}" alt="Kuće na obali u Manaroli, Cinque Terre" width="700" height="850" loading="lazy"><figcaption>Manarola, Cinque Terre <span>Italija</span></figcaption></figure>
    <div class="about-copy"><h2>Putovanje je osobno.<br><em>Takav je i naš pristup.</em></h2><p>Volite istraživati svaki kutak grada ili radije polako uživate uz kavu? Recite nam kakvo putovanje zamišljate, a mi ćemo vam pomoći pronaći ono pravo.</p><div class="about-location"><span>Maestro Travel · Orašje</span><p>Posjetite nas u Trećoj ulici 44.<br>Zajedno ćemo proći kroz mogućnosti.</p></div><a class="text-link" href="#kontakt">Upoznajmo se @include('catalog.icon', ['name' => 'right'])</a></div>
</section>
@if($instagramPosts->isNotEmpty())
<section class="container instagram-section" id="instagram" aria-labelledby="instagram-title">
    <div class="instagram-heading"><div><h2 id="instagram-title">S našeg <em>Instagrama.</em></h2><p>Odabrane objave, nove ideje za putovanja.</p></div><a class="instagram-profile" href="https://www.instagram.com/maestro.travel.orasje/" target="_blank" rel="noopener noreferrer">@include('catalog.icon', ['name' => 'instagram'])<span>@maestro.travel.orasje</span>@include('catalog.icon', ['name' => 'diagonal'])</a></div>
    <div class="instagram-posts" id="instagram-posts" aria-label="Odabrane Instagram objave">
    @foreach($instagramPosts as $post)
    @php($label = $post->title ?: 'Objava s Instagrama')
    @if(! $post->imageUrl() && $post->publicEmbedUrl())
    <article class="instagram-post instagram-post-embed"><iframe class="instagram-embed" src="{{ $post->publicEmbedUrl() }}" title="{{ $label }} — objava na Instagramu" loading="lazy" height="640" referrerpolicy="strict-origin-when-cross-origin" data-instagram-embed></iframe><a class="instagram-post-link" href="{{ $post->url }}" target="_blank" rel="noopener noreferrer">Pogledaj objavu na Instagramu @include('catalog.icon', ['name' => 'diagonal'])</a></article>
    @continue
    @endif
    <article class="instagram-post"><a class="instagram-media{{ $post->imageUrl() ? '' : ' is-unavailable' }}" href="{{ $post->url }}" target="_blank" rel="noopener noreferrer">@if($post->imageUrl())<img src="{{ $post->imageUrl() }}" alt="{{ $label }} — promotivna objava agencije; detalji na izvornom Instagram postu" loading="lazy" width="1254" height="1254">@endif<span class="instagram-media-fallback" @if($post->imageUrl()) hidden @endif>@if($post->imageUrl())Fotografija trenutačno nije dostupna.<br>Otvorite objavu na Instagramu.@else Pogledajte objavu na Instagramu.@endif</span><span class="instagram-media-link">@include('catalog.icon', ['name' => 'instagram'])</span></a><div class="instagram-post-copy">@if($post->posted_on)<time datetime="{{ $post->posted_on->toDateString() }}">{{ $post->posted_on->format('d.m.Y.') }}</time>@endif<h3><a href="{{ $post->url }}" target="_blank" rel="noopener noreferrer">{{ $label }}</a></h3>@if($post->description)<p>{{ $post->description }}</p>@endif<a class="instagram-post-link" href="{{ $post->url }}" target="_blank" rel="noopener noreferrer">Pogledaj objavu @include('catalog.icon', ['name' => 'diagonal'])</a></div></article>
    @endforeach
    </div>
    <div class="instagram-navigation"><span id="instagram-counter" role="status" aria-live="polite">Objava 1 od {{ $instagramPosts->count() }}</span><div><button type="button" id="instagram-prev" aria-label="Prethodna Instagram objava" disabled><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M20 12H4m6-6-6 6 6 6"/></svg></button><button type="button" id="instagram-next" aria-label="Sljedeća Instagram objava" @disabled($instagramPosts->count() < 2)><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 12h16m-6-6 6 6-6 6"/></svg></button></div></div>
</section>
@endif
@endsection
