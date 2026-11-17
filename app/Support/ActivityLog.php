<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\User;
use App\Models\Wishlist;
use Throwable;

/**
 * Zapis zdarzeń do logu w panelu admina. Błąd zapisu nigdy nie psuje akcji użytkownika.
 */
class ActivityLog
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function record(string $type, string $description, ?Wishlist $wishlist = null, array $meta = []): void
    {
        try {
            $request = request();
            $user = app(CurrentWishlist::class)->user($request);
            $admin = $request->attributes->get(Impersonation::ADMIN);

            Activity::query()->create([
                'type' => $type,
                'user_id' => $user instanceof User ? $user->id : $wishlist?->user_id,
                'actor_id' => $admin instanceof User ? $admin->id : null,
                'wishlist_id' => $wishlist?->id,
                'description' => mb_substr($description, 0, 500),
                'meta' => $meta === [] ? null : $meta,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
