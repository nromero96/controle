@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">{{ __('Dashboard') }}</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-6">
                            <h4 class="mb-0">{{ __('Bienvenido a la aplicación de control de asistencia') }}</h4>
                            <p>{{ __('Esta aplicación te permite gestionar empleados, horarios y asistencias de manera eficiente.') }}</p>
                        </div>
                        <div class="col-md-6 text-end">
                            <img src="{{ asset('images/logo.svg') }}" alt="Logo" class="img-fluid" style="max-width: 200px;">
                        </div>
                    </div>
                    <div class="mt-4">
                        <h5>{{ __('¿Qué te gustaría hacer?') }}</h5>
                        <ul class="list-unstyled">
                            <li><a href="{{ route('employees.index') }}">{{ __('Gestionar Empleados') }}</a></li>
                            <li><a href="{{ route('schedules.index') }}">{{ __('Gestionar Horarios') }}</a></li>
                            <li><a href="{{ route('attendances.index') }}">{{ __('Registrar Asistencias') }}</a></li>
                        </ul>
                    </div>
                    
                    
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
