<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AddonController;
use App\Http\Controllers\LoginLinkController;
use App\Http\Controllers\MyListsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\WishlistItemController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/jak-to-dziala', [PageController::class, 'home'])->name('about');
Route::get('/prywatnosc', [PageController::class, 'privacy'])->name('privacy');
Route::get('/moje-listy', [MyListsController::class, 'index'])->name('my-lists');
Route::get('/start', [WishlistController::class, 'start'])->name('start');
Route::delete('/account', [AccountController::class, 'destroy'])->middleware('auth')->name('account.destroy');
Route::patch('/list', [WishlistController::class, 'updateCurrent'])->name('wishlist.update-current');
Route::post('/lists', [WishlistController::class, 'store'])->middleware('throttle:20,1')->name('wishlists.store');

Route::get('/l/{wishlist}', [WishlistController::class, 'show'])->name('wishlist.show');
Route::patch('/l/{wishlist}', [WishlistController::class, 'update'])->name('wishlist.update');
Route::delete('/l/{wishlist}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');
Route::post('/l/{wishlist}/unlock', [WishlistController::class, 'unlock'])->middleware('throttle:10,1')->name('wishlist.unlock');

Route::post('/l/{wishlist}/items', [WishlistItemController::class, 'store'])->middleware('throttle:30,1')->name('items.store');
Route::post('/l/{wishlist}/items/bulk', [WishlistItemController::class, 'bulk'])->middleware('throttle:5,1')->name('items.bulk');
Route::patch('/l/{wishlist}/order', [WishlistItemController::class, 'reorder'])->name('items.reorder');
Route::post('/l/{wishlist}/items/{item}/reservation', [ReservationController::class, 'store'])->middleware('throttle:20,1')->name('reservations.store');
Route::delete('/l/{wishlist}/items/{item}/reservation', [ReservationController::class, 'destroy'])->name('reservations.destroy');

Route::post('/items/preview', [WishlistItemController::class, 'preview'])->middleware('throttle:30,1')->name('items.preview');
Route::post('/items/preview-html', [WishlistItemController::class, 'previewHtml'])->middleware('throttle:20,1')->name('items.preview-html');
Route::post('/items', [WishlistItemController::class, 'storeCurrent'])->middleware('throttle:30,1')->name('items.store-current');
Route::patch('/items/{item}', [WishlistItemController::class, 'update'])->name('items.update');
Route::post('/items/{item}/photo', [WishlistItemController::class, 'photo'])->middleware('throttle:20,1')->name('items.photo');
Route::delete('/items/{item}', [WishlistItemController::class, 'destroy'])->name('items.destroy');

Route::post('/login-link', [LoginLinkController::class, 'store'])->middleware('throttle:5,1')->name('login.link');
Route::get('/login/{user}', [LoginLinkController::class, 'confirm'])->middleware('signed')->name('login.verify');
Route::post('/login/{user}', [LoginLinkController::class, 'verify'])->middleware('signed')->name('login.confirm');
Route::get('/addon/connect', [AddonController::class, 'connectPage'])->name('addon.connect');
Route::post('/addon/connect', [AddonController::class, 'connect'])->middleware('throttle:10,1')->name('addon.connect.store');

Route::post('/logout', [LoginLinkController::class, 'destroy'])->name('logout');
