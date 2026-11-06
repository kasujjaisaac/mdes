<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');
        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Try again in {$seconds} seconds.",
            ]);
        }

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ])->redirectTo(route('login'));
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::logout();
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'This account is not active. Contact the system administrator.',
            ])->redirectTo(route('login'));
        }

        $user->forceFill(['last_login_at' => now()])->save();

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return $user->isSchoolRepresentative()
            ? redirect()->route('representative.home')
            : redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out.');
    }

    private function throttleKey(Request $request): string
    {
        return strtolower((string) $request->input('email')).'|'.$request->ip();
    }
}
