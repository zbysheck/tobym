<?php

namespace App\Http\Controllers;

use App\Models\WishlistItem;
use App\Services\ProductMetadataFetcher;
use App\Support\CurrentWishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WishlistItemController extends Controller
{
    /**
     * Pobiera dane produktu z linku, żeby pokazać je do potwierdzenia przed dodaniem.
     * Bez "deep" odpowiada szybko (bez AI) i zwraca pending=true, gdy AI może jeszcze coś uzupełnić.
     */
    public function preview(Request $request, ProductMetadataFetcher $fetcher): JsonResponse
    {
        $url = $this->validatedUrl($request);
        $metadata = $fetcher->fetch($url, allowAi: $request->boolean('deep'));

        return response()->json([
            'url' => $url,
            'title' => $metadata->title,
            'shop' => $metadata->shop,
            'image_url' => $metadata->imageUrl,
            'price' => $metadata->price,
            'pending' => $metadata->improvable,
        ]);
    }

    public function store(Request $request, CurrentWishlist $current, ProductMetadataFetcher $fetcher): RedirectResponse
    {
        $url = $this->validatedUrl($request);

        $request->validate([
            'title' => ['nullable', 'string', 'max:500'],
            'price' => ['nullable', 'string', 'max:64'],
            'shop' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'string', 'url:http,https', 'max:2000'],
        ]);

        $title = $this->optional($request, 'title');

        if ($title === null) {
            // Bez potwierdzonych danych (np. z addona) pobieramy je sami.
            $metadata = $fetcher->fetch($url);
            $attributes = [
                'title' => $metadata->title,
                'shop' => $metadata->shop,
                'image_url' => $metadata->imageUrl,
                'price' => $metadata->price,
            ];
        } else {
            $attributes = [
                'title' => $title,
                'shop' => $this->optional($request, 'shop'),
                'image_url' => $this->optional($request, 'image_url'),
                'price' => $this->optional($request, 'price'),
            ];
        }

        $wishlist = $current->findOrCreate($request);

        $wishlist->items()->create([
            ...$attributes,
            'url' => $url,
            'position' => ((int) $wishlist->items()->max('position')) + 1,
        ]);

        return redirect()->route('wishlist.show', $wishlist);
    }

    public function update(Request $request, CurrentWishlist $current, WishlistItem $item): RedirectResponse
    {
        $this->ensureOwned($request, $current, $item);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:500'],
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

    private function validatedUrl(Request $request): string
    {
        $request->validate([
            'url' => ['required', 'string', 'url:http,https', 'max:2000'],
        ], [
            'url.required' => 'Wklej link do prezentu.',
            'url.url' => 'To nie wygląda na link. Wklej pełny adres strony produktu.',
            'url.max' => 'Ten link jest za długi.',
        ]);

        return $request->string('url')->trim()->toString();
    }

    private function optional(Request $request, string $key): ?string
    {
        $value = $request->string($key)->trim()->toString();

        return $value === '' ? null : $value;
    }

    private function ensureOwned(Request $request, CurrentWishlist $current, WishlistItem $item): void
    {
        abort_unless($current->find($request)?->id === $item->wishlist_id, 404);
    }
}
