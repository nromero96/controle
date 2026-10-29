@extends('layouts.app')

@section('content')
@php
    $duration = fn ($seconds) => \App\Services\AttendanceDayCalculator::duration($seconds);
    $types = ['entry' => 'Ingreso', 'break_out' => 'Salida al almuerzo', 'break_in' => 'Retorno del almuerzo', 'exit' => 'Salida laboral'];
    $todayFilters = array_filter(['from' => $today, 'to' => $today, 'branch_id' => $branchId]);
@endphp
<div class="container dashboard-home">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div><p class="small text-muted mb-1 text-capitalize">{{ $now->locale('es')->translatedFormat('l d \d\e F \d\e Y') }}</p><h3 class="fw-bold mb-1">Hola, {{ Auth::user()->name }}</h3><p class="text-muted mb-0">Este es el estado de las asistencias y las tareas pendientes.</p></div>
        <a class="btn btn-primary px-4" href="{{ url('/') }}">Abrir marcador</a>
    </div>
    @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <form method="GET" action="{{ route('home') }}" class="d-flex flex-wrap align-items-center gap-2 mb-4">
        <label for="home_branch" class="small fw-semibold">Sede</label>
        <select id="home_branch" name="branch_id" class="form-select form-select-sm w-auto" style="max-width: 100%;"><option value="">Todas las sedes</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($branchId == $branch->id)>{{ $branch->name }}</option>@endforeach</select>
        <button class="btn btn-sm btn-outline-primary">Aplicar</button><a href="{{ route('home') }}" class="btn btn-sm btn-outline-secondary">Limpiar</a>
    </form>
    <h5 class="fw-bold mb-3">Hoy</h5>
    <div class="row g-3 mb-3 dashboard-stats">
        @foreach(['scheduled' => 'Empleados programados', 'entered' => 'Con ingreso registrado', 'at_lunch' => 'En almuerzo', 'finished' => 'Jornadas finalizadas'] as $key => $label)
            <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="text-muted small mb-2">{{ $label }}</p><div class="fs-2 fw-bold text-primary">{{ $stats[$key] }}</div></div></div></div>
        @endforeach
    </div>
    <div class="d-flex flex-wrap justify-content-between gap-2 border rounded-3 p-3 mb-4 bg-white">
        <span class="small"><b>{{ $stats['without_entry'] }}</b> empleados programados sin ingreso registrado. Su jornada podría no haber comenzado.</span>
        <a class="small" href="{{ route('attendances.index', $todayFilters) }}">Ver asistencias de hoy</a>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <section class="card border-0 shadow-sm h-100" aria-labelledby="pending-heading"><div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div><h5 id="pending-heading" class="fw-bold mb-1">Necesitan atención</h5><p class="small text-muted mb-0">Pendientes acumulados hasta hoy, incluidas jornadas de días anteriores.</p></div></div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6"><div class="bg-warning-subtle rounded p-3"><div class="fs-4 fw-bold">{{ $pending['review_days'] }}</div><div class="small">Jornadas por revisar / regularizar</div></div></div>
                    <div class="col-sm-6"><div class="bg-primary-subtle rounded p-3"><div class="fs-4 fw-bold">{{ $pending['overtime_days'] }}</div><div class="small">Jornadas con extras pendientes · {{ $duration($pending['overtime_seconds']) }}</div></div></div>
                </div>
                <div class="list-group list-group-flush">
                    @forelse($pendingItems as $item)
                        <div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-start gap-3 dashboard-task">
                            <div><div class="fw-semibold">{{ $item['employee']->first_name }} {{ $item['employee']->last_name_father }}</div><div class="small text-muted">{{ \Carbon\Carbon::parse($item['date'])->format('d/m/Y') }} · {{ $item['branch'] }}</div><div class="small mt-1">{{ $item['kind'] === 'overtime' ? 'Horas extra pendientes: '.$duration($item['seconds']) : $item['reason'] }}</div></div>
                            <a class="btn btn-sm btn-outline-primary flex-shrink-0" href="{{ route('attendances.index', ['from' => $item['date'], 'to' => $item['date'], 'employee_id' => $item['employee']->id]) }}">Revisar</a>
                        </div>
                    @empty<div class="text-center text-muted py-4">No hay jornadas cerradas pendientes de revisión.</div>@endforelse
                </div>
                @if($pending['review_days'] + $pending['overtime_days'] > 8)<p class="small text-muted mt-3 mb-0">Se muestran los 8 pendientes más recientes.</p>@endif
            </div></section>
        </div>
        <div class="col-lg-4">
            <section class="card border-0 shadow-sm h-100" aria-labelledby="quick-heading"><div class="card-body">
                <h5 id="quick-heading" class="fw-bold mb-3">Accesos rápidos</h5>
                <div class="d-grid gap-2"><a href="{{ route('employees.create') }}" class="btn btn-outline-primary text-start">Registrar empleado</a><a href="{{ route('attendances.index', $todayFilters) }}" class="btn btn-outline-primary text-start">Gestionar asistencias</a><a href="{{ route('reports.attendances', $todayFilters) }}" class="btn btn-outline-primary text-start">Generar reporte</a><a href="{{ route('schedules.index') }}" class="btn btn-outline-secondary text-start">Consultar horarios</a></div>
                <hr class="my-4"><p class="small text-muted mb-0">La programación usa los empleados activos y su sede actual. Las marcaciones y los pendientes conservan la sede histórica registrada. Los ingresos pueden incluir trabajo en días sin horario.</p>
            </div></section>
        </div>
    </div>
    <section class="card border-0 shadow-sm" aria-labelledby="latest-heading"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3"><h5 id="latest-heading" class="fw-bold mb-0">Últimas marcaciones de hoy</h5><a href="{{ route('attendances.index', $todayFilters) }}" class="small">Ver todas</a></div>
        <div class="table-responsive"><table class="table align-middle small mb-0"><thead><tr><th>Empleado</th><th>Sede registrada</th><th>Marcación</th><th>Hora</th><th>Evidencia</th></tr></thead><tbody>
            @forelse($latestMarks as $mark)
                <tr><td>{{ $mark->employee->first_name }} {{ $mark->employee->last_name_father }}</td><td>{{ $mark->recordedBranchLabel() }}</td><td>{{ $types[$mark->mark_type] }}</td><td class="fw-semibold">{{ \App\Support\AttendanceTime::minutes($mark->mark_time) }}</td><td>@if($mark->photo)@include('partials.attendance-photo-button')@else<span class="text-muted">Sin foto</span>@endif</td></tr>
            @empty<tr><td colspan="5" class="text-center text-muted py-4">Todavía no hay marcaciones hoy.</td></tr>@endforelse
        </tbody></table></div>
    </div></section>
</div>
@endsection
