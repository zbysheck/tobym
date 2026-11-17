<?php

namespace App\Http\Controllers;

use App\Enums\Layout;
use App\Enums\Theme;
use App\Models\AddonToken;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\ProductExtractor;
use App\Support\CurrentWishlist;
use App\Support\Impersonation;
use App\Support\Urls;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Addon Chrome: łączenie z kontem/szkicem i dodawanie prezentów z HTML-u strony,
 * którą użytkownik ma otwartą (działa też dla sklepów blokujących serwer).
 */
class AddonController extends Controller
{
    public function connectPage(): Response
    {
        return Inertia::render('AddonConnect', ['token' => session('addon_token')]);
    }

    public function connect(Request $request, CurrentWishlist $current): RedirectResponse
    {
        abort_if(Impersonation::admin($request) !== null, 403, 'Tego nie da się zrobić w podglądzie „jako”.');
        $user = $request->user();
        $draftHash = null;

        if (! $user instanceof User) {
            $draftHash = $current->draftHash($request);

            if ($draftHash === null) {
                $token = Str::random(40);
                Cookie::queue(CurrentWishlist::COOKIE, $token, 60 * 24 * 365);
                $draftHash = hash('sha256', $token);
            }
        }

        $plain = Str::random(48);

        AddonToken::query()->create([
            'token_hash' => hash('sha256', $plain),
            'user_id' => $user instanceof User ? $user->id : null,
            'draft_hash' => $draftHash,
        ]);

        // Token pokazujemy tylko raz; addon odczytuje go ze strony i zapamiętuje.
        return redirect()->route('addon.connect')->with('addon_token', $plain);
    }

    public function lists(Request $request): JsonResponse
    {
        $token = $this->token($request);

        return response()->json([
            'lists' => $token->wishlists()->latest('id')->get(['slug', 'title'])
                ->map(fn (Wishlist $list): array => ['slug' => $list->slug, 'title' => $list->title])
                ->values(),
        ]);
    }

    public function store(Request $request, ProductExtractor $extractor): JsonResponse
    {
        $token = $this->token($request);

        $request->validate([
            'url' => ['required', 'string', 'url:http,https', 'max:2000'],
            'html' => ['nullable', 'string'],
            'list' => ['nullable', 'string', 'max:16'],
        ]);

        $url = $request->string('url')->trim()->toString();
        $html = mb_strcut($request->string('html')->toString(), 0, 3_000_000, 'UTF-8');
        $slug = $request->string('list')->toString();
        set_time_limit(150);

        $fields = $html === ''
            ? ['title' => null, 'shop' => null, 'image' => null, 'price' => null]
            : $extractor->extract($html, $url);
        $host = Urls::host($url);

        $wishlist = $slug !== ''
            ? $token->wishlists()->where('slug', $slug)->first()
            : $token->wishlists()->latest('id')->first();

        $wishlist ??= Wishlist::query()->create([
            'title' => Wishlist::DEFAULT_TITLE,
            'theme' => Theme::Swiateczna,
            'layout' => Layout::Tiles,
            'user_id' => $token->user_id,
            'draft_token' => $token->user_id === null ? $token->draft_hash : null,
        ]);

        $item = $wishlist->items()->create([
            'url' => $url,
            'title' => $fields['title'] ?? $host ?? 'Prezent',
            'shop' => $fields['shop'] ?? $host,
            'image_url' => $fields['image'],
            'price' => $fields['price'],
            'position' => ((int) $wishlist->items()->max('position')) + 1,
        ]);

        return response()->json([
            'title' => $item->title,
            'price' => $item->price,
            'image_url' => $item->image_url,
            'list' => ['slug' => $wishlist->slug, 'title' => $wishlist->title, 'url' => route('wishlist.show', $wishlist)],
        ], 201);
    }

    private function token(Request $request): AddonToken
    {
        $plain = $request->bearerToken();
        $token = $plain === null ? null : AddonToken::query()->where('token_hash', hash('sha256', $plain))->first();

        abort_if($token === null, 401, 'Połącz addon z Tobym jeszcze raz.');

        $token->forceFill(['last_used_at' => now()])->save();

        return $token;
    }
}
