<?php

namespace App\Http\Controllers\Auth;

use App\Actions\RecordAuditEvent;
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
        $identifier = $request->input('username');
        if (is_string($identifier)) {
            $identifier = User::normalizeUsername($identifier);
        }
        $request->merge(['username' => $identifier]);
        $data = $request->validate(['username' => ['required', 'string', 'max:80', 'not_regex:/@/'], 'password' => ['required', 'string', 'max:4096']]);
        $credentials = ['username' => $data['username'], 'password' => $data['password']];

        $remember = $request->boolean('remember');
        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'username' => "Too many login attempts. Try again in {$seconds} seconds.",
            ]);
        }

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'username' => 'The provided credentials do not match our records.',
            ])->redirectTo(route('login'));
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'username' => 'This account is not active. Contact the system administrator.',
            ])->redirectTo(route('login'));
        }

        $user->forceFill(['last_login_at' => now()])->save();
        app(RecordAuditEvent::class)->handle($request, 'account.login', $user, null, ['login' => true]);

        $request->session()->put('auth_session_version.'.$user->id, (int) $user->session_version);
        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return $user->isSchoolRepresentative()
            ? redirect()->route('representative.home')
            : redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        app(RecordAuditEvent::class)->handle($request, 'account.logout', $request->user(), null, ['logout' => true]);
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out.');
    }

    private function throttleKey(Request $request): string
    {
        return strtolower((string) $request->input('username')).'|'.$request->ip();
    }
}
