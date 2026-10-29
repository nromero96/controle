@extends('layouts.app')

@section('content')
@php
    $duration = fn ($seconds) => \App\Support\AttendanceTime::duration($seconds);
    $time = fn ($value) => \App\Support\AttendanceTime::minutes($value);
    $statuses = ['incomplete' => 'Pendientes de cálculo por incidencia', 'none' => 'Sin extras elegibles', 'pending' => 'Pendiente de aprobar extras', 'approved' => 'Extras aprobadas', 'rejected' => 'Extras rechazadas'];
@endphp
<div class="container-fluid px-lg-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1">Reportes de asistencia</h3>
            <p class="text-muted mb-0">Trabajo real = salida − ingreso − almuerzo marcado. Extras = trabajo real − jornada neta programada.</p>
        </div>
        <a href="{{ url('/') }}" class="btn btn-primary">Ir a marcar</a>
    </div>
    @foreach(['success' => 'success', 'error' => 'danger'] as $key => $color)
        @if(session($key)) <div class="alert alert-{{ $color }}" role="status">{{ session($key) }}</div> @endif
    @endforeach
    @if($errors->any())
        <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @include('pages.attendances.filters', ['filterRoute' => 'reports.attendances'])
    <section id="attendance-charts" class="mb-4" aria-labelledby="chart-heading">
        <h5 id="chart-heading" class="fw-bold mb-1">{{ $selectedEmployee ? 'Gráficas de '.$selectedEmployee->first_name.' '.$selectedEmployee->last_name_father : 'Comparación por empleado' }}</h5>
        <p class="small text-muted mb-3">Selecciona un empleado y el período, luego pulsa Generar reporte. Con todos los empleados se comparan sus totales; con uno se muestra su evolución diaria. Las gráficas incluyen todo el período, sin depender de la página del listado.</p>
        @if(count($chartData['points']))
            <div class="row g-3">
                <div class="col-xl-7"><div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <h6>Horas programadas y trabajadas</h6>
                    <div class="overflow-auto"><div data-chart-scroll style="position: relative; height: 340px;"><canvas id="attendance-work-chart" role="img" aria-label="Comparación de horas programadas y trabajadas. Los valores exactos están en las tablas del reporte."></canvas></div></div>
                </div></div></div>
                <div class="col-xl-5"><div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <h6>Estado de las horas extra</h6>
                    <div class="overflow-auto"><div data-chart-scroll style="position: relative; height: 340px;"><canvas id="attendance-extra-chart" role="img" aria-label="Horas extra pendientes, aprobadas y rechazadas. Los valores exactos están en las tablas del reporte."></canvas></div></div>
                </div></div></div>
            </div>
            <p class="small text-muted mt-2 mb-0">Eje vertical en horas. Al pasar el cursor se muestra HH:mm. Las jornadas incompletas no se representan como cero horas trabajadas; permanecen pendientes de cálculo en el detalle.</p>
            <div id="attendance-chart-error" class="alert alert-secondary mt-2" hidden>No se pudieron cargar las gráficas. Los datos completos siguen disponibles en las tablas.</div>
            <script id="attendance-chart-data" type="application/json">@json($chartData)</script>
        @else
            <div class="alert alert-light border">No hay datos para graficar con los filtros seleccionados.</div>
        @endif
    </section>
    <p class="small text-muted">Los totales corresponden a todo el período filtrado. El trabajo real suma únicamente jornadas completas y válidas. Las horas programadas de días sin ingreso se estiman con el horario actual; no constituyen automáticamente faltas ni descuentos. Los registros anteriores sin sede guardada se muestran como desconocidos; el filtro por sede registrada no los atribuye a la sede actual.</p>
    <div class="row g-3 mb-4">
        @foreach(['scheduled' => 'Horas programadas netas', 'worked' => 'Horas trabajadas computables', 'pending' => 'Extras pendientes', 'approved' => 'Extras aprobadas'] as $field => $label)
            <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">{{ $label }}</div><div class="fs-4 fw-bold">{{ $duration($summaries->sum($field)) }}</div></div></div></div>
        @endforeach
    </div>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5>Resumen por empleado</h5>
            <div class="table-responsive"><table class="table align-middle small">
                <thead><tr><th>Empleado</th><th>Programado neto</th><th>Trabajado</th><th>Almuerzo real</th><th>Extra calculada</th><th>Extra pendiente</th><th>Extra aprobada</th><th>Extra rechazada</th><th>Jornadas</th><th></th></tr></thead>
                <tbody>@forelse($summaries as $summary)
                    <tr>
                        <td>{{ $summary['employee']->first_name }} {{ $summary['employee']->last_name_father }}<div class="text-muted">{{ $summary['employee']->document_number }}</div></td>
                        @foreach(['scheduled', 'worked', 'lunch', 'raw_extra', 'pending', 'approved', 'rejected'] as $field)<td class="text-nowrap">{{ $duration($summary[$field]) }}</td>@endforeach
                        <td>{{ $summary['complete_days'] }} completas · {{ $summary['incomplete_days'] }} con incidencia · {{ $summary['unmarked_days'] }} sin marcas<div class="text-muted">{{ $summary['estimated_days'] }} días con programación estimada · {{ $summary['unknown_schedule_days'] }} sin jornada válida</div></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="{{ route('reports.attendances', array_merge(request()->except('page'), ['employee_id' => $summary['employee']->id, 'from' => $from, 'to' => $to])) }}">Ver detalle</a></td>
                    </tr>
                @empty<tr><td colspan="10" class="text-center text-muted">No hay empleados para los filtros seleccionados.</td></tr>@endforelse</tbody>
            </table></div>
        </div>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h5>Detalle diario del período</h5>
            <p class="small text-muted">El ingreso anticipado cuenta como trabajo. La asistencia se registra sin aprobación. Solo se aprueban las horas extra; para calcularlas se necesitan marcas válidas o un almuerzo regularizado. Las horas originales siempre se conservan. Los tiempos se muestran en HH:mm.</p>
            <div class="table-responsive">
                <table class="table table-bordered align-middle small">
                    <thead class="table-light"><tr>
                        <th>Empleado / sede registrada</th><th>Fecha</th><th>Horario programado</th><th>Ingreso</th><th>Salida al almuerzo</th><th>Retorno del almuerzo</th><th>Salida</th><th>Tardanza</th><th>Almuerzo real</th><th>Jornada neta</th><th>Trabajo real</th><th>Extra calculada</th><th>Extra elegible</th><th>Extra aprobada</th><th style="min-width: 240px">Estado de horas extra</th>
                    </tr></thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td><a href="{{ route('employees.edit', $row['employee']) }}">{{ $row['employee']->first_name }} {{ $row['employee']->last_name_father }} {{ $row['employee']->last_name_mother }}</a><div class="text-muted">{{ $row['branch_label'] }}</div></td>
                                <td class="text-nowrap">{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                                <td class="text-nowrap">
                                    @if($row['is_unscheduled_day'])
                                        <span class="badge text-bg-info">Día sin horario programado</span>
                                        <div class="text-muted">Trabajo efectivo sujeto a aprobación como extra.</div>
                                    @else
                                    {{ $time($row['display_start']) }} – {{ $time($row['display_end']) }}
                                    @if($row['schedule_estimated'])<div class="text-warning-emphasis">Estimado con horario actual</div>@endif
                                    <div class="text-muted">Almuerzo: @if($row['uses_reference_lunch']) {{ $row['reference_lunch'] }} min de referencia @else {{ $time($row['display_break_start']) }} – {{ $time($row['display_break_end']) }} @endif</div>
                                    @endif
                                    <div class="text-muted">Mínimo extra: {{ $row['minimum'] }} min</div>
                                    <div class="text-muted">Almuerzo mínimo: {{ $row['minimum_lunch'] }} min · Tolerancia: {{ $row['lunch_tolerance'] }} min</div>
                                    @if($row['legacy_rules'])<div class="text-warning-emphasis">Reglas actuales (registro antiguo)</div>@endif
                                </td>
                                @foreach(['entry', 'break_out', 'break_in', 'exit'] as $type)
                                    @php($mark = $row['marks']->get($type))
                                    <td class="text-nowrap">
                                        @include('pages.attendances.mark-time')
                                        @if($type === 'exit' && $row['exit_status'] !== 'unknown')
                                            <div class="small {{ $row['exit_status'] === 'normal' ? 'text-success' : 'text-muted' }}">{{ ['normal' => 'Salida dentro de tolerancia', 'early' => 'Salida anticipada', 'late' => 'Salida posterior'][$row['exit_status']] }}</div>
                                            <div class="small text-muted">Margen: ±{{ $row['exit_tolerance'] }} min</div>
                                        @endif
                                        @if($mark?->photo)<div>@include('partials.attendance-photo-button')</div>@endif
                                    </td>
                                @endforeach
                                <td>{{ $row['entry'] ? $time($row['entry']->lateness_time ?? '00:00:00') : '—' }}</td>
                                @foreach(['lunch', 'scheduled', 'worked', 'raw_extra', 'eligible', 'approved'] as $field)
                                    <td class="text-nowrap {{ $field === 'approved' && $row[$field] > 0 ? 'text-success fw-bold' : '' }}">{{ $field === 'scheduled' ? $duration($row['planned']) : (!$row['complete'] ? '—' : $duration($row[$field])) }}
                                        @if($field === 'lunch' && $row['adjusted'])
                                            <div class="small text-primary">Regularizado: {{ $time($row['adjustment']->lunch_start) }} – {{ $time($row['adjustment']->lunch_end) }}</div>
                                        @endif
                                    </td>
                                @endforeach
                                <td>
                                    <span class="badge {{ ['approved' => 'text-bg-success', 'rejected' => 'text-bg-danger', 'pending' => 'text-bg-warning'][$row['status']] ?? 'text-bg-secondary' }}">{{ $row['unmarked'] ? 'Sin marcaciones' : $statuses[$row['status']] }}</span>
                                    @if(!$row['complete'])<div class="text-muted mt-2">{{ $row['reason'] }}</div>@endif
                                    @if(in_array($row['status'], ['approved', 'rejected']))
                                        <div class="text-muted mt-2">{{ $row['exit']->reviewer?->name ?? 'Usuario no disponible' }}<br>{{ $row['exit']->overtime_reviewed_at?->format('d/m/Y H:i') }}</div>
                                        <div>{{ $row['exit']->overtime_review_note }}</div>
                                    @endif
                                    @if(!$row['unmarked'])
                                        <a class="small d-block mt-2" href="{{ route('attendances.index', ['from' => $row['date'], 'to' => $row['date'], 'employee_id' => $row['employee']->id]) }}">Gestionar en Asistencias</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="15" class="text-center text-muted py-5">No hay jornadas para los filtros seleccionados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <span class="small text-muted">{{ $days->total() }} jornadas · Página {{ $days->currentPage() }} de {{ $days->lastPage() }}</span>
                <div class="d-flex gap-2">
                    @if($days->previousPageUrl())<a class="btn btn-sm btn-outline-primary" href="{{ $days->previousPageUrl() }}">Anterior</a>@endif
                    @if($days->nextPageUrl())<a class="btn btn-sm btn-outline-primary" href="{{ $days->nextPageUrl() }}">Siguiente</a>@endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
