<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Anonimowy identyfikator gościa w przeglądarce, żeby mógł cofnąć własną rezerwację bez konta.
 */
class GuestToken
{
    public const COOKIE = 'tobym_guest';

    public function hash(Request $request): ?string
    {
        $token = $request->cookie(self::COOKIE);

        return is_string($token) && $token !== '' ? hash('sha256', $token) : null;
    }

    public function hashOrCreate(Request $request): string
    {
        $existing = $this->hash($request);

        if ($existing !== null) {
            return $existing;
        }

        $token = Str::random(40);
        Cookie::queue(self::COOKIE, $token, 60 * 24 * 365);

        return hash('sha256', $token);
    }
}
