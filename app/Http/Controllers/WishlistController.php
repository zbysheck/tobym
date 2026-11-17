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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WishlistController extends Controller
{
    private const UNLOCKED = 'unlocked_lists';

    /**
     * Pusta lista gotowa do dodania pierwszego prezentu (lista powstaje przy pierwszej zmianie).
     * Kto ma już listę, trafia do niej.
     */
    public function start(Request $request, CurrentWishlist $current): Response|RedirectResponse
    {
        $wishlist = $current->find($request);

        if ($wishlist !== null) {
            return redirect()->route('wishlist.show', $wishlist);
        }

        return $this->render($request, $current, null, editable: true, guestToken: null);
    }

    public function store(Request $request, CurrentWishlist $current): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'theme' => ['nullable', Rule::enum(Theme::class)],
            'event_date' => ['nullable', 'date'],
        ]);

        $wishlist = $current->create($request, array_filter([
            'title' => $data['title'] ?? 'Nowa lista',
            'theme' => $data['theme'] ?? null,
            'event_date' => $data['event_date'] ?? null,
        ]));

        return redirect()->route('wishlist.show', $wishlist);
    }

    public function show(Request $request, CurrentWishlist $current, GuestToken $guest, Wishlist $wishlist): Response
    {
        $editable = $current->owns($request, $wishlist);

        if (! $editable && $wishlist->pin_hash !== null && ! $this->unlocked($request, $wishlist)) {
            return Inertia::render('LockedList', [
                'slug' => $wishlist->slug,
                'title' => $wishlist->title,
                'theme' => $wishlist->theme->value,
                'accent' => $wishlist->accent,
            ]);
        }

        return $this->render($request, $current, $wishlist, $editable, $editable ? null : $guest->hash($request));
    }

    public function unlock(Request $request, Wishlist $wishlist): RedirectResponse
    {
        $pin = $request->string('pin')->toString();

        if ($wishlist->pin_hash === null || ! Hash::check($pin, $wishlist->pin_hash)) {
            return back()->withErrors(['pin' => 'Nieprawidłowy PIN.']);
        }

        $request->session()->push(self::UNLOCKED, $wishlist->id);

        return redirect()->route('wishlist.show', $wishlist);
    }

    public function update(Request $request, CurrentWishlist $current, Wishlist $wishlist): RedirectResponse
    {
        abort_unless($current->owns($request, $wishlist), 404);

        $this->applyUpdate($request, $wishlist);

        return redirect()->route('wishlist.show', $wishlist);
    }

    /**
     * Zmiana na stronie głównej, zanim lista ma adres (tworzy ją przy pierwszej zmianie).
     */
    public function updateCurrent(Request $request, CurrentWishlist $current): RedirectResponse
    {
        $wishlist = $current->findOrCreate($request);
        $this->applyUpdate($request, $wishlist);

        return redirect()->route('wishlist.show', $wishlist);
    }

    public function destroy(Request $request, CurrentWishlist $current, Wishlist $wishlist): RedirectResponse
    {
        abort_unless($current->owns($request, $wishlist), 404);

        foreach ($wishlist->items()->whereNotNull('image_path')->pluck('image_path') as $path) {
            Storage::disk('public')->delete((string) $path);
        }

        $wishlist->delete();

        return redirect()->route('home')->with('status', 'Lista „'.$wishlist->title.'” została usunięta.');
    }

    private function applyUpdate(Request $request, Wishlist $wishlist): void
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'theme' => ['sometimes', 'required', Rule::enum(Theme::class)],
            'layout' => ['sometimes', 'required', Rule::enum(Layout::class)],
            'event_date' => ['sometimes', 'nullable', 'date'],
            'accent' => ['sometimes', 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'pin' => ['sometimes', 'nullable', 'digits_between:4,8'],
        ], [
            'pin.digits_between' => 'PIN to od 4 do 8 cyfr.',
        ]);

        if (array_key_exists('pin', $data)) {
            $data['pin_hash'] = $data['pin'] === null ? null : Hash::make((string) $data['pin']);
            unset($data['pin']);
        }

        $wishlist->update($data);
    }

    private function unlocked(Request $request, Wishlist $wishlist): bool
    {
        $unlocked = $request->session()->get(self::UNLOCKED, []);

        return is_array($unlocked) && in_array($wishlist->id, $unlocked, true);
    }

    /**
     * Właściciel (editable) nigdy nie dostaje informacji o rezerwacjach, żeby prezenty były niespodzianką.
     */
    private function render(Request $request, CurrentWishlist $current, ?Wishlist $wishlist, bool $editable, ?string $guestToken): Response
    {
        $wishlist?->load('items.reservation');
        $items = $wishlist === null ? [] : $wishlist->items
            ->map(fn (WishlistItem $item): array => [
                'id' => $item->id,
                'url' => $item->url,
                'title' => $item->title,
                'shop' => $item->shop,
                'image_url' => $item->image_url,
                'image_large' => $item->image_path !== null ? $item->image_url : ($item->image_source ?? $item->image_url),
                'price' => $item->price,
                'note' => $item->note,
                'priority' => $item->priority,
                'reservation' => $editable || $item->reservation === null ? null : [
                    'name' => $item->reservation->name,
                    'mine' => $guestToken !== null && hash_equals($item->reservation->guest_token, $guestToken),
                ],
            ])
            ->values()
            ->all();

        $today = now()->startOfDay();

        return Inertia::render('Wishlist', [
            'editable' => $editable,
            'slug' => $wishlist?->slug,
            'shareUrl' => $wishlist === null ? null : route('wishlist.show', $wishlist),
            'wishlist' => [
                'title' => $wishlist->title ?? Wishlist::DEFAULT_TITLE,
                'theme' => ($wishlist->theme ?? Theme::Swiateczna)->value,
                'layout' => ($wishlist->layout ?? Layout::Tiles)->value,
                'event_date' => $wishlist?->event_date?->toDateString(),
                'accent' => $wishlist?->accent,
                'has_pin' => $wishlist?->pin_hash !== null,
                'items' => $items,
            ],
            'lists' => $editable ? $current->all($request)
                ->map(fn (Wishlist $list): array => [
                    'slug' => $list->slug,
                    'title' => $list->title,
                    'past' => $list->event_date !== null && $list->event_date->lt($today),
                ])
                ->values()
                ->all() : [],
            'themes' => array_map(
                fn (Theme $theme): array => ['value' => $theme->value, 'label' => $theme->label()],
                Theme::cases(),
            ),
        ])->withViewData(['og' => $this->openGraph($wishlist)]);
    }

    /**
     * Podgląd linku w komunikatorach (Messenger, WhatsApp): tytuł listy i zdjęcie pierwszego prezentu.
     *
     * @return array{title: string, description: string, image: string|null, url: string}|null
     */
    private function openGraph(?Wishlist $wishlist): ?array
    {
        if ($wishlist === null) {
            return null;
        }

        $image = $wishlist->items->first(fn (WishlistItem $item): bool => $item->image_url !== null)?->image_url;
        $count = $wishlist->items->count();

        return [
            'title' => $wishlist->title,
            'description' => $count === 0 ? 'Lista prezentów' : 'Lista prezentów · pozycji: '.$count,
            'image' => $image === null ? null : url($image),
            'url' => route('wishlist.show', $wishlist),
        ];
    }
}
