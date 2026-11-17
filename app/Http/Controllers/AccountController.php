<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AccountController extends Controller
{
    /**
     * Usuwa konto razem ze wszystkimi listami, prezentami, rezerwacjami i wgranymi zdjęciami.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $paths = WishlistItem::query()
            ->whereIn('wishlist_id', $user->wishlists()->select('id'))
            ->whereNotNull('image_path')
            ->pluck('image_path');

        foreach ($paths as $path) {
            Storage::disk('public')->delete((string) $path);
        }

        $user->wishlists()->delete();
        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
