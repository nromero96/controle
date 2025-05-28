@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    
                    <div class="row">
                        <div class="col-md-6 d-flex flex-column justify-content-center">
                            <h4 class="mb-0">{{ __('Lista de empleados') }}</h4>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="{{ route('employees.create') }}" class="btn btn-primary">Agregar Empleado</a>
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
                                <th>ID</th>
                                <th>Nombres</th>
                                <th>Tipo Doc.</th>
                                <th>N° Doc.</th>
                                <th>Correo</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($employees as $employee)
                                <tr>
                                    <td>{{ $employee->id }}</td>
                                    <td>{{ $employee->first_name }} {{ $employee->last_name_father }} {{ $employee->last_name_mother }}</td>
                                    <td>{{ $employee->document_type }}</td>
                                    <td>{{ $employee->document_number }}</td>
                                    <td>{{ $employee->email }}</td>
                                    <td>
                                        @if ($employee->status == 'activo')
                                            <span class="badge bg-success">Activo</span>
                                        @else
                                            <span class="badge bg-danger">Inactivo</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('employees.show', $employee->id) }}" class="btn btn-info">Ver</a>
                                        <a href="{{ route('employees.edit', $employee->id) }}" class="btn btn-warning">Editar</a>
                                        <form action="{{ route('employees.destroy', $employee->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger">Eliminar</button>
                                        </form>
                                    </td>
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
