<?php

namespace App\Http\Middleware;

use App\Models\Wishlist;
use App\Support\CurrentWishlist;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            // Czy odwiedzający ma jakąkolwiek listę (link "Moje listy" w nagłówku).
            'hasLists' => fn () => app(CurrentWishlist::class)->query($request)?->exists() ?? false,
            // Listy odwiedzającego do wyboru przy dodawaniu prezentu (wklejanie linku działa na każdej stronie).
            'myLists' => fn () => app(CurrentWishlist::class)->all($request)
                ->map(fn (Wishlist $list): array => ['slug' => $list->slug, 'title' => $list->title])
                ->values()
                ->all(),
            'flash' => [
                'status' => $request->session()->get('status'),
            ],
        ];
    }
}
