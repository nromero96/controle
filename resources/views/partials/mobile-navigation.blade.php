@auth
<header class="mobile-app-header d-lg-none">
    <a class="mobile-app-brand" href="{{ route('home') }}" aria-label="Ir al inicio"><img src="{{ asset('images/logo.svg') }}" alt="{{ config('app.name') }}"></a>
    <div class="mobile-app-heading"><span>Panel de administración</span><strong>{{ $data['page_title'] ?? 'Inicio' }}</strong></div>
    <a class="mobile-profile-button" href="{{ route('profile.edit') }}" aria-label="Mi perfil: {{ Auth::user()->name }}">@include('partials.icon', ['icon' => 'profile'])</a>
</header>
<nav class="mobile-tab-bar d-lg-none" aria-label="Navegación principal móvil">
    @foreach([
        ['route' => 'home', 'pattern' => 'home', 'label' => 'Inicio', 'icon' => 'home'],
        ['route' => 'employees.index', 'pattern' => 'employees.*', 'label' => 'Empleados', 'icon' => 'users'],
        ['route' => 'attendances.index', 'pattern' => 'attendances.*', 'label' => 'Asistencias', 'icon' => 'clock'],
        ['route' => 'reports.attendances', 'pattern' => 'reports.*', 'label' => 'Reportes', 'icon' => 'chart'],
    ] as $tab)
        <a href="{{ route($tab['route']) }}" class="mobile-tab {{ request()->routeIs($tab['pattern']) ? 'is-active' : '' }}" @if(request()->routeIs($tab['pattern'])) aria-current="page" @endif>
            @include('partials.icon', ['icon' => $tab['icon']])<span>{{ $tab['label'] }}</span>
        </a>
    @endforeach
    <button type="button" class="mobile-tab {{ request()->routeIs('branches.*', 'schedules.*', 'settings.*', 'profile.*') ? 'is-active' : '' }}" data-bs-toggle="offcanvas" data-bs-target="#mobile-more-menu" aria-controls="mobile-more-menu" aria-label="Abrir más opciones">@include('partials.icon', ['icon' => 'menu'])<span>Más</span></button>
</nav>
<div class="offcanvas offcanvas-bottom mobile-more-sheet" tabindex="-1" id="mobile-more-menu" aria-labelledby="mobile-more-title">
    <div class="offcanvas-header"><div><h5 id="mobile-more-title" class="fw-bold mb-1">Más opciones</h5><div class="small text-muted text-break">{{ Auth::user()->name }}</div></div><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar menú"></button></div>
    <div class="offcanvas-body pt-0">
        <div class="mobile-menu-grid">
            @foreach([
                ['route' => 'branches.index', 'label' => 'Sedes', 'icon' => 'building'],
                ['route' => 'schedules.index', 'label' => 'Horarios', 'icon' => 'calendar'],
                ['route' => 'settings.index', 'label' => 'Configuración', 'icon' => 'settings'],
                ['route' => 'profile.edit', 'label' => 'Mi perfil', 'icon' => 'profile'],
            ] as $item)
                <a href="{{ route($item['route']) }}">@include('partials.icon', ['icon' => $item['icon']])<span>{{ $item['label'] }}</span></a>
            @endforeach
        </div>
        <a href="{{ url('/') }}" class="btn btn-primary w-100 mt-3 py-3">Abrir marcador de asistencia</a>
        <form action="{{ route('logout') }}" method="POST" class="mt-2">@csrf<button class="btn btn-light text-danger w-100 py-3 d-flex justify-content-center align-items-center gap-2">@include('partials.icon', ['icon' => 'logout']) Cerrar sesión</button></form>
        <p class="small text-muted text-center mt-3 mb-0">Versión {{ config('app.version') }}</p>
    </div>
</div>
@endauth
