<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Support\CurrentWishlist;
use App\Support\GuestToken;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function store(Request $request, CurrentWishlist $current, GuestToken $guest, Wishlist $wishlist, WishlistItem $item): RedirectResponse
    {
        $this->ensureGuest($request, $current, $wishlist, $item);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ], [
            'name.required' => 'Podaj imię, żeby inni wiedzieli, kto kupuje.',
        ]);

        try {
            $item->reservation()->create([
                'name' => trim($data['name']),
                'guest_token' => $guest->hashOrCreate($request),
            ]);
        } catch (UniqueConstraintViolationException) {
            return back()->with('status', 'Ktoś właśnie zarezerwował ten prezent.');
        }

        return back();
    }

    public function destroy(Request $request, CurrentWishlist $current, GuestToken $guest, Wishlist $wishlist, WishlistItem $item): RedirectResponse
    {
        $this->ensureGuest($request, $current, $wishlist, $item);

        $token = $guest->hash($request);

        Reservation::query()
            ->where('wishlist_item_id', $item->id)
            ->when($token === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($token !== null, fn ($query) => $query->where('guest_token', $token))
            ->delete();

        return back();
    }

    private function ensureGuest(Request $request, CurrentWishlist $current, Wishlist $wishlist, WishlistItem $item): void
    {
        abort_unless($item->wishlist_id === $wishlist->id, 404);
        // Właściciel nie rezerwuje własnych prezentów (i nie powinien widzieć rezerwacji).
        abort_if($current->owns($request, $wishlist), 403);
    }
}
