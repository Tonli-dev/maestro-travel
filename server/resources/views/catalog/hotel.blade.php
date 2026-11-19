@extends('catalog.layout')
@section('title', $offer['title'].' — Maestro Travel')
@section('description', \Illuminate\Support\Str::limit($offer['record']->description ?: $offer['title'], 160))
@section('content')
@php($record = $offer['record'])
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
<div class="container detail catalog-detail hotel-detail hotel-layout" data-hotel-detail data-selection-announcement="{{ $selectionAnnouncement }}">
    <a class="back" href="{{ route('home', ['type' => 'hotel']) }}#ponude">@include('catalog.icon', ['name' => 'left']) Sve hotelske ponude</a>
    <header class="hotel-heading">
        <h1>{{ $offer['title'] }}</h1>
        <div class="hotel-meta"><span>{{ $record->destination }}</span>@if($record->address)<span>{{ $record->address }}</span>@endif @if($record->stars)<span>Kategorija: {{ $record->stars }} zvjezdica</span>@endif</div>
        @include('catalog.labels')
    </header>
    <div class="hotel-overview">
        <div class="hotel-media">
            <figure class="hotel-photo">
                @if(!empty($offer['images']))<img data-catalog-image src="{{ $offer['images'][0] }}" alt="{{ $offer['title'] }}" width="850" height="650" fetchpriority="high">@else<div class="card-image-placeholder">Maestro Travel</div>@endif
            </figure>
            @if(count($offer['images']) > 1)
                <div class="hotel-gallery" aria-label="Galerija fotografija">@foreach(array_slice($offer['images'], 1) as $image)<a href="{{ $image }}" target="_blank" rel="noopener noreferrer" aria-label="Otvori fotografiju {{ $loop->iteration + 1 }}: {{ $offer['title'] }}"><img data-catalog-image src="{{ $image }}" alt="{{ $offer['title'] }} — fotografija {{ $loop->iteration + 1 }}" width="180" height="120" loading="lazy"></a>@endforeach</div>
            @endif
        </div>
        <section class="hotel-package-panel" id="paketi" aria-labelledby="hotel-package-heading">
            <h2 id="hotel-package-heading">1. Odaberite paket</h2>
            @if($packages->isEmpty())<p class="notice">Trenutačno nema aktualnih paketa. Za više informacija javite se agenciji.</p>@endif
            <div class="hotel-package-options" data-hotel-options role="radiogroup" aria-labelledby="hotel-package-heading">
                @foreach($packages as $package)
                @php($active = $activePackage && $activePackage['record']->id === $package['record']->id)
                <form class="hotel-option-form" data-term-form method="get" action="{{ url()->current() }}#paketi">
                    @include('catalog.hotel-query', ['query' => $package['query']])
                    <button class="hotel-package-choice" type="submit" role="radio" aria-checked="{{ $active ? 'true' : 'false' }}" data-term-id="hotel-package-{{ $package['record']->id }}">
                        <span class="hotel-option-mark">@include('catalog.icon', ['name' => 'check'])</span>
                        <span><strong>{{ $package['record']->name }}</strong><small>@if($package['minimum'])Od {{ $package['minimum']['price_label'] }}@elseif($package['selectable']->isNotEmpty())Cijena na upit @else Trenutačno nema raspoloživih termina @endif · {{ $package['basis_label'] }}</small></span>
                    </button>
                </form>
                @endforeach
            </div>
            @if($activePackage)
            <div class="hotel-package-context" aria-label="Detalji odabranog paketa">
                <p>@if($activePackage['record']->room_type)<span>{{ $activePackage['record']->room_type }}</span>@endif @if($activePackage['record']->board)<span>{{ $activePackage['record']->board }}</span>@endif @if($activePackage['record']->nights)<span>{{ $activePackage['record']->nights }} noćenja</span>@endif</p>
                <p>Cijene su izražene <strong>{{ $activePackage['basis_label'] }}</strong>.</p>
            </div>
            @endif
            <p class="hotel-intro">Odaberite paket i jedan od ponuđenih boravaka. Za raspoloživost i dogovor javite se agenciji.</p>
            @if($record->description)<details class="hotel-about"><summary>O hotelu</summary><p>{{ $record->description }}</p></details>@endif
        </section>
    </div>
    <div class="hotel-selection">
        <section class="hotel-dates" id="termini" data-hotel-dates aria-labelledby="hotel-date-heading">
            <div class="hotel-section-heading"><h2 id="hotel-date-heading">2. Odaberite termin</h2>@if($yearLabel)<span>{{ $yearLabel }}</span>@endif</div>
            <p class="hotel-date-help">Datumi označavaju fiksni dolazak i odlazak.</p>
            @if($hotelNotice)<p class="notice hotel-selection-notice" role="status">{{ $hotelNotice }}</p>@endif
            @if($showMonthFilter)
            <form class="hotel-month-form" data-term-form method="get" action="{{ url()->current() }}#termini">
                @include('catalog.hotel-query', ['query' => \Illuminate\Support\Arr::except($hotelQuery, ['month'])])
                <div class="hotel-month-field"><label for="hotel-month">Mjesec boravka</label><select id="hotel-month" name="month" data-hotel-auto-submit data-term-id="hotel-month"><option value="all" @selected($month === null)>Svi mjeseci</option>@foreach($months as $key => $label)<option value="{{ $key }}" @selected($month === $key)>{{ $label }}</option>@endforeach</select></div>
                <button class="btn secondary hotel-control-submit" type="submit">Prikaži termine</button>
            </form>
            @endif
            @if($visibleOptions->isEmpty())
                <div class="hotel-empty"><p>Trenutačno nema aktualnih termina{{ $month ? ' u odabranom mjesecu' : ' za ovaj paket' }}.</p><p>@if($months->count() > 1)Pokušajte drugi mjesec ili paket. @elseif($packages->count() > 1)Pokušajte drugi paket. @endif Za alternativne termine javite se agenciji.</p></div>
            @endif
            <p class="hotel-date-count">{{ $availableCount }} {{ $availableCount === 1 ? 'raspoloživ termin' : (in_array($availableCount % 100, [11, 12, 13, 14]) || !in_array($availableCount % 10, [2, 3, 4]) ? 'raspoloživih termina' : 'raspoloživa termina') }}</p>
            <div class="hotel-date-options hotel-available-options" data-hotel-options role="radiogroup" aria-labelledby="hotel-date-heading">
                @foreach($visibleOptions as $option)@include('catalog.hotel-date')@endforeach
            </div>
            @if($soldOut->isNotEmpty())
            <form data-term-form class="hotel-sold-out-form" method="get" action="{{ url()->current() }}#termini">
                @include('catalog.hotel-query', ['query' => \Illuminate\Support\Arr::except($hotelQuery, ['sold_out'])])
                <label><input type="checkbox" name="sold_out" value="1" data-hotel-auto-submit data-term-id="hotel-sold-out" @checked($showSoldOut) aria-controls="hotel-sold-out-options"> Prikaži popunjene termine ({{ $soldOut->count() }})</label>
                <button class="btn secondary hotel-control-submit" type="submit">Prikaži odabir</button>
            </form>
            @if($showSoldOut)<div class="hotel-date-options hotel-sold-out-options" id="hotel-sold-out-options" role="group" aria-label="Popunjeni termini">@foreach($soldOut as $option)@include('catalog.hotel-date')@endforeach</div>@else<div id="hotel-sold-out-options" hidden></div>@endif
            @endif
        </section>
        <aside class="hotel-inquiry" data-hotel-inquiry aria-labelledby="hotel-inquiry-heading">
            <h2 id="hotel-inquiry-heading">Vaš upit</h2>
            @if($activePackage)<p class="hotel-inquiry-package">{{ $activePackage['record']->name }}</p>@endif
            @if($selected)
                <p class="hotel-inquiry-dates">{{ $selected['starts_at']->format('d.m.Y.') }} – {{ $selected['ends_at']->format('d.m.Y.') }}</p>
                <div class="price hotel-inquiry-price">@include('catalog.amount', ['amount' => $selected['price']])<span>{{ $selected['basis_label'] }}</span></div>
                <p class="hotel-inquiry-availability">{{ $selected['availability_label'] }}</p>
            @elseif($activePackage && $activePackage['selectable']->isNotEmpty())
                <p class="hotel-choose-date">Odaberite termin za pripremu upita.</p>
            @else
                <p class="hotel-choose-date">Za nove termine i aktualne ponude javite se agenciji.</p>
            @endif
            @if($activePackage && $activePackage['record']->notes)
                <p class="hotel-note-lead">{{ \Illuminate\Support\Str::limit($activePackage['record']->notes, 180) }}</p>
                @if(\Illuminate\Support\Str::length($activePackage['record']->notes) > 180)<details class="hotel-package-notes"><summary>Svi uvjeti i doplate</summary><p>{{ $activePackage['record']->notes }}</p></details>@endif
            @endif
            @if(!$selected && $activePackage && $activePackage['selectable']->isNotEmpty())<a class="btn hotel-select-prompt" href="#termini">Odaberite termin za upit @include('catalog.icon', ['name' => 'right'])</a>@endif
            <div class="catalog-contact">@include('catalog.contact-actions', ['compact' => false, 'whatsapp' => $selected || !$activePackage || $activePackage['selectable']->isEmpty() ? $whatsapp : null])</div>
            <p class="hotel-confirmation">Raspoloživost i uvjete potvrđuje agencija.</p>
        </aside>
    </div>
    <p class="sr-only" id="term-selection-status" role="status" aria-live="polite" aria-atomic="true"></p>
</div>
@endsection
