<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CatalogPreviewController;
use App\Http\Middleware\EnsureActiveEmployee;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'index'])->name('home');
Route::get('/putovanja/{trip:slug}', [CatalogController::class, 'trip'])->name('trips.show');
Route::get('/hoteli/{hotel:slug}', [CatalogController::class, 'hotel'])->name('hotels.show');
Route::middleware(EnsureActiveEmployee::class)->prefix('admin/preview')->name('admin.preview.')->group(function (): void {
    Route::get('/trips/{trip}', [CatalogPreviewController::class, 'trip'])->name('trips');
    Route::get('/hotels/{hotel}', [CatalogPreviewController::class, 'hotel'])->name('hotels');
});
