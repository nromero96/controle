@php
    $dias = \App\Http\Requests\EmployeeRequest::DAYS;
    $horarios = isset($employee) ? $employee->schedules->keyBy('day') : collect();
@endphp
<div class="border rounded-3 bg-body-tertiary p-3 mb-4" id="schedule-template">
    <h6 class="fw-bold mb-1">Aplicar un horario a varios días</h6>
    <p class="text-muted small mb-3">Ingresa el horario una vez y aplícalo. Los días elegidos se activarán y recibirán estas horas; después puedes ajustar cada día.</p>
    <div class="row g-3">
        @foreach(['start_time' => 'Entrada', 'end_time' => 'Salida', 'break_start' => 'Salida al almuerzo (opcional)', 'break_end' => 'Retorno del almuerzo (opcional)'] as $field => $label)
            <div class="col-sm-6 col-lg-3">
                <label for="template_{{ $field }}" class="form-label">{{ $label }}</label>
                <input type="time" id="template_{{ $field }}" class="form-control" data-schedule-field="{{ $field }}">
            </div>
        @endforeach
        <div class="col-sm-6 col-lg-4">
            <label for="template_days" class="form-label">Aplicar a</label>
            <select id="template_days" class="form-select">
                <option value="all">Todos los días (lunes a domingo)</option>
                <option value="weekdays">Lunes a viernes</option>
                <option value="saturday">Lunes a sábado</option>
            </select>
        </div>
        <div class="col-sm-6 col-lg-8 d-flex align-items-end">
            <button type="button" id="apply-schedule" class="btn btn-outline-primary">Aplicar horario y activar días</button>
        </div>
    </div>
    <p class="small text-muted mt-3 mb-0">Se reemplazarán las horas de los días elegidos, incluido el almuerzo. Los otros días conservarán su configuración. Los cambios se guardan al guardar el empleado.</p>
    <p id="schedule-template-status" class="small mt-2 mb-0" role="status" aria-live="polite"></p>
</div>
<p class="text-muted small">También puedes configurar cada día por separado. El almuerzo es opcional; si lo usas, completa ambas horas. Los horarios deben terminar el mismo día.</p>
@error('schedule') <div class="text-danger mb-3">{{ $message }}</div> @enderror
@foreach($dias as $dia)
    @php
        $horario = $horarios->get($dia);
        $active = old('schedule.'.$dia.'.active', $horario ? '1' : '0');
    @endphp
    <div class="card mb-2">
        <div class="card-body">
            <input type="hidden" name="schedule[{{ $dia }}][day]" value="{{ $dia }}">
            <input type="hidden" name="schedule[{{ $dia }}][active]" value="0">
            <div class="form-check form-switch mb-2">
                <input class="form-check-input toggle-day" type="checkbox" id="check_{{ $dia }}" name="schedule[{{ $dia }}][active]" value="1" @checked($active)>
                <label class="form-check-label" for="check_{{ $dia }}">{{ $dia }}</label>
            </div>
            @error('schedule.'.$dia) <div class="text-danger">{{ $message }}</div> @enderror
            @error('schedule.'.$dia.'.active') <div class="text-danger">{{ $message }}</div> @enderror
            @error('schedule.'.$dia.'.day') <div class="text-danger">{{ $message }}</div> @enderror
            <div class="row horario-fields {{ $active ? '' : 'd-none' }}" id="fields_{{ $dia }}">
                @foreach(['start_time' => 'Entrada', 'end_time' => 'Salida', 'break_start' => 'Salida al almuerzo (opcional)', 'break_end' => 'Retorno del almuerzo (opcional)'] as $field => $label)
                    <div class="col-md-3 mb-2">
                        <label for="{{ $field }}_{{ $dia }}" class="form-label">{{ $label }}</label>
                        <input type="time" id="{{ $field }}_{{ $dia }}" class="form-control @error('schedule.'.$dia.'.'.$field) is-invalid @enderror" name="schedule[{{ $dia }}][{{ $field }}]" value="{{ old('schedule.'.$dia.'.'.$field, $horario ? substr($horario->$field ?? '', 0, 5) : '') }}" @required($active && in_array($field, ['start_time', 'end_time']))>
                        @error('schedule.'.$dia.'.'.$field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endforeach
