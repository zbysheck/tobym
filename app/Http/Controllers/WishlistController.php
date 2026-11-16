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
    public function show(Request $request, CurrentWishlist $current): Response
    {
        $wishlist = $current->find($request);

        return Inertia::render('Wishlist', [
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

    public function update(Request $request, CurrentWishlist $current): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'theme' => ['sometimes', 'required', Rule::enum(Theme::class)],
            'layout' => ['sometimes', 'required', Rule::enum(Layout::class)],
        ]);

        $current->findOrCreate($request)->update($data);

        return back();
    }
}
