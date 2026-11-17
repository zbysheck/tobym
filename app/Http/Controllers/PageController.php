<?php

namespace App\Http\Controllers;

use App\Support\CurrentWishlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    /**
     * Strona główna: kto ma już listę, trafia prosto do niej, a nowi widzą stronę startową.
     */
    public function home(Request $request, CurrentWishlist $current): Response|RedirectResponse
    {
        $wishlist = $current->find($request);

        if ($wishlist !== null) {
            return redirect()->route('wishlist.show', $wishlist);
        }

        return $this->landing(null);
    }

    public function about(Request $request, CurrentWishlist $current): Response
    {
        $wishlist = $current->find($request);

        return $this->landing($wishlist === null ? null : route('wishlist.show', $wishlist));
    }

    public function privacy(): Response
    {
        return Inertia::render('Privacy', [
            'controller' => config('tobym.privacy_controller'),
            'contact' => config('tobym.privacy_contact'),
            'updated' => '17 listopada 2026',
        ])->withViewData(['indexable' => true]);
    }

    private function landing(?string $listUrl): Response
    {
        return Inertia::render('Landing', ['listUrl' => $listUrl])
            ->withViewData([
                'indexable' => true,
                'og' => [
                    'title' => 'tobym: listy prezentów, z których da się kupić',
                    'description' => 'Wklej link z dowolnego sklepu, wyślij listę bliskim, a oni zarezerwują prezenty. Bez rejestracji, za darmo.',
                    'image' => url('/icon-512.png'),
                    'url' => url('/'),
                ],
            ]);
    }
}
