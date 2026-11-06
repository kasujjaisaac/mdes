<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'This account is not active. Contact the system administrator.']);
        }

        if ($user) {
            $key = 'auth_session_version.'.$user->id;
            if (Auth::viaRemember() && ! $request->session()->has($key)) {
                $request->session()->put($key, (int) $user->session_version);
            }
            if ((int) $request->session()->get($key, 0) !== (int) $user->session_version) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['username' => 'Your account access changed. Please sign in again.']);
            }
            $request->session()->put($key, (int) $user->session_version);
        }

        return $next($request);
    }
}
