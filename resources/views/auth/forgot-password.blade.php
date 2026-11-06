<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="icon" href="{{ asset('images/system-logo.jpg') }}"><title>Reset password | {{ config('mdsiss.name') }}</title>@include('auth.partials.styles')</head><body><main class="login-panel"><img class="login-logo" src="{{ asset('images/system-logo.jpg') }}" alt="Masaka Diocese crest" width="100" height="100"><h1 class="login-welcome">Forgot password?</h1><p class="login-subtitle">Enter your registered email to receive a reset link.</p>
@if(session('status'))
<p class="login-message" role="status">{{ session('status') }}</p>
@endif
@if($errors->any())
<p class="login-message login-error" role="alert">{{ $errors->first() }}</p>
@endif
<form class="login-form" method="POST" action="{{ route('password.email') }}">@csrf<div class="field"><label for="email">Registered email address</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus placeholder="Enter your registered email"></div><button class="login-button">Send reset link</button><a class="forgot-link" href="{{ route('login') }}">Back to login</a></form></main></body></html>
