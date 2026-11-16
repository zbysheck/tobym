<?php

namespace App\Support;

use App\Enums\Theme;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Ustala, na której liście pracuje bieżący odwiedzający:
 * zalogowany na swojej, niezalogowany na szkicu przypiętym do przeglądarki.
 */
class CurrentWishlist
{
    public const COOKIE = 'tobym_draft';

    private const COOKIE_MINUTES = 60 * 24 * 365;

    public function find(Request $request): ?Wishlist
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $user->wishlists()->latest('id')->first();
        }

        return $this->draft($request);
    }

    public function draft(Request $request): ?Wishlist
    {
        $token = $request->cookie(self::COOKIE);

        if (! is_string($token) || $token === '') {
            return null;
        }

        return Wishlist::query()
            ->whereNull('user_id')
            ->where('draft_token', hash('sha256', $token))
            ->first();
    }

    public function findOrCreate(Request $request): Wishlist
    {
        $existing = $this->find($request);

        if ($existing !== null) {
            return $existing;
        }

        $attributes = [
            'title' => Wishlist::DEFAULT_TITLE,
            'theme' => Theme::Swiateczna,
        ];

        $user = $request->user();

        if ($user instanceof User) {
            return $user->wishlists()->create($attributes);
        }

        $token = Str::random(40);

        Cookie::queue(self::COOKIE, $token, self::COOKIE_MINUTES);

        return Wishlist::query()->create([
            ...$attributes,
            'draft_token' => hash('sha256', $token),
        ]);
    }
}
