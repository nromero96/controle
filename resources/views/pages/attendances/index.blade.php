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
                    
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th rowspan="2">Nombres</th>
                                <th colspan="5"  class="text-center">Asistencia</th>
                            </tr>
                            <tr>
                                <th class="bg-secondary">Fecha</th>
                                <th>Ingreso</th>
                                <th>Refrigerio</th>
                                <th>Retorno</th>
                                <th>Salida</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($attendances as $attendance)
                                <tr>
                                    <td><a href="{{ route('employees.edit', $attendance->employee_id) }}">{{ $attendance->full_name }}</a></td>
                                    <td class="bg-secondary">{{ $attendance->mark_date }}</td>
                                    <td>{{ $attendance->entry_time ?? '-' }}</td>
                                    <td>{{ $attendance->break_out_time ?? '-' }}</td>
                                    <td>{{ $attendance->break_in_time ?? '-' }}</td>
                                    <td>{{ $attendance->exit_time ?? '-' }}</td>
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
