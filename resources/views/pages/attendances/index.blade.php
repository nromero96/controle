@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    
                    <div class="row">
                        <div class="col-md-6 d-flex flex-column justify-content-center">
                            <h4 class="mb-0">{{ __('Lista de asistencia') }}</h4>
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
                    
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th rowspan="2">Nombres y Apellidos</th>
                                <th rowspan="2">Horario de Trabajo</th>
                                <th colspan="7"  class="text-center">Asistencia</th>
                            </tr>
                            <tr>
                                <th class="bg-secondary-subtle text-secondary-emphasis">Fecha</th>
                                <th class="bg-secondary-subtle text-secondary-emphasis">Ingreso</th>
                                <th class="bg-secondary-subtle text-secondary-emphasis">Retraso</th>
                                <th class="bg-secondary-subtle text-secondary-emphasis">Refrigerio</th>
                                <th class="bg-secondary-subtle text-secondary-emphasis">Retorno</th>
                                <th class="bg-secondary-subtle text-secondary-emphasis">Salida</th>
                                <th class="bg-secondary-subtle text-secondary-emphasis">Sobretiempo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($attendances as $attendance)
                                <tr>
                                    <td><a href="{{ route('employees.edit', $attendance->employee_id) }}">{{ $attendance->full_name }}</a></td>

                                    <td>
                                        <b>{{ $attendance->day_name }}</b>: 
                                        <small class="text-muted">{{ \Carbon\Carbon::parse($attendance->scheduled_start_time)->format('H:i') }} a 
                                        {{ \Carbon\Carbon::parse($attendance->scheduled_end_time)->format('H:i') }}</small>
                                    </td>

                                    <td class="bg-success-subtle text-success-emphasis">
                                        
                                        

                                        {{ \Carbon\Carbon::parse($attendance->mark_date)->format('d-m-Y') }}
                                        
                                    </td>
                                    
                                    <td>{{ $attendance->entry_time ?? '-' }}</td>
                                    <td><span class="badge text-bg-danger">{{ $attendance->lateness_time ?? '' }}</span></td>
                                    <td>{{ $attendance->break_out_time ?? '-' }}</td>
                                    <td>{{ $attendance->break_in_time ?? '-' }}</td>
                                    <td>{{ $attendance->exit_time ?? '-' }}</td>
                                    <td><span class="badge text-bg-success">{{ $attendance->overtime_time ?? '' }}</span></td>                      
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
