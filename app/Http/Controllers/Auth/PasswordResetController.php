<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetController extends Controller
{
    public function request()
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request)
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
        $request->validate(['email' => ['required', 'email', 'max:255']]);
        if (in_array(config('mail.default'), ['log', 'array']) && ! app()->environment('testing')) {
            throw ValidationException::withMessages(['email' => 'Email delivery is not configured yet. Please contact the administrator.']);
        }
        try {
            Password::sendResetLink(['email' => $request->string('email')->toString(), 'status' => User::STATUS_ACTIVE]);
        } catch (TransportExceptionInterface $exception) {
            report($exception);
            throw ValidationException::withMessages(['email' => 'The email service is temporarily unavailable. Please try again later.']);
        }

        return back()->with('status', 'If this email belongs to an active registered account, a password reset link has been sent. Check your inbox and spam folder.');
    }

    public function edit(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function update(Request $request)
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
        $passwordRule = \Illuminate\Validation\Rules\Password::min(12)->mixedCase()->numbers()->symbols();
        $data = $request->validate(['token' => ['required', 'string', 'max:255'], 'email' => ['required', 'email'], 'password' => ['required', 'string', 'max:4096', 'confirmed', $passwordRule]]);
        $status = Password::reset([...$data, 'status' => User::STATUS_ACTIVE], function (User $user, string $password) use ($data) {
            DB::transaction(function () use ($user, $password, $data) {
                $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                if (! $locked->isActive() || $locked->email !== $user->email || ! Password::broker()->tokenExists($locked, $data['token'])) {
                    throw ValidationException::withMessages(['email' => 'This reset link is no longer valid. Request a new link.']);
                }
                Password::broker()->deleteToken($locked);
                $user->setRawAttributes($locked->getAttributes(), true);
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60), 'must_change_password' => false, 'session_version' => (int) $user->session_version + 1])->save();
                if (config('session.driver') === 'database') {
                    DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                }
            });
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'This reset link is invalid or has expired. Please request a new link.']);
        }

        return redirect()->route('login')->with('status', 'Your password has been reset. Sign in with your new password.');
    }
}
