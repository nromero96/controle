@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    
                    <div class="row">
                        <div class="col-md-6 d-flex flex-column justify-content-center">
                            <h4 class="mb-0">{{ __('Lista de horario') }}</h4>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="/" class="btn btn-primary">Ir a marcar</a>
                        </div>
                    </div>

                </div>

                <div class="card-body">
                    
                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <strong>¡Genial!</strong> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                    
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th rowspan="2">Empleado</th>
                                <th rowspan="2">N° Doc</th>
                                <th colspan="7" class="text-center">Horario</th>
                            </tr>
                            <tr>
                                <th>Lunes</th>
                                <th>Martes</th>
                                <th>Miércoles</th>
                                <th>Jueves</th>
                                <th>Viernes</th>
                                <th>Sábado</th>
                                <th>Domingo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($schedules as $employee)
                                <tr>
                                    <td>
                                        <a href="{{ route('employees.edit', $employee['employee_id']) }}">
                                            {{ $employee['name'] }}
                                        </a>
                                    </td>
                                    <td>{{ $employee['document'] }}</td>

                                    @foreach (['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'] as $day)
                                        <td class="border-start text-center">
                                            @if ($employee['days'][$day])
                                                <span class="badge bg-success">
                                                {{ \Carbon\Carbon::parse($employee['days'][$day]->start_time)->format('H:i') }} - 
                                                {{ \Carbon\Carbon::parse($employee['days'][$day]->end_time)->format('H:i') }}
                                                </span>
                                                @if ($employee['days'][$day]->break_start && $employee['days'][$day]->break_end)
                                                    <br>
                                                    <small class="text-muted">
                                                        Ref: {{ \Carbon\Carbon::parse($employee['days'][$day]->break_start)->format('H:i') }} - 
                                                        {{ \Carbon\Carbon::parse($employee['days'][$day]->break_end)->format('H:i') }}
                                                    </small>
                                                @endif
                                            @else
                                                <span class="badge bg-secondary">Ninguno</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
