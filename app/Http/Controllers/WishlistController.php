<?php

namespace App\Http\Controllers;

use App\Enums\Layout;
use App\Enums\Theme;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Support\CurrentWishlist;
use App\Support\GuestToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WishlistController extends Controller
{
    /**
     * Strona główna: jeśli odwiedzający ma już listę, przenosi pod jej stały adres,
     * a jeśli nie, pokazuje pustą listę gotową do dodania pierwszego prezentu.
     */
    public function home(Request $request, CurrentWishlist $current): Response|RedirectResponse
    {
        $wishlist = $current->find($request);

        if ($wishlist !== null) {
            return redirect()->route('wishlist.show', $wishlist);
        }

        return $this->render(null, editable: true, guestToken: null);
    }

    public function show(Request $request, CurrentWishlist $current, GuestToken $guest, Wishlist $wishlist): Response
    {
        $editable = $current->find($request)?->id === $wishlist->id;

        return $this->render($wishlist, $editable, $editable ? null : $guest->hash($request));
    }

    public function update(Request $request, CurrentWishlist $current): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'theme' => ['sometimes', 'required', Rule::enum(Theme::class)],
            'layout' => ['sometimes', 'required', Rule::enum(Layout::class)],
        ]);

        $wishlist = $current->findOrCreate($request);
        $wishlist->update($data);

        return redirect()->route('wishlist.show', $wishlist);
    }

    /**
     * Właściciel (editable) nigdy nie dostaje informacji o rezerwacjach, żeby prezenty były niespodzianką.
     */
    private function render(?Wishlist $wishlist, bool $editable, ?string $guestToken): Response
    {
        $wishlist?->load('items.reservation');

        return Inertia::render('Wishlist', [
            'editable' => $editable,
            'slug' => $wishlist?->slug,
            'shareUrl' => $wishlist === null ? null : route('wishlist.show', $wishlist),
            'wishlist' => [
                'title' => $wishlist->title ?? Wishlist::DEFAULT_TITLE,
                'theme' => ($wishlist->theme ?? Theme::Swiateczna)->value,
                'layout' => ($wishlist->layout ?? Layout::Tiles)->value,
                'items' => $wishlist === null ? [] : $wishlist->items
                    ->map(fn (WishlistItem $item): array => [
                        'id' => $item->id,
                        'url' => $item->url,
                        'title' => $item->title,
                        'shop' => $item->shop,
                        'image_url' => $item->image_url,
                        'price' => $item->price,
                        'reservation' => $editable || $item->reservation === null ? null : [
                            'name' => $item->reservation->name,
                            'mine' => $guestToken !== null && hash_equals($item->reservation->guest_token, $guestToken),
                        ],
                    ])
                    ->values()
                    ->all(),
            ],
            'themes' => array_map(
                fn (Theme $theme): array => ['value' => $theme->value, 'label' => $theme->label()],
                Theme::cases(),
            ),
        ]);
    }
}
