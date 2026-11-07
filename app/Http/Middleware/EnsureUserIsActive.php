<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\SubmissionWindowNotificationService;
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
                ->withErrors(['username' => 'This account is not active. Contact the system administrator.']);
        }

        if ($user) {
            abort_unless($user->hasValidPortalRole() || $request->routeIs('profile.show', 'profile.password.*'), 403, 'Your account role or school assignment needs administrator correction.');
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
            app(SubmissionWindowNotificationService::class)->syncFor($user);
        }

        return $next($request);
    }
}
