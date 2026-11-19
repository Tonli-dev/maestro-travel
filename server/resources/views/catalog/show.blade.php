@extends('catalog.layout')
@section('title', $offer['title'].' — Maestro Travel')
@section('description', \Illuminate\Support\Str::limit($offer['record']->description ?: $offer['title'], 160))
@section('content')
@php
    $record = $offer['record'];
    $years = $offer['options']->flatMap(fn (array $option): array => [$option['starts_at']->year, $option['ends_at']->year]);
    $yearLabel = $years->isEmpty() ? null : ($years->min() === $years->max() ? $years->min().'.' : $years->min().'–'.$years->max().'.');
    $selectionAnnouncement = $selected
        ? 'Odabran termin '.$selected['starts_at']->format('d.m.Y.').' – '.$selected['ends_at']->format('d.m.Y.').' '.$selected['price_label'].' '.$selected['basis_label'].'. '.$selected['availability_label'].'.'
            .(($itinerary['kind'] ?? null) === 'structured' ? ' Program: '.$itinerary['name'].', '.$itinerary['day_label'].'.' : '')
        : 'Trenutačno nema aktualnih termina. Za nove termine javite se agenciji.';
@endphp
@if($preview)
<aside class="preview-banner" aria-label="Privatni pregled">
    <div class="container"><strong>Privatni pregled</strong> · {{ \App\Filament\Resources\CatalogFields::STATUSES[$record->status] ?? $record->status }}
        <p>Prikazuje posljednje spremljene podatke. Otvaranje pregleda ne sprema promjene iz obrasca.</p>
        @if($record->status !== 'published')<p>Ponuda nije u javnom katalogu jer nema status Objavljeno.</p>@endif
        @if($offer['selectable']->isEmpty())<p>Ponuda nije u javnom katalogu jer nema budućih nepopunjenih termina.</p>@endif
        <a href="{{ \App\Support\CatalogAdminActions::editUrl($record) }}">Natrag na uređivanje</a>
        @if($record->status === 'published') · <a href="{{ $offer['url'] }}" target="_blank" rel="noopener">Otvori javnu ponudu</a>@endif
    </div>
