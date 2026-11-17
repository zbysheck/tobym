<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\ProductMetadataFetcher;
use App\Support\ActivityLog;
use App\Support\CurrentWishlist;
use App\Support\Urls;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WishlistItemController extends Controller
{
    private const BULK_LIMIT = 20;

    /**
     * Pobiera dane produktu z linku, żeby pokazać je do potwierdzenia przed dodaniem.
     * Bez "deep" odpowiada szybko (bez AI) i zwraca pending=true, gdy AI może jeszcze coś uzupełnić.
     */
    public function preview(Request $request, ProductMetadataFetcher $fetcher): JsonResponse
    {
        $url = $this->validatedUrl($request);
        $metadata = $fetcher->fetch($url, allowAi: $request->boolean('deep'));

        if (! $request->boolean('deep')) {
            $result = $metadata->gone ? 'produktu nie ma' : ($metadata->blocked ? 'sklep zablokował' : 'odczytano');
            ActivityLog::record('preview', 'Podgląd linku: '.(Urls::host($url) ?? $url).", {$result}", null, [
                'host' => Urls::host($url),
                'blocked' => $metadata->blocked,
                'image' => $metadata->imageUrl !== null,
                'price' => $metadata->price !== null,
            ]);
        }

        return response()->json([
            'url' => $url,
            'title' => $metadata->title,
            'shop' => $metadata->shop,
            'image_url' => $metadata->imageUrl,
            'price' => $metadata->price,
            'pending' => $metadata->improvable,
            'blocked' => $metadata->blocked,
            'gone' => $metadata->gone,
        ]);
    }

    /**
     * Podgląd z HTML, który addon odczytał w przeglądarce użytkownika (gdy sklep nie wpuszcza serwera).
     * AI może przy tym nauczyć się przepisu dla sklepu, więc kolejne strony idą już bez niego.
     */
    public function previewHtml(Request $request, ProductMetadataFetcher $fetcher): JsonResponse
    {
        $url = $this->validatedUrl($request);
        $request->validate(['html' => ['required', 'string']]);

        set_time_limit(150);
        $html = mb_strcut($request->string('html')->toString(), 0, 3_000_000, 'UTF-8');
        $metadata = $fetcher->fromHtml($url, $html);
        ActivityLog::record('preview.addon', 'Wtyczka odczytała: '.(Urls::host($url) ?? $url), null, [
            'host' => Urls::host($url),
            'image' => $metadata->imageUrl !== null,
            'price' => $metadata->price !== null,
        ]);

        return response()->json([
            'url' => $url,
            'title' => $metadata->title,
            'shop' => $metadata->shop,
            'image_url' => $metadata->imageUrl,
            'price' => $metadata->price,
            'pending' => false,
            'blocked' => false,
            'gone' => false,
        ]);
    }

    public function store(Request $request, CurrentWishlist $current, ProductMetadataFetcher $fetcher, Wishlist $wishlist): RedirectResponse
    {
        abort_unless($current->owns($request, $wishlist), 404);

        $this->createItem($request, $wishlist, $fetcher);

        return redirect()->route('wishlist.show', $wishlist);
    }

    /**
     * Dodanie z okna potwierdzenia: na wskazaną listę ("list"), na nową listę ("new_list")
     * albo, bez wskazania, na najnowszą listę (tworzoną, jeśli jej nie ma). Używa tego też addon.
     */
    public function storeCurrent(Request $request, CurrentWishlist $current, ProductMetadataFetcher $fetcher): RedirectResponse
    {
        $this->validateItem($request);
        $request->validate([
            'list' => ['nullable', 'string', 'max:16'],
            'new_list' => ['nullable', 'string', 'max:120'],
        ]);

        $slug = $this->optional($request, 'list');
        $newList = $this->optional($request, 'new_list');

        if ($newList !== null) {
            $wishlist = $current->create($request, ['title' => $newList]);
        } elseif ($slug !== null) {
            $wishlist = $current->query($request)?->where('slug', $slug)->first();
            abort_if($wishlist === null, 404);
        } else {
            $wishlist = $current->findOrCreate($request);
        }

        $this->createItem($request, $wishlist, $fetcher);

        return redirect()->route('wishlist.show', $wishlist);
    }

    /**
     * Wiele linków naraz: każdy dodawany w trybie szybkim (bez czekania na AI).
     */
    public function bulk(Request $request, CurrentWishlist $current, ProductMetadataFetcher $fetcher, Wishlist $wishlist): RedirectResponse
    {
        abort_unless($current->owns($request, $wishlist), 404);

        $request->validate(['urls' => ['required', 'string', 'max:20000']], [
            'urls.required' => 'Wklej przynajmniej jeden link.',
        ]);

        $urls = collect(preg_split('/\s+/', $request->string('urls')->toString()) ?: [])
            ->map(fn (string $url): string => trim($url))
            ->filter(fn (string $url): bool => filter_var($url, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $url) === 1)
            ->unique()
            ->take(self::BULK_LIMIT)
            ->values();

        if ($urls->isEmpty()) {
            throw ValidationException::withMessages(['urls' => 'Nie znalazłem żadnego linku.']);
        }

        set_time_limit(180);
        $position = (int) $wishlist->items()->max('position');

        foreach ($urls as $url) {
            $metadata = $fetcher->fetch($url, allowAi: false);
            $wishlist->items()->create([
                'url' => $url,
                'title' => $metadata->title,
                'shop' => $metadata->shop,
                'image_url' => $metadata->imageUrl,
                'price' => $metadata->price,
                'position' => ++$position,
            ]);
        }

        return redirect()->route('wishlist.show', $wishlist)->with('status', 'Dodano prezentów: '.$urls->count().'.');
    }

    public function reorder(Request $request, CurrentWishlist $current, Wishlist $wishlist): RedirectResponse
    {
        abort_unless($current->owns($request, $wishlist), 404);

        $ids = $request->validate([
            'ids' => ['required', 'array', 'max:1000'],
            'ids.*' => ['integer'],
        ])['ids'];

        $owned = $wishlist->items()->pluck('id')->all();

        foreach (array_values($ids) as $index => $id) {
            if (in_array($id, $owned, true)) {
                WishlistItem::query()->whereKey($id)->update(['position' => $index + 1]);
            }
        }

        return redirect()->route('wishlist.show', $wishlist);
    }

    public function update(Request $request, CurrentWishlist $current, WishlistItem $item): RedirectResponse
    {
        $this->ensureOwned($request, $current, $item);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:500'],
            'price' => ['sometimes', 'nullable', 'string', 'max:64'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'priority' => ['sometimes', 'boolean'],
        ]);

        $item->update($data);

        return back();
    }

    public function photo(Request $request, CurrentWishlist $current, WishlistItem $item): RedirectResponse
    {
        $this->ensureOwned($request, $current, $item);

        $request->validate(['photo' => $this->photoRules(required: true)], $this->photoMessages());
        $photo = $request->file('photo');

        if ($photo instanceof UploadedFile) {
            $this->deletePhoto($item);
            $path = $photo->store('gifts', 'public');
            $item->update(['image_path' => $path, 'image_url' => '/storage/'.$path]);
        }

        return back();
    }

    public function destroy(Request $request, CurrentWishlist $current, WishlistItem $item): RedirectResponse
    {
        $this->ensureOwned($request, $current, $item);

        $this->deletePhoto($item);
        $item->delete();

        return back();
    }

    private function createItem(Request $request, Wishlist $wishlist, ProductMetadataFetcher $fetcher): WishlistItem
    {
        $this->validateItem($request);

        $url = $this->optional($request, 'url');
        $title = $this->optional($request, 'title');

        if ($title === null && $url !== null) {
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
                'title' => $title ?? 'Prezent',
                'shop' => $this->optional($request, 'shop'),
                'image_url' => $this->optional($request, 'image_url'),
                'price' => $this->optional($request, 'price'),
            ];
        }

        $photo = $request->file('photo');

        if ($photo instanceof UploadedFile) {
            $path = $photo->store('gifts', 'public');
            $attributes['image_path'] = $path;
            $attributes['image_url'] = '/storage/'.$path;
        }

        return $wishlist->items()->create([
            ...$attributes,
            'url' => $url,
            'note' => $this->optional($request, 'note'),
            'priority' => $request->boolean('priority'),
            'position' => ((int) $wishlist->items()->max('position')) + 1,
        ]);
    }

    private function validateItem(Request $request): void
    {
        $request->validate([
            'url' => ['nullable', 'string', 'url:http,https', 'max:2000', 'required_without:title'],
            'title' => ['nullable', 'string', 'max:500'],
            'price' => ['nullable', 'string', 'max:64'],
            'shop' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'string', 'url:http,https', 'max:2000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'priority' => ['nullable', 'boolean'],
            'photo' => $this->photoRules(required: false),
        ], [
            'url.required_without' => 'Wklej link albo wpisz nazwę prezentu.',
            'url.url' => 'To nie wygląda na link. Wklej pełny adres strony produktu.',
            ...$this->photoMessages(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function photoRules(bool $required): array
    {
        return [$required ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'];
    }

    /**
     * @return array<string, string>
     */
    private function photoMessages(): array
    {
        return [
            'photo.image' => 'To musi być zdjęcie.',
            'photo.max' => 'Zdjęcie może mieć najwyżej 5 MB.',
        ];
    }

    private function deletePhoto(WishlistItem $item): void
    {
        if ($item->image_path !== null) {
            Storage::disk('public')->delete($item->image_path);
        }
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

        return Str::of($request->string('url'))->trim()->toString();
    }

    private function optional(Request $request, string $key): ?string
    {
        $value = $request->string($key)->trim()->toString();

        return $value === '' ? null : $value;
    }

    private function ensureOwned(Request $request, CurrentWishlist $current, WishlistItem $item): void
    {
        abort_unless($current->owns($request, $item->wishlist), 404);
    }
}
