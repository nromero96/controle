@php($anchor = $row['entry'] ?? $row['marks']->first())
@if($anchor)
    <details class="mb-3 border rounded p-2">
        <summary class="fw-semibold">Corregir ingreso / salida</summary>
        <p class="small text-muted mt-2">Registra las horas reales confirmadas, incluso si se olvidó marcar. Las horas originales se conservan. Este cambio no aprueba extras y deja sin efecto una aprobación anterior.</p>
        <form method="POST" action="{{ route('attendances.time-correction', $anchor) }}">
            @csrf
            <input type="hidden" name="source_fingerprint" value="{{ $row['source_fingerprint'] }}">
            <input type="hidden" name="current_lunch_adjustment_id" value="{{ $row['adjustment']?->id ?? 0 }}">
            @foreach(['entry' => 'Ingreso real', 'exit' => 'Salida real'] as $type => $label)
                <label class="form-label" for="{{ $type }}_correction_{{ $anchor->id }}">{{ $label }}</label>
                <input type="time" step="1" class="form-control form-control-sm mb-2" id="{{ $type }}_correction_{{ $anchor->id }}" name="{{ $type }}_time" value="{{ $row[$type]?->effectiveTime() }}">
            @endforeach
            <p class="small text-muted">Si aún no hay salida, puedes dejarla vacía. Una hora existente no se elimina al dejar el campo vacío.</p>
            @if(!$row['entry'])
                <div class="border rounded p-2 mb-2">
                    <p class="small">Falta el ingreso original. Confirma el horario programado que correspondía a esta fecha; no se toma el horario actual del empleado.</p>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="unscheduled" value="1" id="unscheduled_{{ $anchor->id }}"><label class="form-check-label" for="unscheduled_{{ $anchor->id }}">Día sin horario programado</label></div>
                    @foreach(['scheduled_start' => 'Ingreso programado', 'scheduled_end' => 'Salida programada', 'scheduled_break_start' => 'Almuerzo programado: salida (opcional)', 'scheduled_break_end' => 'Almuerzo programado: retorno (opcional)'] as $field => $label)
                        <label class="form-label small" for="{{ $field }}_{{ $anchor->id }}">{{ $label }}</label>
                        <input type="time" class="form-control form-control-sm mb-2" name="{{ $field }}" id="{{ $field }}_{{ $anchor->id }}">
                    @endforeach
                </div>
            @endif
            <label class="form-label" for="time_reason_{{ $anchor->id }}">Motivo y sustento de la corrección</label>
            <textarea class="form-control form-control-sm mb-2" name="reason" id="time_reason_{{ $anchor->id }}" required minlength="10" maxlength="1000" rows="2" placeholder="Ej.: ingreso olvidado; hora confirmada con el responsable."></textarea>
            <button class="btn btn-sm btn-outline-primary">Guardar ingreso / salida</button>
        </form>
    </details>
    @php($timeHistory = $row['marks']->flatMap(fn ($mark) => $mark->timeCorrections->map(fn ($correction) => ['type' => $mark->mark_type, 'correction' => $correction]))->sortByDesc(fn ($item) => $item['correction']->id))
    @if($timeHistory->isNotEmpty())
        <details class="small mb-3">
            <summary>Historial de ingreso y salida ({{ $timeHistory->count() }})</summary>
            @foreach($timeHistory as $item)
                @php($correction = $item['correction'])
                <div class="border-bottom py-2">
                    <strong>{{ $item['type'] === 'entry' ? 'Ingreso' : 'Salida' }}: {{ $correction->previous_time ? $time($correction->previous_time) : 'Sin marcación' }} → {{ $time($correction->corrected_time) }}</strong>
                    <div>{{ $correction->recorder?->name ?? 'Usuario no disponible' }} · {{ $correction->created_at->format('d/m/Y H:i') }}</div>
                    <div>{{ $correction->reason }}</div>
                    @if($correction->previous_overtime_review)<div class="text-muted">Revisión anterior de extras invalidada.</div>@endif
                </div>
            @endforeach
        </details>
    @endif
@endif
