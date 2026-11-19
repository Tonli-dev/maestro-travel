@if($amount === null)
    <strong class="amount-on-request">Cijena na upit</strong>
@else
    <strong>{{ preg_replace('/,00$/', '', substr(\App\Support\CatalogPresentation::money($amount), 0, -3)) }} <span>KM</span></strong>
@endif
