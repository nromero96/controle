import './bootstrap';
import './time-picker';
import './mobile-layout';

document.querySelectorAll('.toggle-day').forEach(checkbox => {
    const fields = document.getElementById('fields_' + checkbox.id.replace('check_', ''));
    const update = () => {
        fields.classList.toggle('d-none', !checkbox.checked);
        fields.querySelectorAll('input[data-time-picker], input[type="time"]').forEach(input => {
            input.disabled = !checkbox.checked;
            input.required = checkbox.checked && /\[(start_time|end_time)\]$/.test(input.name);
        });
    };
    checkbox.addEventListener('change', update);
    update();
});

const applySchedule = document.getElementById('apply-schedule');
applySchedule?.addEventListener('click', () => {
    const status = document.getElementById('schedule-template-status');
    const template = {};
    let invalidField = null;
    const inputs = [...document.querySelectorAll('[data-schedule-field]')];
    inputs.forEach(input => {
        input.setCustomValidity('');
        template[input.dataset.scheduleField] = input.value;
    });
    const fail = (field, message) => {
        if (!invalidField) invalidField = { field, message };
    };
    if (!template.start_time) fail('start_time', 'Ingresa la hora de entrada.');
    if (!template.end_time) fail('end_time', 'Ingresa la hora de salida.');
    if (template.start_time && template.end_time && template.end_time <= template.start_time) {
        fail('end_time', 'La salida debe ser posterior a la entrada, dentro del mismo día.');
    }
    if (template.break_start || template.break_end) {
        if (!template.break_start) fail('break_start', 'Completa la salida al almuerzo.');
        if (!template.break_end) fail('break_end', 'Completa el retorno del almuerzo.');
        if (template.break_start && (template.break_start <= template.start_time || template.break_start >= template.end_time)) {
            fail('break_start', 'La salida al almuerzo debe estar dentro de la jornada.');
        }
        if (template.break_end && (template.break_end <= template.break_start || template.break_end >= template.end_time)) {
            fail('break_end', 'El retorno del almuerzo debe ser posterior a su salida y anterior al fin de la jornada.');
        }
    }
    if (invalidField) {
        status.className = 'small text-danger mt-2 mb-0';
        status.textContent = invalidField.message;
        const input = inputs.find(input => input.dataset.scheduleField === invalidField.field);
        input.setCustomValidity(invalidField.message);
        input.reportValidity();
        // La plantilla es una herramienta opcional y no debe bloquear el guardado del empleado.
        input.setCustomValidity('');
        return;
    }
    const scope = document.getElementById('template_days').value;
    const count = scope === 'weekdays' ? 5 : scope === 'saturday' ? 6 : 7;
    [...document.querySelectorAll('.toggle-day')].slice(0, count).forEach(checkbox => {
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change'));
        const fields = document.getElementById('fields_' + checkbox.id.replace('check_', ''));
        Object.entries(template).forEach(([field, value]) => {
            const input = fields.querySelector(`input[name$="[${field}]"]`);
            if (input._flatpickr) {
                if (value) input._flatpickr.setDate(value, false, 'H:i');
                else input._flatpickr.clear(false);
            } else {
                input.value = value;
            }
            input.setCustomValidity('');
            input.classList.remove('is-invalid');
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });
    status.className = 'small text-success mt-2 mb-0';
    status.textContent = `Horario aplicado a ${count} días. Puedes ajustarlos antes de guardar.`;
});

if (document.getElementById('attendance-chart-data')) {
    import('./attendance-charts').catch(() => {
        const error = document.getElementById('attendance-chart-error');
        if (error) error.hidden = false;
    });
}
