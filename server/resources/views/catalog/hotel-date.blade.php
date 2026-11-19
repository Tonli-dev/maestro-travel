@php($chosen = $selected && $selected['id'] === $option['id'])
<form class="hotel-option-form" data-term-form method="get" action="{{ url()->current() }}#termini">
    @include('catalog.hotel-query', ['query' => array_replace($hotelQuery, ['rate' => $option['id']])])
    <button @class(['hotel-date-choice', 'is-sold-out' => !$option['selectable']]) type="submit" role="radio" aria-checked="{{ $chosen ? 'true' : 'false' }}" data-term-id="hotel-rate-{{ $option['id'] }}" @disabled(!$option['selectable']) aria-label="{{ $option['starts_at']->format('d.m.Y.') }} do {{ $option['ends_at']->format('d.m.Y.') }} {{ $option['selectable'] ? $option['price_label'].' '.$option['basis_label'].'. ' : '' }}{{ $option['availability_label'] }}">
        <span class="hotel-option-mark">@include('catalog.icon', ['name' => 'check'])</span>
        <span class="hotel-date-copy"><span>{{ $option['range_label'] }}@if($option['starts_at']->year === $option['ends_at']->year) <small>{{ $option['starts_at']->year }}.</small>@endif</span>@if($option['selectable'])<small>{{ $option['availability_label'] }}</small>@endif</span>
        <span class="hotel-date-price">@if($option['selectable'])@include('catalog.amount', ['amount' => $option['price']])<small>{{ $option['basis_label'] }}</small>@else<span>{{ $option['availability_label'] }}</span>@endif</span>
    </button>
</form>
