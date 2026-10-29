@if($row['can_regularize'])
    <details class="mb-3 border rounded p-2">
        <summary class="fw-semibold">{{ $row['adjusted'] ? 'Almuerzo regularizado' : 'Regularizar / confirmar almuerzo' }}</summary>
        @if($row['adjusted'])
            <p class="small mt-2 mb-2">Usado para cálculo: <b>{{ $time($row['adjustment']->lunch_start) }} – {{ $time($row['adjustment']->lunch_end) }}</b></p>
        @endif
        <p class="small text-muted mt-2">Registra las horas reales confirmadas. No reemplaza las marcas originales ni aprueba extras. Un almuerzo largo puede confirmarse si realmente ocurrió.</p>
        <form method="POST" action="{{ route('attendances.lunch-regularization', $row['entry']) }}">
            @csrf
            <input type="hidden" name="source_fingerprint" value="{{ $row['source_fingerprint'] }}">
            <input type="hidden" name="current_adjustment_id" value="{{ $row['adjustment']?->id ?? 0 }}">
            <label class="form-label" for="lunch_start_{{ $row['entry']->id }}">Salida real al almuerzo</label>
            <input type="time" step="1" class="form-control form-control-sm mb-2" required id="lunch_start_{{ $row['entry']->id }}" name="lunch_start" value="{{ $row['adjusted'] ? $row['adjustment']->lunch_start : '' }}">
            <label class="form-label" for="lunch_end_{{ $row['entry']->id }}">Retorno real del almuerzo</label>
            <input type="time" step="1" class="form-control form-control-sm mb-2" required id="lunch_end_{{ $row['entry']->id }}" name="lunch_end" value="{{ $row['adjusted'] ? $row['adjustment']->lunch_end : '' }}">
            <label class="form-label" for="lunch_reason_{{ $row['entry']->id }}">Motivo y sustento de la regularización</label>
            <textarea class="form-control form-control-sm mb-2" required minlength="10" maxlength="1000" rows="3" id="lunch_reason_{{ $row['entry']->id }}" name="reason" placeholder="Ej.: retorno olvidado; hora confirmada con el responsable de sede."></textarea>
            <div class="small text-muted mb-2">Para esta regularización se aplica la configuración actual: mínimo {{ $regularizationMinimumLunch }} min − tolerancia {{ $regularizationLunchTolerance }} min = umbral {{ max(1, $regularizationMinimumLunch - $regularizationLunchTolerance) }} min. Las reglas anteriores quedan en el historial. Una corrección deja las extras pendientes de aprobación nuevamente.</div>
            <button class="btn btn-sm btn-outline-primary">Guardar almuerzo confirmado</button>
        </form>
    </details>
@endif
@if($row['adjustments']->isNotEmpty())
    <details class="mb-3 small">
        <summary>Historial de regularizaciones ({{ $row['adjustments']->count() }})</summary>
        @foreach($row['adjustments'] as $adjustment)
            <div class="border-bottom py-2">
                <b>{{ $time($adjustment->lunch_start) }} – {{ $time($adjustment->lunch_end) }}</b>
                <div>{{ $adjustment->recorder?->name ?? 'Usuario no disponible' }} · {{ $adjustment->created_at->format('d/m/Y H:i') }}</div>
                <div>{{ $adjustment->reason }}</div>
                @if($adjustment->previous_lunch_rules)
                    <div class="text-muted">Regla anterior: mínimo {{ $adjustment->previous_lunch_rules['minimum_lunch_minutes'] }} min · Tolerancia {{ $adjustment->previous_lunch_rules['lunch_tolerance_minutes'] }} min.</div>
                @endif
                @if($adjustment->previous_overtime_review)
                    <div class="text-muted">Revisión anterior de extras invalidada: {{ $adjustment->previous_overtime_review['overtime_status'] === 'approved' ? 'aprobadas' : 'rechazadas' }}.</div>
                @endif
            </div>
        @endforeach
    </details>
@endif
