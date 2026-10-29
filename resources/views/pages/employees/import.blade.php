@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Importar empleados y horarios</h1>
        <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary">Volver a empleados</a>
    </div>
    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <p class="fw-semibold">No se guardaron empleados. Corrige estos datos y vuelve a cargar el archivo:</p>
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <div class="card mb-3"><div class="card-body">
        <h2 class="h5">1. Prepara el Excel</h2>
        <p class="small text-muted">Una fila por empleado en «Empleados». En «Horarios», usa su documento y los días separados por coma para aplicar un mismo horario a varios días. Puedes agregar otra fila para días con horas diferentes.</p>
        <p class="small">Ejemplo de días: <strong>Lunes,Martes,Miércoles,Jueves,Viernes</strong>. Las horas usan <strong>HH:mm</strong>. El almuerzo es opcional; completa ambas horas o deja ambas vacías.</p>
        <a href="{{ route('employees.import.template') }}" class="btn btn-outline-primary">Descargar plantilla Excel</a>
        <p class="small text-muted mt-3 mb-0">La plantilla incluye los códigos de las sedes activas y las instrucciones. Conserva los documentos como texto para mantener los ceros iniciales. Solo se crearán empleados nuevos activos; los existentes se señalarán como error.</p>
    </div></div>
    <div class="card mb-3"><div class="card-body">
        <h2 class="h5">2. Carga y valida</h2>
        <form method="POST" enctype="multipart/form-data" action="{{ route('employees.import.preview') }}">
            @csrf
            <label for="import-file" class="form-label">Archivo Excel (.xlsx)</label>
            <input type="file" id="import-file" name="file" class="form-control mb-2" accept=".xlsx" required aria-describedby="file-help">
            <p id="file-help" class="small text-muted">Hasta 5 MB y 2000 filas por hoja. Se valida todo el archivo antes de guardar.</p>
            <button class="btn btn-primary">Validar archivo</button>
        </form>
    </div></div>
    @if($preview && $preview['expires'] >= now()->timestamp)
        @php($branchNames = $branches->pluck('name', 'id'))
        <div class="card"><div class="card-body">
            <h2 class="h5">3. Revisa y confirma</h2>
            <p>{{ count($preview['employees']) }} empleados listos para importar. Todavía no se han guardado. Esta revisión vence en 30 minutos desde la carga.</p>
            <div class="table-responsive"><table class="table align-middle">
                <thead><tr><th>Empleado</th><th>Documento</th><th>Sede</th><th>Horario semanal</th></tr></thead>
                <tbody>@foreach($preview['employees'] as $employee)
                    <tr>
                        <td>{{ $employee['first_name'] }} {{ $employee['last_name_father'] }} {{ $employee['last_name_mother'] }}<small class="d-block text-muted">{{ $employee['email'] }}</small></td>
                        <td>{{ $employee['document_type'] }} {{ $employee['document_number'] }}</td>
                        <td>{{ $branchNames[$employee['branch_id']] ?? 'Sede no disponible' }}</td>
                        <td>@forelse($employee['schedule'] as $schedule)
                            <div class="small mb-1"><strong>{{ $schedule['day'] }}:</strong> {{ $schedule['start_time'] }}–{{ $schedule['end_time'] }}@if($schedule['break_start']) · Almuerzo {{ $schedule['break_start'] }}–{{ $schedule['break_end'] }}@endif</div>
                        @empty <span class="text-muted">Sin horario asignado</span> @endforelse</td>
                    </tr>
                @endforeach</tbody>
            </table></div>
            <form method="POST" action="{{ route('employees.import.store') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $preview['token'] }}">
                <button class="btn btn-success">Confirmar importación de {{ count($preview['employees']) }} empleados</button>
            </form>
        </div></div>
    @endif
</div>
@endsection
