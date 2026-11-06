<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/jpeg" href="{{ asset('images/system-logo.jpg') }}">
    <title>Login | {{ config('mdsiss.name') }}</title>
    @include('auth.partials.styles')
</head>
<body>
    <main class="login-panel" aria-labelledby="login-title">
        
        <img class="login-logo" src="{{ asset('images/system-logo.jpg') }}" alt="Masaka Diocese crest" width="100" height="100">
        <h1 id="login-title" class="login-welcome">{{ config('mdsiss.name') }}</h1>
        <p class="login-subtitle">Sign in to continue</p>
        @if(session('status'))
            <p class="login-message" role="status">{{ session('status') }}</p>
        @endif
        @if($errors->any())
            <p class="login-message login-error" id="login-error" role="alert">{{ $errors->first() }}</p>
        @endif
        <form class="login-form" method="POST" action="{{ route('login.store') }}">
            @csrf
            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" value="{{ old('username', session('registered_username')) }}" placeholder="Enter your username" autocomplete="username" autofocus required @if($errors->any()) aria-invalid="true" aria-describedby="login-error" @endif>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <x-ui.password-input id="password" name="password" placeholder="Enter your password" autocomplete="current-password" />
            </div>
            <a class="forgot-link" href="{{ route('password.request') }}">Forgot password?</a>
            <button class="login-button" type="submit">Login</button>
        </form>
        <p class="login-subtitle"><a href="{{ route('schools.register') }}">New school? Register your school</a></p>
    </main>
    @include('auth.partials.password-toggle')
</body>
</html>
