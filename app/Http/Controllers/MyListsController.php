<?php

namespace App\Http\Controllers;

use App\Enums\Theme;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Support\CurrentWishlist;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Moje listy": wszystkie listy właściciela (konta albo tej przeglądarki) i ustawienia konta.
 */
class MyListsController extends Controller
{
    public function index(Request $request, CurrentWishlist $current): Response
    {
        $today = now()->startOfDay();

        $lists = $current->query($request)?->withCount('items')
            ->with(['items' => fn ($query) => $query->whereNotNull('image_url')])
            ->latest('updated_at')
            ->get() ?? collect();

        return Inertia::render('MyLists', [
            'lists' => $lists->map(fn (Wishlist $list): array => [
                'slug' => $list->slug,
                'title' => $list->title,
                'theme' => $list->theme->value,
                'accent' => $list->accent,
                'event_date' => $list->event_date?->toDateString(),
                'past' => $list->event_date !== null && $list->event_date->lt($today),
                'items_count' => $list->items_count,
                'images' => $list->items->take(3)->map(fn (WishlistItem $item): ?string => $item->image_url)->values(),
                'has_pin' => $list->pin_hash !== null,
            ])->values(),
            'themes' => array_map(
                fn (Theme $theme): array => ['value' => $theme->value, 'label' => $theme->label()],
                Theme::cases(),
            ),
        ]);
    }
}
