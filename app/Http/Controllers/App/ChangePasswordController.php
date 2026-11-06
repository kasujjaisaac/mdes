<?php

namespace App\Http\Controllers\App;

use App\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ChangePasswordController extends Controller
{
    public function edit()
    {

        return view('system.change-password', ['title' => 'Change password']);
    }

    public function update(Request $request, RecordAuditEvent $audit)
    {
        $passwordRule = $request->user()->isSchoolRepresentative() ? Password::min(12)->mixedCase()->numbers()->symbols() : Password::min(12);
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', $passwordRule],
        ]);
        $expectedVersion = (int) $request->user()->session_version;
        $updated = DB::transaction(function () use ($request, $data, $audit, $expectedVersion) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $user->session_version === $expectedVersion, 409, 'Your account changed. Sign in again.');
            $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60), 'must_change_password' => false, 'session_version' => (int) $user->session_version + 1])->save();
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
            }
            $audit->handle($request, 'account.password_changed', $user, null, ['password_changed' => true]);

            return $user;
        });
        $request->user()->setRawAttributes($updated->getAttributes(), true);
        $request->session()->put('auth_session_version.'.$updated->id, (int) $updated->session_version);
        $request->session()->regenerate();

        return redirect()->route('profile.password.edit')->with('status', 'Password changed successfully.');
    }
}
