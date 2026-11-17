<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WishlistItem;
use App\Support\ActivityLog;
use App\Support\Impersonation;
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
        abort_if(Impersonation::admin($request) !== null, 403, 'Tego nie da się zrobić w podglądzie „jako”.');
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
        ActivityLog::record('account.deleted', 'Usunięto konto');
        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
