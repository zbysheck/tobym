<?php

namespace App\Http\Controllers;

use App\Mail\LoginLinkMail;
use App\Models\AddonToken;
use App\Models\User;
use App\Models\Wishlist;
use App\Support\CurrentWishlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LoginLinkController extends Controller
{
    private const LINK_MINUTES = 30;

    public function store(Request $request, CurrentWishlist $current): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
        ], [
            'email.required' => 'Podaj adres e-mail.',
            'email.email' => 'To nie wygląda na adres e-mail.',
        ]);

        $email = $request->string('email')->trim()->lower()->toString();

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => Str::before($email, '@'), 'password' => Str::random(40)],
        );

        // Skrót tokenu szkiców jedzie w podpisanym linku, żeby listy trafiły na konto
        // także wtedy, gdy mail zostanie otwarty w innej przeglądarce.
        // "n" czyni link jednorazowym: po użyciu trafia do cache i drugi raz nie zadziała.
        $parameters = ['user' => $user->id, 'n' => Str::random(20)];
        $draftHash = $current->draftHash($request);

        if ($draftHash !== null) {
            $parameters['d'] = $draftHash;
        }

        $url = URL::temporarySignedRoute('login.verify', now()->addMinutes(self::LINK_MINUTES), $parameters);

        Mail::to($user->email)->send(new LoginLinkMail($url, self::LINK_MINUTES));

        return back()->with('status', 'Wysłaliśmy link na '.$email.'. Kliknij go, aby zapisać listę.');
    }

    /**
     * Link z maila otwiera najpierw stronę z przyciskiem. Skanery poczty, które same otwierają
     * linki, nie zużyją więc jednorazowego logowania.
     */
    public function confirm(Request $request): Response
    {
        return Inertia::render('ConfirmLogin', ['action' => $request->fullUrl()]);
    }

    public function verify(Request $request, CurrentWishlist $current, User $user): RedirectResponse
    {
        $nonce = $request->string('n')->toString();

        if ($nonce === '' || ! Cache::add('login-link:'.$nonce, true, now()->addMinutes(self::LINK_MINUTES + 1))) {
            return redirect()->route('home')->with('status', 'Ten link został już użyty. Podaj maila jeszcze raz, wyślemy nowy.');
        }

        $draftHashes = array_values(array_filter([$request->string('d')->toString(), $current->draftHash($request)]));

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        if ($draftHashes !== []) {
            Wishlist::query()
                ->whereNull('user_id')
                ->whereIn('draft_token', $draftHashes)
                ->update(['user_id' => $user->id, 'draft_token' => null]);

            // Addon połączony jeszcze ze szkicem przechodzi razem z listami na konto.
            AddonToken::query()
                ->whereIn('draft_hash', $draftHashes)
                ->update(['user_id' => $user->id, 'draft_hash' => null]);
        }

        Cookie::queue(Cookie::forget(CurrentWishlist::COOKIE));

        return redirect()->route('home')->with('status', 'Lista jest zapisana na Twoim koncie.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
