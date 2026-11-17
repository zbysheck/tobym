<?php

use App\Http\Controllers\LoginLinkController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\WishlistItemController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WishlistController::class, 'home'])->name('home');
Route::get('/l/{wishlist}', [WishlistController::class, 'show'])->name('wishlist.show');
Route::post('/l/{wishlist}/items/{item}/reservation', [ReservationController::class, 'store'])->middleware('throttle:20,1')->name('reservations.store');
Route::delete('/l/{wishlist}/items/{item}/reservation', [ReservationController::class, 'destroy'])->name('reservations.destroy');
Route::patch('/list', [WishlistController::class, 'update'])->name('wishlist.update');

Route::post('/items/preview', [WishlistItemController::class, 'preview'])->middleware('throttle:30,1')->name('items.preview');
Route::post('/items', [WishlistItemController::class, 'store'])->middleware('throttle:30,1')->name('items.store');
Route::patch('/items/{item}', [WishlistItemController::class, 'update'])->name('items.update');
Route::delete('/items/{item}', [WishlistItemController::class, 'destroy'])->name('items.destroy');

Route::post('/login-link', [LoginLinkController::class, 'store'])->middleware('throttle:5,1')->name('login.link');
Route::get('/login/{user}', [LoginLinkController::class, 'confirm'])->middleware('signed')->name('login.verify');
Route::post('/login/{user}', [LoginLinkController::class, 'verify'])->middleware('signed')->name('login.confirm');
Route::post('/logout', [LoginLinkController::class, 'destroy'])->name('logout');
