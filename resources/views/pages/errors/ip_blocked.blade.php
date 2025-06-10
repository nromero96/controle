<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('images/icono-bio.png') }}" type="image/png">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/sass/marker.scss', 'resources/js/marker.js'])

    <style>

    </style>


</head>
<body>
    <section class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card mt-5">
                
                    <div class="card-body">
                        <h3 class="text-center">{{ __('Acceso Denegado') }}</h3>
                        <hr>
                        <p class="text-center">{{ __('Ingresa desde un equipo autorizado para marcar tu asistencia. Si crees que esto es un error, contacta al administrador del sistema.') }}</p>
                        <div class="text-center mt-4">
                            <img src="{{ asset('images/logo.svg') }}" alt="Logo" class="img-fluid" style="max-width: 150px;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</body>
</html>