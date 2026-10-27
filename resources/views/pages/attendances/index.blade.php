@extends('layouts.app')

@section('content')
@php
    $duration = fn ($seconds) => \App\Services\AttendanceDayCalculator::duration($seconds);
    $statuses = ['incomplete' => 'Pendientes de cálculo por incidencia', 'none' => 'Sin extras elegibles', 'pending' => 'Pendiente de aprobar extras', 'approved' => 'Extras aprobadas', 'rejected' => 'Extras rechazadas'];
@endphp
@php($types = ['entry' => 'Ingreso laboral', 'break_out' => 'Salida al almuerzo', 'break_in' => 'Retorno del almuerzo', 'exit' => 'Salida laboral'])
<div class="container-fluid px-lg-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><h3 class="fw-bold mb-1">Asistencias</h3><p class="text-muted mb-0">Marcaciones originales agrupadas por empleado y día.</p></div>
        <div class="d-flex gap-2"><a class="btn btn-outline-primary" href="{{ route('reports.attendances', request()->only(['from', 'to', 'employee_id', 'branch_id'])) }}">Ver reportes</a><a class="btn btn-primary" href="{{ url('/') }}">Ir a marcar</a></div>
    </div>
    @foreach(['success' => 'success', 'error' => 'danger'] as $key => $color)
        @if(session($key))<div class="alert alert-{{ $color }}" role="status">{{ session($key) }}</div>@endif
    @endforeach
    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    @include('pages.attendances.filters', ['filterRoute' => 'attendances.index'])

    <div class="card border-0 shadow-sm"><div class="card-body">
        <div class="table-responsive"><table class="table table-striped align-middle small">
            <thead><tr><th>Empleado</th><th>Sede registrada</th><th>Fecha</th><th>Horario programado</th>@foreach($types as $label)<th>{{ $label }}</th>@endforeach<th style="min-width: 270px">Gestión y horas extra</th></tr></thead>
            <tbody>@forelse($records as $record)
                <tr>
                    <td>{{ $record->employee->first_name }} {{ $record->employee->last_name_father }} {{ $record->employee->last_name_mother }}<div class="small text-muted">{{ $record->employee->document_number }}</div></td>
                    <td>{{ ($record->marks->get('entry') ?? $record->marks->first())->recordedBranchLabel() }}</td>
                    <td class="text-nowrap">{{ \Carbon\Carbon::parse($record->mark_date)->format('d/m/Y') }}</td>
                    @php($entry = $record->marks->get('entry'))
                    <td class="text-nowrap">
                        @if($entry?->is_unscheduled_day)
                            <span class="text-muted">Sin horario programado</span>
                        @elseif($entry?->scheduled_start_time && $entry?->scheduled_end_time)
                            <span>{{ substr($entry->scheduled_start_time, 0, 5) }} – {{ substr($entry->scheduled_end_time, 0, 5) }}</span>
                            @if($entry->scheduled_break_start && $entry->scheduled_break_end)
                                <div class="small text-muted">Almuerzo: {{ substr($entry->scheduled_break_start, 0, 5) }} – {{ substr($entry->scheduled_break_end, 0, 5) }}</div>
                            @endif
                        @else
                            <span class="text-muted">Sin horario guardado</span>
                        @endif
                    </td>
                    @foreach($types as $type => $label)
                        @php($mark = $record->marks->get($type))
                        <td class="text-nowrap">
                            @if($mark)
                                <span class="fw-semibold">{{ $mark->mark_time }}</span>
                                @if($mark->ip_address)<div class="small text-muted">IP: {{ $mark->ip_address }}</div>@endif
                                @if($mark->photo)<a class="small" href="{{ route('attendances.photo', $mark) }}" target="_blank" rel="noopener">Ver foto</a>@endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    @endforeach
                    <td>
                        @php($row = $record->day_result)
                        @include('pages.attendances.day-management')
                    </td>
                </tr>
            @empty<tr><td colspan="9" class="text-center text-muted py-5">No hay marcaciones para los filtros seleccionados.</td></tr>@endforelse</tbody>
        </table></div>
        <div class="d-flex justify-content-between align-items-center mt-3"><span class="small text-muted">{{ $records->total() }} jornadas · Página {{ $records->currentPage() }} de {{ $records->lastPage() }}</span><div class="d-flex gap-2">@if($records->previousPageUrl())<a class="btn btn-sm btn-outline-primary" href="{{ $records->previousPageUrl() }}">Anterior</a>@endif @if($records->nextPageUrl())<a class="btn btn-sm btn-outline-primary" href="{{ $records->nextPageUrl() }}">Siguiente</a>@endif</div></div>
    </div></div>
</div>
@endsection
