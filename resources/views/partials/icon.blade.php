@php
    $paths = [
        'home' => 'M3 10l9-7 9 7M5 9v12h5v-7h4v7h5V9',
        'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M16 3a4 4 0 0 1 0 8M22 21v-2a4 4 0 0 0-3-3.87M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
        'clock' => 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0M12 7v5l3 2',
        'chart' => 'M4 3v17h17M8 15v-4M13 15V7M18 15V4',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'profile' => 'M20 21v-2a8 8 0 0 0-16 0v2M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
        'building' => 'M4 21V3h12v18M16 9h4v12M8 7h4M8 11h4M8 15h4M2 21h20',
        'calendar' => 'M8 2v4M16 2v4M3 10h18M3 5h18v16H3zM8 14h2M14 14h2M8 18h2',
        'settings' => 'M4 7h16M4 17h16M9 4v6M15 14v6',
        'logout' => 'M9 21H3V3h6M14 8l4 4-4 4M8 12h13',
    ];
@endphp
<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="{{ $paths[$icon] ?? $paths['menu'] }}" /></svg>
