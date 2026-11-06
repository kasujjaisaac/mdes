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
        $data = $request->validate(['token' => ['required', 'string'], 'email' => ['required', 'email'], 'password' => ['required', 'string', 'min:8', 'confirmed']]);
        $status = Password::reset([...$data, 'status' => User::STATUS_ACTIVE], function (User $user, string $password) {
            DB::transaction(function () use ($user, $password) {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
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
