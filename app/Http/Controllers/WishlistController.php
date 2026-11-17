<?php

namespace App\Http\Controllers;

use App\Enums\Layout;
use App\Enums\Theme;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Support\CurrentWishlist;
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

        return $this->render(null, editable: true);
    }

    public function show(Request $request, CurrentWishlist $current, Wishlist $wishlist): Response
    {
        return $this->render($wishlist, editable: $current->find($request)?->id === $wishlist->id);
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

    private function render(?Wishlist $wishlist, bool $editable): Response
    {
        return Inertia::render('Wishlist', [
            'editable' => $editable,
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
