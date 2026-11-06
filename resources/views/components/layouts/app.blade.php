<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preload" href="{{ asset('fonts/poppins-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('fonts/poppins.css') }}">
    <link rel="icon" type="image/jpeg" href="{{ asset('images/system-logo.jpg') }}">
    <title>{{ $title ?? 'MDES' }} | {{ config('mdsiss.name') }}</title>
    @if (app()->environment('testing') || (! file_exists(public_path('build/manifest.json')) && ! file_exists(public_path('hot'))))
        @php
            $fallbackCss = file_get_contents(resource_path('css/app.css'))."\n".file_get_contents(resource_path('css/system-theme.css'))."\n".file_get_contents(resource_path('css/mobile.css'));
            $fallbackCss = preg_replace('/^@(?:import|source).*$/m', '', $fallbackCss);
            $fallbackCss = preg_replace('/@theme\s*\{.*?\}\s*/s', '', $fallbackCss);
        @endphp
        <style>{!! $fallbackCss !!}</style>
        <script type="module">{!! file_get_contents(resource_path('js/app.js')) !!}</script>
    @else
        @vite(['resources/css/app.css', 'resources/css/system-theme.css', 'resources/css/mobile.css', 'resources/js/app.js'])
    @endif
</head>
<body class="app-body system-theme mobile-portal {{ auth()->user()->hasRole(\App\Models\Role::SUPER_ADMINISTRATOR) ? 'admin-interface' : '' }} {{ auth()->user()->isSchoolRepresentative() ? 'representative-client' : '' }}">
    @php($navigationGroups = \App\Support\Navigation::groups())
    @php($unreadAlerts = \App\Models\NotificationRecord::where('user_id',auth()->id())->whereNull('read_at')->count())

    <a class="portal-skip-link" href="#main-content">Skip to main content</a>
    <div class="app-shell" data-shell>

        <header class="topbar">
            @include('components.layouts.client-navigation')


                <a class="reference-brand" href="{{ route('dashboard') }}"><img class="system-logo" src="{{ asset('images/system-logo.jpg') }}" alt="Masaka Diocese crest" width="44" height="38"><span><strong>MDES</strong><small>{{ config('mdsiss.name') }}</small></span></a>
            <div class="topbar-left">
                <div class="topbar-title">
                    <span>{{ config('mdsiss.name') }}</span>
                    <strong>{{ $title ?? 'Dashboard' }}</strong>
                </div>
            </div>

            @if(auth()->user()->isSchoolRepresentative())
            <div class="client-mobile-announcement">@include('representative.window-strip')</div>
            @endif
            @isset($topbarFilters)
                {{ $topbarFilters }}
            @endisset
            <div class="topbar-actions"><a class="topbar-alert-link" href="{{ route('alerts.index') }}" aria-label="Inbox: {{ $unreadAlerts }} unread"><x-ui.nav-icon name="bell"/>@if($unreadAlerts)<span>{{ $unreadAlerts }}</span>@endif</a>
                <span class="topbar-date">{{ now()->timezone(config('app.timezone'))->format('l, d M Y') }}</span>
                <div class="profile-menu" data-menu>
                    <button class="profile-button profile-icon-button" type="button" data-menu-toggle aria-expanded="false" aria-haspopup="true" aria-controls="profile-menu-panel" aria-label="Open profile menu" title="Profile menu">
                        <svg class="profile-person-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></svg>
                        <svg class="profile-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m4 6 4 4 4-4"/></svg>
                    </button>
                    <div class="profile-dropdown" data-menu-panel id="profile-menu-panel">
                        <a href="{{ route('profile.show') }}">profile</a>
                        @can('settings.view')<a href="{{ route('settings.index') }}">settings</a>@endcan
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit">sign out</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <aside class="app-sidebar" aria-label="Primary navigation">
            <div class="sidebar-brand">
                <img class="system-logo" src="{{ asset('images/system-logo.jpg') }}" alt="Masaka Diocese crest" width="44" height="38">
                <div class="brand-text">
                    <strong>MDES</strong>
                    <span>{{ config('mdsiss.name') }}</span>
                </div>
            </div>

            <nav class="sidebar-nav" aria-label="Main menu">
                <?php
                    $visibleItems = collect($navigationGroups)->flatMap(fn($group) => $group['items'])->filter(fn($item) => ! $item['permission'] || auth()->user()->can($item['permission']));
                    $activeUrl = $visibleItems->pluck('url')->filter(fn($url) => request()->url() === $url || str_starts_with(request()->url(), $url.'/'))->sortByDesc(fn($url) => strlen($url))->first();
                ?>
                <ul class="sidebar-menu-list">
                    @foreach($visibleItems->reject(fn($item)=>in_array($item['key'],['users','change-password','reporting-periods'])) as $item)
                        <li><a href="{{ $item['url'] }}" class="nav-link @if($activeUrl === $item['url']) is-active @endif" title="{{ $item['label'] }}" @if($activeUrl === $item['url']) aria-current="page" @endif><span class="nav-icon"><x-ui.nav-icon :name="$item['icon']" /></span><span class="nav-label">{{ $item['label'] }}</span>@if($item['key'] === 'announcements' && $unreadAlerts)<span class="alerts-count">{{ $unreadAlerts }}</span>@endif</a></li>
                    @endforeach
                    <li>@include('components.layouts.settings-menu',['mobile'=>false])</li>
                    @if(auth()->user()->isSchoolRepresentative())<li><a href="{{ route('support.index') }}" class="nav-link {{ request()->routeIs('support.*') ? 'is-active' : '' }}" @if(request()->routeIs('support.*')) aria-current="page" @endif><span class="nav-icon"><x-ui.nav-icon name="help"/></span><span class="nav-label">Help &amp; Support</span></a></li>@endif
                </ul>
            </nav>
            <form method="POST" action="{{ route('logout') }}" class="sidebar-logout">
                @csrf
                <button type="submit" class="sidebar-logout-button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M9 4H4v16h5M14 8l4 4-4 4M8 12h12"/></svg><span>Log out</span></button>
            </form>
        </aside>

        <div class="app-main">
            @include('representative.window-strip')
            <main class="content" id="main-content" tabindex="-1">
                @unless($hideHeading ?? false)
                    <div class="page-heading">
                        <div>
                            <x-ui.breadcrumb :items="$breadcrumbs ?? [['label' => $title ?? 'Dashboard']]" />
                            <h1>{{ $title ?? 'Dashboard' }}</h1>
                        </div>

                        @isset($actions)
                            <x-ui.page-actions :actions="$actions" />
                        @endisset
                    </div>
                @endunless

                @if (session('status'))
                    <x-ui.alert tone="success" :message="session('status')" />
                @endif

                @if($errors->any() && request()->routeIs('schools.index','parishes.index','reporting-periods.index','administration.users.index','dashboard','returns.index'))
                    <div class="portal-filter-errors" role="alert"><strong>Please check your filters.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                {{ $slot }}
                
            </main>
            <footer class="workspace-footer"><span>MDES &middot; {{ config('mdsiss.name') }}</span></footer>
        </div>
    </div>


<button type="button" class="whatsapp-support-float support-launcher" data-open-whatsapp-support aria-label="Get support" title="Get support"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 14v-3a8 8 0 0 1 16 0v3M4 12H3v6h4v-6H4ZM20 12h1v6h-4v-6h3ZM20 18v1a3 3 0 0 1-3 3h-3"/><path d="M11 21h4"/></svg></button>
<dialog class="whatsapp-issue-dialog" data-whatsapp-support-dialog aria-labelledby="support-issue-title"><header><h2 id="support-issue-title">How can we help?</h2><button type="button" data-close-whatsapp-support aria-label="Close support">&times;</button></header><form data-whatsapp-issue-form data-school="{{ auth()->user()->school?->official_name ?? '' }}"><p>Describe the issue you are experiencing so our support team can assist you.</p><label for="whatsapp-support-issue">Your issue</label><textarea id="whatsapp-support-issue" name="issue" rows="4" required maxlength="2000" placeholder="Tell us what happened and where you need help."></textarea><div class="form-actions"><button class="button" type="button" data-close-whatsapp-support>Cancel</button><button class="button button-primary" type="submit">Submit</button></div></form></dialog>

@include('announcements.required-popup')
</body>
</html>
