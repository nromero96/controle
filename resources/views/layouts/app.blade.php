<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <meta name="theme-color" content="#f5f6fb">

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('images/icono-bio.png') }}" type="image/png">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $data['page_title'] ?? 'Controle' }} - {{ config('app.name') }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body class="admin-shell @auth has-mobile-navigation @endauth">
    <div id="app" class="d-flex flex-column min-vh-100">
        @include('partials.mobile-navigation')
        <nav class="navbar navbar-expand-lg shadow-sm py-0 @auth d-none d-lg-flex @endauth" style="background-color: #e3f2fd;" data-bs-theme="light">
            <div class="container">
                <a class="navbar-brand" href="{{ url('/home') }}">
                    <img src="{{ asset('images/logo.svg') }}" alt="Controle">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav me-auto">

                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto">
                        <!-- Authentication Links -->
                        @guest
                            @if (Route::has('login'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('login') }}">{{ __('Ingresar') }}</a>
                                </li>
                            @endif
                        @else

                            <li class="nav-item border-end">
                                <a class="nav-link" href="{{ route('employees.index') }}">{{ __('Empleados') }}</a>
                            </li>

                            <li class="nav-item border-end">
                                <a class="nav-link" href="{{ route('branches.index') }}">{{ __('Sedes') }}</a>
                            </li>

                            <li class="nav-item border-end">
                                <a class="nav-link" href="{{ route('schedules.index') }}">{{ __('Horarios') }}</a>
                            </li>

                            <li class="nav-item border-end">
                                <a class="nav-link" href="{{ route('attendances.index') }}">{{ __('Asistencias') }}</a>
                            </li>

                            <li class="nav-item border-end">
                                <a class="nav-link" href="{{ route('reports.attendances') }}">Reportes</a>
                            </li>

                            <li class="nav-item border-end">
                                <a class="nav-link" href="{{ route('settings.index') }}">{{ __('Configuración') }}</a>
                            </li>

                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white border text-primary flex-shrink-0" style="width: 30px; height: 30px;" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="8" r="4" />
                                            <path d="M4 21v-2a8 8 0 0 1 16 0v2" />
                                        </svg>
                                    </span>
                                    <span>{{ Auth::user()->name }}</span>
                                </a>

                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" href="{{ route('profile.edit') }}">Mi perfil</a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="{{ route('logout') }}"
                                       onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                        {{ __('Cerrar sesión') }}
                                    </a>

                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </div>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>

        <main class="py-4 admin-main" id="main-content">
            @yield('content')
        </main>
        @include('partials.footer')
    </div>
    @auth
        @include('partials.attendance-photo-modal')
    @endauth
</body>
</html>
