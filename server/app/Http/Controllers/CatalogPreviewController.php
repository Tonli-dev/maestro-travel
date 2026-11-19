<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Trip;
use App\Support\CatalogPresentation;
use App\Support\HotelDetailPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CatalogPreviewController extends Controller
{
    public function trip(Trip $trip, Request $request, CatalogPresentation $catalog): Response
    {
        return $this->render($trip, $request, $catalog);
    }

    public function hotel(Hotel $hotel, Request $request, HotelDetailPresentation $hotels): Response
    {
        return response()->view('catalog.hotel', array_replace($hotels->detail($hotel, $request), ['preview' => true]))
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function render(Trip|Hotel $record, Request $request, CatalogPresentation $catalog): Response
    {
        return response()->view('catalog.show', array_replace($catalog->detail($record, $request), ['preview' => true]))
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