</aside>
@endif
<div class="container detail catalog-detail trip-layout" data-selection-announcement="{{ $selectionAnnouncement }}">
    <a class="back" href="{{ route('home', ['type' => 'trip']) }}#ponude">@include('catalog.icon', ['name' => 'left']) Sva putovanja</a>
    <header class="trip-heading">
        <h1>{{ $offer['title'] }}</h1>
        <div class="trip-meta"><span>{{ $record->destination }}</span>@if($offer['transport'])<span>{{ $offer['transport'] }}</span>@endif</div>
        @include('catalog.labels')
    </header>
    <div class="trip-introduction">
        <div class="trip-media">
            <figure class="trip-photo">
                @if(!empty($offer['images']))<img data-catalog-image src="{{ $offer['images'][0] }}" alt="{{ $offer['title'] }}" width="850" height="650" fetchpriority="high">@else<div class="card-image-placeholder">Maestro Travel</div>@endif
            </figure>
            @if(count($offer['images']) > 1)
                <div class="trip-gallery" aria-label="Galerija fotografija">@foreach(array_slice($offer['images'], 1) as $image)<a href="{{ $image }}" target="_blank" rel="noopener noreferrer" aria-label="Otvori fotografiju {{ $loop->iteration + 1 }}: {{ $offer['title'] }}"><img data-catalog-image src="{{ $image }}" alt="{{ $offer['title'] }} — fotografija {{ $loop->iteration + 1 }}" width="180" height="120" loading="lazy"></a>@endforeach</div>
            @endif
        </div>
        <section class="trip-context" aria-labelledby="trip-about-heading">
            <h2 id="trip-about-heading">O putovanju</h2>
            @if($record->description)<p class="trip-description">{{ $record->description }}</p>@endif
            @if($offer['minima']->isNotEmpty())<div class="trip-starting-price">@include('catalog.prices')</div>@elseif($offer['selectable']->isNotEmpty())<p>Cijena na upit</p>@endif
            <p class="trip-intro">Odaberite termin koji vam odgovara. Za raspoloživost i dogovor javite se agenciji.</p>
            <div class="trip-context-links">
                <a class="text-link" href="#termini">Termini i cijene @include('catalog.icon', ['name' => 'right'])</a>
                @if($itinerary)<a class="text-link" href="#program">Program putovanja @include('catalog.icon', ['name' => 'right'])</a>@endif
            </div>
        </section>
    </div>
    <div class="trip-selection">
        <section class="trip-dates" id="termini" aria-labelledby="term-heading">
            <div class="trip-section-heading"><h2 id="term-heading">Odaberite termin</h2>@if($yearLabel)<span>{{ $yearLabel }}</span>@endif</div>
            <p class="trip-date-help">Cijene su navedene po osobi. Raspoloživost potvrđuje agencija.</p>
            @if($invalidSelection)<p class="notice" role="status">Odabrani termin nije dostupan ili ne pripada ovoj ponudi. @if($selected)Prikazan je prvi raspoloživi termin.@elseTrenutačno nema aktualnih termina.@endif</p>@endif
            @if($offer['selectable']->isEmpty())<p class="notice">Trenutačno nema aktualnih termina. Za nove termine javite se agenciji.</p>@endif
            <div class="trip-date-options" data-trip-options data-departure-list role="radiogroup" aria-labelledby="term-heading">
                @foreach($offer['options'] as $option)
                @php
                    $isSelected = $selected && $selected['id'] === $option['id'];
                    $minimum = $offer['minima']->first();
                    $lowest = $option['selectable'] && $option['price'] !== null && $minimum && $option['price'] === $minimum['price'];
                @endphp
                <form class="trip-option-form" data-term-form method="get" action="{{ url()->current() }}#termini">
                    <input type="hidden" name="departure" value="{{ $option['id'] }}">
                    <button class="trip-date-choice" type="submit" role="radio" aria-checked="{{ $isSelected ? 'true' : 'false' }}" data-term-id="{{ $option['id'] }}" @disabled(!$option['selectable']) aria-label="{{ $option['starts_at']->format('d.m.Y.') }} do {{ $option['ends_at']->format('d.m.Y.') }} {{ $option['selectable'] ? $option['price_label'].' '.$option['basis_label'].'. ' : '' }}{{ $option['availability_label'] }}{{ $lowest ? '. Najniža cijena' : '' }}">
                        <span class="trip-option-mark">@include('catalog.icon', ['name' => 'check'])</span>
                        <span class="trip-date-copy"><span>{{ $option['range_label'] }}@if($option['starts_at']->year === $option['ends_at']->year) <small>{{ $option['starts_at']->year }}.</small>@endif</span><small>{{ $option['duration_label'] }}@if($option['departure_place']) · {{ $option['departure_place'] }}@endif</small><small>{{ $option['availability_label'] }}</small></span>
                        <span class="trip-date-price">@if($option['selectable'])@include('catalog.amount', ['amount' => $option['price']])<small>{{ $lowest ? 'Najniža cijena' : $option['basis_label'] }}</small>@else<span>{{ $option['availability_label'] }}</span>@endif</span>
                    </button>
                </form>
                @endforeach
            </div>
        </section>
        <aside class="trip-inquiry" data-trip-inquiry aria-labelledby="trip-inquiry-heading">
            <h2 id="trip-inquiry-heading">Vaš upit</h2>
            @if($selected)
                <p class="trip-inquiry-dates">{{ $selected['starts_at']->format('d.m.Y.') }} – {{ $selected['ends_at']->format('d.m.Y.') }}</p>
                <div class="price trip-inquiry-price">@include('catalog.amount', ['amount' => $selected['price']])<span>{{ $selected['basis_label'] }}</span></div>
                <p class="trip-inquiry-availability">{{ $selected['availability_label'] }}</p>
                <dl class="trip-inquiry-facts">
                    <div><dt>Trajanje</dt><dd>{{ $selected['duration_label'] }}</dd></div>
                    @if($selected['departure_place'])<div><dt>Polazište</dt><dd>{{ $selected['departure_place'] }}</dd></div>@endif
                    @if(($itinerary['kind'] ?? null) === 'structured')<div><dt>Program</dt><dd>{{ $itinerary['name'] }}</dd></div>@endif
                </dl>
            @else
                <p>Za nove termine i aktualne ponude javite se agenciji.</p>
            @endif
            <div class="catalog-contact">@include('catalog.contact-actions', ['compact' => false])</div>
            <p class="trip-confirmation">Raspoloživost i uvjete putovanja potvrđuje agencija.</p>
        </aside>
    </div>
    <p class="sr-only" id="term-selection-status" role="status" aria-live="polite" aria-atomic="true"></p>
    @if($itinerary || $record->included || $record->excluded)
    <div class="trip-program" id="program">
        @if($itinerary)
        <section class="trip-itinerary" aria-labelledby="trip-program-heading" data-itinerary="{{ $itinerary['kind'] }}">
            <h2 id="trip-program-heading">Vaše putovanje, dan po dan.</h2>
            @switch($itinerary['kind'])
                @case('structured')
                    <p class="trip-program-variant">{{ $itinerary['name'] }} · {{ $itinerary['day_label'] }}</p>
                    @foreach($itinerary['days'] as $day)
                        <article class="itinerary"><h3>{{ $loop->iteration }}. dan · {{ $day['title'] }}</h3>@if(trim($day['description']) !== '')<p>{{ $day['description'] }}</p>@endif</article>
                    @endforeach
                    @break
                @case('legacy')
                    @foreach(preg_split('/\R/u', $itinerary['text'], -1, PREG_SPLIT_NO_EMPTY) as $line)
                        @php($parts = explode('|', $line, 2))
                        <article class="itinerary">@if(count($parts) === 2)<h3>{{ $loop->iteration }}. {{ $parts[0] }}</h3><p>{{ $parts[1] }}</p>@else<p>{{ $line }}</p>@endif</article>
                    @endforeach
                    @break
                @case('missing')
                    <p class="notice">Program za odabrani termin trenutačno nije dostupan. Za detalje programa javite se agenciji.</p>
                    @break
                @default
                    <p class="notice">Program ovisi o terminu putovanja. Za aktualne termine i program javite se agenciji.</p>
            @endswitch
        </section>
        @endif
        <div class="trip-costs">
            @foreach(['included' => 'Što je uključeno?', 'excluded' => 'Dodatni troškovi'] as $field => $heading)
                @if($record->$field)<section aria-labelledby="trip-{{ $field }}-heading"><h2 id="trip-{{ $field }}-heading">{{ $heading }}</h2><ul class="included">@foreach(preg_split('/\R/u', $record->$field, -1, PREG_SPLIT_NO_EMPTY) as $line)<li>{{ $line }}</li>@endforeach</ul></section>@endif
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
