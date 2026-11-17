<?php

use App\Http\Controllers\AddonController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->prefix('addon')->group(function () {
    Route::get('/lists', [AddonController::class, 'lists'])->name('addon.lists');
    Route::post('/items', [AddonController::class, 'store'])->name('addon.items');
});
