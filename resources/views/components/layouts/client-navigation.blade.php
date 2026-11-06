            <details class="client-header-menu">
                <summary aria-label="Open school portal navigation"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></summary>
                <nav aria-label="School portal navigation">
                    <header><span>{{ auth()->user()->isSchoolRepresentative() ? 'SCHOOL PORTAL' : 'MDES PORTAL' }}</span><strong>{{ auth()->user()->name }}</strong></header>
                    @if(auth()->user()->isSchoolRepresentative())
                    @foreach([
                        ['label'=>'Dashboard','icon'=>'dashboard','route'=>'representative.home','active'=>request()->routeIs('dashboard','representative.home')],
                        ['label'=>'School Profile','icon'=>'school','route'=>'representative.profile','active'=>request()->routeIs('representative.profile*')],
                        ['label'=>'My Submissions','icon'=>'clipboard','route'=>'returns.index','active'=>request()->routeIs('returns.*','representative.confirm*')],
                        ['label'=>'School Alerts','icon'=>'bell','route'=>'alerts.index','active'=>request()->routeIs('alerts.*')],
                        ['label'=>'My Profile','icon'=>'users','route'=>'profile.show','active'=>request()->routeIs('profile.show')],
                        ['label'=>'Help & Support','icon'=>'help','route'=>'support.index','active'=>request()->routeIs('support.*')]
                    ] as $link)
                    <a href="{{ route($link['route']) }}" @if($link['active']) aria-current="page" @endif><x-ui.nav-icon :name="$link['icon']"/><span>{{ $link['label'] }}</span><span class="client-menu-arrow" aria-hidden="true">&rsaquo;</span></a>
                    @endforeach
                    @else
                    @foreach(collect(\App\Support\Navigation::groups())->flatMap(fn($group) => $group['items'])->filter(fn($item) => !$item['permission'] || auth()->user()->can($item['permission'])) as $item)
                    <a href="{{ $item['url'] }}" @if(request()->url() === $item['url'] || str_starts_with(request()->url(),$item['url'].'/')) aria-current="page" @endif><x-ui.nav-icon :name="$item['icon']"/><span>{{ $item['label'] }}</span><span class="client-menu-arrow" aria-hidden="true">&rsaquo;</span></a>
                    @endforeach
                    <a href="{{ route('profile.show') }}" @if(request()->routeIs('profile.show')) aria-current="page" @endif><x-ui.nav-icon name="users"/><span>My Profile</span><span class="client-menu-arrow" aria-hidden="true">&rsaquo;</span></a>
                    <a href="{{ route('support.index') }}" @if(request()->routeIs('support.*')) aria-current="page" @endif><x-ui.nav-icon name="help"/><span>Help &amp; Support</span><span class="client-menu-arrow" aria-hidden="true">&rsaquo;</span></a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-ui.nav-icon name="shield"/>Sign out</button></form>
                </nav>
            </details>
