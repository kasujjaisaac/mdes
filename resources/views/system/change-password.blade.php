<x-layouts.app :title="$title" :hide-heading="true">
<section class="account-password-page"><header><span class="diocesan-eyebrow">Settings</span><h1>Change password</h1><p>Update the password for your own account.</p></header>
<form method="POST" action="{{ route('profile.password.update') }}">@csrf @method('PUT')
@if($errors->any())<div class="account-password-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<label for="current-password">Current password<input id="current-password" type="password" name="current_password" autocomplete="current-password" required autofocus></label>
<label for="new-password">New password<input id="new-password" type="password" name="password" autocomplete="new-password" minlength="12" required><small>{{ auth()->user()->isSchoolRepresentative() ? 'Use at least 12 characters, with uppercase and lowercase letters, a number and a symbol.' : 'Use at least 12 characters.' }}</small></label>
<label for="confirm-password">Confirm new password<input id="confirm-password" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></label>
<button type="submit" class="button button-primary">Update password</button></form></section>
</x-layouts.app>
