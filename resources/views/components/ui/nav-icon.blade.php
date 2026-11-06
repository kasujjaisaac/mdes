@props(['name' => 'circle'])

@php
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="m9 12 2 2 4-5"></path>',
        'hierarchy' => '<rect x="8" y="3" width="8" height="5"></rect><rect x="3" y="16" width="7" height="5"></rect><rect x="14" y="16" width="7" height="5"></rect><path d="M12 8v4M6.5 16v-4h11v4"></path>',
        'school' => '<path d="m3 10 9-6 9 6"></path><path d="M5 10v10h14V10"></path><path d="M9 20v-6h6v6"></path><path d="M3 20h18"></path>',
        'database' => '<ellipse cx="12" cy="5" rx="8" ry="3"></ellipse><path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"></path><path d="M4 11v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"></path>',
        'calendar' => '<rect x="3" y="4" width="18" height="17" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path>',
        'clipboard' => '<rect x="8" y="2" width="8" height="4" rx="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14H4V6a2 2 0 0 1 2-2h2"></path><path d="M8 12h8M8 16h6"></path>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"></path>',
        'staff' => '<path d="M16 11a4 4 0 1 0-8 0"></path><path d="M4 21a8 8 0 0 1 16 0"></path><path d="M12 11v4M9 15h6"></path>',
        'chart' => '<path d="M3 3v18h18"></path><rect x="7" y="12" width="3" height="5"></rect><rect x="12" y="8" width="3" height="9"></rect><rect x="17" y="5" width="3" height="12"></rect>',
        'building' => '<path d="M4 21V5l8-3 8 3v16"></path><path d="M9 21v-6h6v6M8 8h.01M12 8h.01M16 8h.01M8 12h.01M12 12h.01M16 12h.01"></path>',
        'cross' => '<path d="M10 2h4v6h6v4h-6v10h-4V12H4V8h6z"></path>',
        'checklist' => '<path d="M9 6h12M9 12h12M9 18h12"></path><path d="m3 6 1 1 2-2M3 12l1 1 2-2M3 18l1 1 2-2"></path>',
        'coins' => '<ellipse cx="8" cy="6" rx="5" ry="3"></ellipse><path d="M3 6v8c0 1.66 2.24 3 5 3s5-1.34 5-3V6"></path><path d="M13 9c2.84.16 5 1.43 5 2.96V18c0 1.66-2.24 3-5 3-1.15 0-2.21-.23-3.06-.62"></path>',
        'search' => '<circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path>',
        'target' => '<circle cx="12" cy="12" r="9"></circle><circle cx="12" cy="12" r="5"></circle><circle cx="12" cy="12" r="1"></circle>',
        'tools' => '<path d="M14.7 6.3a4 4 0 0 0-5 5L3 18l3 3 6.7-6.7a4 4 0 0 0 5-5l-2.4 2.4-3-3z"></path>',
        'folder' => '<path d="M3 6h7l2 2h9v10a2 2 0 0 1-2 2H3z"></path>',
        'document' => '<path d="M6 2h9l5 5v15H6z"></path><path d="M14 2v6h6M9 13h6M9 17h6"></path>',
        'message' => '<path d="M21 15a4 4 0 0 1-4 4H7l-4 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"></path>',
        'bell' => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path>',
        'help' => '<circle cx="12" cy="12" r="10"></circle><path d="M9.1 9a3 3 0 1 1 5.8 1c-.8 1.2-2.9 1.4-2.9 3"></path><path d="M12 17h.01"></path>',
        'report' => '<path d="M5 3h14v18H5z"></path><path d="M9 8h6M9 12h6M9 16h4"></path>',
        'analytics' => '<path d="M3 3v18h18"></path><path d="m7 15 3-4 4 3 5-8"></path>',
        'quality' => '<path d="M12 3 4 7v5c0 5 3.5 8.5 8 10 4.5-1.5 8-5 8-10V7z"></path><path d="m8 12 3 3 5-6"></path>',
        'audit' => '<path d="M9 11h6M9 15h6"></path><path d="M5 3h14v18H5z"></path><path d="m15 3 2 4H7l2-4"></path>',
        'settings' => '<circle cx="12" cy="12" r="3"></circle><path d="M12 2v3M12 19v3M4.9 4.9 7 7M17 17l2.1 2.1M2 12h3M19 12h3M4.9 19.1 7 17M17 7l2.1-2.1"></path><circle cx="12" cy="12" r="7"></circle>',
        'logs' => '<path d="M4 4h16v16H4z"></path><path d="M8 8h8M8 12h8M8 16h5"></path>',
    ];
@endphp

<svg {{ $attributes->merge(['class' => 'nav-svg']) }} viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    {!! $paths[$name] ?? '<circle cx="12" cy="12" r="8"></circle>' !!}
</svg>
