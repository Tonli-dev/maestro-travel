<div class="catalog-prices">
    @forelse($offer['minima'] as $minimum)
        <div class="price"><small>Već od</small>@include('catalog.amount', ['amount' => $minimum['price']])<span class="price-person">{{ $minimum['basis_label'] }}</span>
            @if($minimum['availability'] === 'on_request')<span class="availability-note">Raspoloživost na upit</span>@endif
        </div>
    @empty
        <p>{{ $offer['selectable']->isEmpty() ? 'Trenutačno nema aktualnih termina' : 'Cijena na upit' }}</p>
    @endforelse
</div>
