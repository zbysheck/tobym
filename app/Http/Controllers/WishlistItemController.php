<?php

namespace App\Http\Controllers;

use App\Models\WishlistItem;
use App\Services\ProductMetadataFetcher;
use App\Support\CurrentWishlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WishlistItemController extends Controller
{
    public function store(Request $request, CurrentWishlist $current, ProductMetadataFetcher $fetcher): RedirectResponse
    {
        $request->validate([
            'url' => ['required', 'string', 'url:http,https', 'max:2000'],
        ], [
            'url.required' => 'Wklej link do prezentu.',
            'url.url' => 'To nie wygląda na link. Wklej pełny adres strony produktu.',
            'url.max' => 'Ten link jest za długi.',
        ]);

        $url = $request->string('url')->trim()->toString();
        $metadata = $fetcher->fetch($url);
        $wishlist = $current->findOrCreate($request);

        $wishlist->items()->create([
            'url' => $url,
            'title' => $metadata->title,
            'shop' => $metadata->shop,
            'image_url' => $metadata->imageUrl,
            'price' => $metadata->price,
            'position' => ((int) $wishlist->items()->max('position')) + 1,
        ]);

        return back();
    }

    public function update(Request $request, CurrentWishlist $current, WishlistItem $item): RedirectResponse
    {
        $this->ensureOwned($request, $current, $item);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'price' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $item->update($data);

        return back();
    }

    public function destroy(Request $request, CurrentWishlist $current, WishlistItem $item): RedirectResponse
    {
        $this->ensureOwned($request, $current, $item);

        $item->delete();

        return back();
    }

    private function ensureOwned(Request $request, CurrentWishlist $current, WishlistItem $item): void
    {
        abort_unless($current->find($request)?->id === $item->wishlist_id, 404);
    }
}
