@php
    $type = $filters['type'];
    $common = \Illuminate\Support\Arr::only($filters, ['q', 'category', 'region']);
    $selected = collect($chips);
@endphp
<div class="catalog-tools">
    <nav class="catalog-types" aria-label="Vrsta ponude">
        @foreach(['trip' => 'Putovanja', 'hotel' => 'Hoteli'] as $key => $name)
            <a id="catalog-type-{{ $key }}" data-catalog-filter data-offer-type="{{ $key }}"
               href="{{ route('home', ['type' => $key] + ($key === $type ? $filters : $common)) }}#ponude"
               @if($key === $type) aria-current="page" @endif>{{ $name }}</a>
        @endforeach
    </nav>
    @if($shortcuts->isNotEmpty())
        <nav class="catalog-shortcuts" aria-label="Izdvojene kategorije">
            @foreach($shortcuts as $label)
                <a class="filter" id="shortcut-{{ $label->id }}" data-catalog-filter data-category="{{ $label->slug }}"
                   href="{{ route('home', ['category' => $label->slug] + $filters) }}#ponude"
                   @if(($filters['category'] ?? '') === $label->slug) aria-current="true" @endif>{{ $label->name }}</a>
            @endforeach
        </nav>
    @endif
    <form class="catalog-search-form" data-catalog-filters method="get" action="{{ route('home') }}#ponude" role="search">
        <input type="hidden" name="type" value="{{ $type }}">
        <div class="catalog-fields">
            <label class="catalog-field">Destinacija
                <span class="catalog-search-box">@include('catalog.icon', ['name' => 'search'])<input id="catalog-search" type="search" name="q" maxlength="200" value="{{ $filters['q'] ?? '' }}" placeholder="Pretraži ponude" aria-controls="catalog-results"></span>
            </label>
            @foreach(['category' => ['Kategorija', 'Sve kategorije'], 'region' => ['Regija', 'Sve regije']] as $key => [$name, $all])
                @php($options = $choices->get($key, collect())->pluck('name', 'slug'))
                @if(isset($filters[$key]) && !$options->has($filters[$key]))
                    @php($options->put($filters[$key], $selected->firstWhere('field', $key)['name']))
                @endif
                @if($options->isNotEmpty())
                    <label class="catalog-field">{{ $name }}
                        <select name="{{ $key }}" id="catalog-{{ $key }}"><option value="">{{ $all }}</option>
                            @foreach($options as $slug => $text)<option value="{{ $slug }}" @selected(($filters[$key] ?? '') === (string) $slug)>{{ $text }}</option>@endforeach
                        </select>
                    </label>
                @endif
            @endforeach
            @if($type === 'trip')
                <label class="catalog-field">Prijevoz
                    <select name="transport" id="catalog-transport"><option value="">Sav prijevoz</option>
                        @foreach(\App\Support\CatalogPresentation::TRANSPORT as $key => $name)
                            @if($key !== 'own' || in_array($key, $availableTransports, true) || ($filters['transport'] ?? '') === $key)
                                <option value="{{ $key }}" @selected(($filters['transport'] ?? '') === $key)>{{ $name }}</option>
                            @endif
                        @endforeach
                    </select>
                </label>
            @endif
        </div>
        @php($amenities = $choices->get('amenity', collect())->pluck('name', 'slug')->union($selected->where('field', 'amenities')->pluck('name', 'value')))
        @if($type === 'hotel' && $amenities->isNotEmpty())
            <fieldset class="catalog-amenities"><legend>Sadržaji hotela</legend>
                <div>@foreach($amenities as $slug => $name)
                    <label><input id="amenity-{{ md5($slug) }}" type="checkbox" name="amenities[]" value="{{ $slug }}" @checked(in_array((string) $slug, $filters['amenities'] ?? [], true))> {{ $name }}</label>
                @endforeach</div>
            </fieldset>
        @endif
        <button class="btn catalog-search-submit" type="submit">Primijeni filtre</button>
    </form>
    @if($chips)
        <div class="catalog-chips" role="group" aria-label="Odabrani filtri">
            @foreach($chips as $chip)
                <a data-catalog-filter class="catalog-chip" href="{{ route('home', $chip['remaining']) }}#ponude" aria-label="Ukloni filtar: {{ $chip['name'] }}">{{ $chip['name'] }} @include('catalog.icon', ['name' => 'close'])</a>
            @endforeach
            <a data-catalog-filter class="catalog-clear" href="{{ route('home', ['type' => $type]) }}#ponude">Očisti filtre</a>
        </div>
    @endif
</div>
