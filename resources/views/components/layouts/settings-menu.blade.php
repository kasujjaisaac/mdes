@php
$settingsItems = collect($navigationGroups)->firstWhere('label','Settings')['items'] ?? [];
$settingsItems = collect($settingsItems)->filter(fn($item)=>!$item['permission'] || auth()->user()->can($item['permission']));
$settingsActive = $settingsItems->contains(fn($item)=>request()->url()===$item['url'] || str_starts_with(request()->url(),$item['url'].'/'));
@endphp
@if($settingsItems->isNotEmpty())
<details class="settings-menu {{ $mobile ? 'settings-menu-mobile' : 'settings-menu-desktop' }}" @if($settingsActive) open @endif>
<summary class="{{ $mobile ? 'mobile-settings-toggle' : 'nav-link' }}"><span class="settings-toggle-row"><span class="nav-icon"><x-ui.nav-icon name="settings"/></span><span class="nav-label">Settings</span><svg class="settings-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m5 3 5 5-5 5"/></svg></span></summary>
<ul class="settings-submenu">@foreach($settingsItems as $item)@php($active=request()->url()===$item['url'] || str_starts_with(request()->url(),$item['url'].'/'))<li><a href="{{ $item['url'] }}" class="{{ $mobile ? '' : 'nav-link' }} {{ $active ? 'is-active' : '' }}" @if($active) aria-current="page" @endif><span class="nav-icon"><x-ui.nav-icon :name="$item['icon']"/></span><span class="nav-label">{{ $item['label'] }}</span></a></li>@endforeach</ul>
</details>
@endif
