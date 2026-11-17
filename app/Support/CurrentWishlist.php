<?php

namespace App\Support;

use App\Enums\Layout;
use App\Enums\Theme;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Ustala, do kogo należą listy: zalogowany ma swoje listy na koncie,
 * niezalogowany ma szkice przypięte do przeglądarki ciasteczkiem.
 */
class CurrentWishlist
{
    public const COOKIE = 'tobym_draft';

    private const COOKIE_MINUTES = 60 * 24 * 365;

    /**
     * @return Builder<Wishlist>|null
     */
    public function query(Request $request): ?Builder
    {
        $user = $request->user();

        if ($user instanceof User) {
            return Wishlist::query()->where('user_id', $user->id);
        }

        $hash = $this->draftHash($request);

        return $hash === null ? null : Wishlist::query()->whereNull('user_id')->where('draft_token', $hash);
    }

    /**
     * @return Collection<int, Wishlist>
     */
    public function all(Request $request): Collection
    {
        return $this->query($request)?->latest('id')->get() ?? new Collection;
    }

    public function find(Request $request): ?Wishlist
    {
        return $this->query($request)?->latest('id')->first();
    }

    public function owns(Request $request, Wishlist $wishlist): bool
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $wishlist->user_id === $user->id;
        }

        $hash = $this->draftHash($request);

        return $wishlist->user_id === null
            && $hash !== null
            && $wishlist->draft_token !== null
            && hash_equals($wishlist->draft_token, $hash);
    }

    public function draftHash(Request $request): ?string
    {
        $token = $request->cookie(self::COOKIE);

        return is_string($token) && $token !== '' ? hash('sha256', $token) : null;
    }

    public function findOrCreate(Request $request): Wishlist
    {
        return $this->find($request) ?? $this->create($request);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Request $request, array $attributes = []): Wishlist
    {
        $attributes = [
            'title' => Wishlist::DEFAULT_TITLE,
            'theme' => Theme::Swiateczna,
            'layout' => Layout::Tiles,
            ...$attributes,
        ];

        $user = $request->user();

        if ($user instanceof User) {
            return $user->wishlists()->create($attributes);
        }

        $hash = $this->draftHash($request);

        if ($hash === null) {
            $token = Str::random(40);
            Cookie::queue(self::COOKIE, $token, self::COOKIE_MINUTES);
            $hash = hash('sha256', $token);
        }

        return Wishlist::query()->create([...$attributes, 'draft_token' => $hash]);
    }
}
