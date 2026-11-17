<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Podmienia zalogowanego admina na oglądaną osobę na czas jednego żądania.
 * Panel /admin i wyjście z podglądu zawsze działają jako admin.
 */
class ApplyImpersonation
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $data = $request->session()->get(Impersonation::SESSION);

        if (! is_array($data)) {
            return $next($request);
        }

        // Prawdziwy zalogowany użytkownik z sesji (nie ten podmieniony na czas żądania).
        $sessionUserId = $request->session()->get(Auth::guard('web')->getName());
        $admin = is_int($sessionUserId) || is_string($sessionUserId) ? User::query()->find($sessionUserId) : null;

        if (! $admin instanceof User || ! $admin->isAdmin()) {
            Impersonation::stop($request);

            return $next($request);
        }

        Auth::setUser($admin);

        if ($request->is('admin', 'admin/*')) {
            return $next($request);
        }

        $request->attributes->set(Impersonation::ADMIN, $admin);

        $target = is_int($data['user_id'] ?? null) ? User::query()->find($data['user_id']) : null;

        if ($target instanceof User) {
            Auth::setUser($target);
        } else {
            $request->attributes->set(Impersonation::DRAFT, is_string($data['draft_hash'] ?? null) ? $data['draft_hash'] : '');
        }

        return $next($request);
    }
}
