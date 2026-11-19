<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Trip;
use App\Support\CatalogListing;
use App\Support\CatalogPresentation;
use App\Support\HotelDetailPresentation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request, CatalogListing $listing): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'type' => ['nullable', Rule::in(['trip', 'hotel'])],
            'transport' => ['nullable', Rule::in(array_keys(CatalogPresentation::TRANSPORT))],
            'category' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'amenities' => ['nullable', 'array', 'list'],
            'amenities.*' => ['required', 'string', 'max:255'],
        ]);
        $filters = array_filter($filters, fn ($value) => filled($value));
        $filters['type'] ??= 'trip';
        if ($filters['type'] === 'hotel') {
            unset($filters['transport']);
            if (isset($filters['amenities'])) {
                $filters['amenities'] = array_values(array_unique($filters['amenities']));
            }
        } else {
            unset($filters['amenities']);
        }

        return view('catalog.index', $listing->results($filters));
    }

    public function trip(Trip $trip, Request $request, CatalogPresentation $catalog): View
    {
        abort_unless($trip->status === 'published', 404);

        return view('catalog.show', $catalog->detail($trip, $request));
    }

    public function hotel(Hotel $hotel, Request $request, HotelDetailPresentation $hotels): View
    {
        abort_unless($hotel->status === 'published', 404);

        return view('catalog.hotel', $hotels->detail($hotel, $request));
    }
}
