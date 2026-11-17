<?php

namespace App\Support;

use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Http\Request;

/**
 * "Wyświetl jako": admin ogląda stronę jako właściciel konta albo szkicu.
 * Sesja admina zostaje nietknięta, podmiana działa tylko na czas pojedynczego żądania.
 */
class Impersonation
{
    public const SESSION = 'tobym.impersonate';

    /** Atrybut żądania z prawdziwym adminem, gdy trwa podgląd "jako". */
    public const ADMIN = 'tobym.admin';

    /** Atrybut żądania z hashem szkicu, którego właściciela udajemy. */
    public const DRAFT = 'tobym.draft';

    public static function start(Request $request, Wishlist $wishlist): string
    {
        $owner = $wishlist->user;
        $label = $owner instanceof User ? $owner->email : 'właściciel szkicu „'.$wishlist->title.'”';

        $request->session()->put(self::SESSION, [
            'user_id' => $owner?->id,
            'draft_hash' => $owner === null ? $wishlist->draft_token : null,
            'label' => $label,
        ]);

        return $label;
    }

    public static function startForUser(Request $request, User $user): void
    {
        $request->session()->put(self::SESSION, [
            'user_id' => $user->id,
            'draft_hash' => null,
            'label' => $user->email,
        ]);
    }

    public static function stop(Request $request): void
    {
        $request->session()->forget(self::SESSION);
    }

    public static function label(Request $request): ?string
    {
        $data = $request->session()->get(self::SESSION);

        return is_array($data) && is_string($data['label'] ?? null) ? $data['label'] : null;
    }

    public static function admin(Request $request): ?User
    {
        $admin = $request->attributes->get(self::ADMIN);

        return $admin instanceof User ? $admin : null;
    }
}
