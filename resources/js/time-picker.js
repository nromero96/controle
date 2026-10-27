import flatpickr from 'flatpickr';
import { Spanish } from 'flatpickr/dist/l10n/es.js';
import 'flatpickr/dist/flatpickr.min.css';
import '../css/time-picker.css';

// Cerrar sin normalizar una hora inválida al salir del campo.
for (const type of ['mousedown', 'touchstart', 'focus']) {
    document.addEventListener(type, event => {
        const input = document.querySelector('input[data-time-picker].active');
        const picker = input?._flatpickr;
        if (picker?.isOpen && input.validity.customError && event.target !== input && !picker.calendarContainer.contains(event.target)) {
            picker.close();
        }
    }, true);
}

// Los campos conservan type=time como alternativa si JavaScript no carga.
export function initializeTimePickers(root = document) {
    root.querySelectorAll('input[type="time"]').forEach(input => {
        const seconds = input.step === '1';
        const format = seconds ? 'H:i:S' : 'H:i';
        const timePattern = seconds ? /^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/ : /^([01]?\d|2[0-3]):([0-5]\d)$/;
        input.dataset.timePicker = 'true';
        input.placeholder = seconds ? 'HH:mm:ss' : 'HH:mm';
        input.autocomplete = 'off';
        input.classList.add('time-picker-field');
        input.title = seconds ? 'Hora en formato de 24 horas, por ejemplo 13:30:00' : 'Hora en formato de 24 horas, por ejemplo 13:30';

        const normalized = value => {
            if (!value.trim()) return '';
            const match = value.trim().match(timePattern);
            if (!match) return null;
            return `${match[1].padStart(2, '0')}:${match[2]}${seconds ? `:${match[3] || '00'}` : ''}`;
        };
        const validate = () => {
            const value = normalized(input.value);
            input.setCustomValidity(value === null ? `Ingresa una hora válida de 00:00 a 23:59${seconds ? ':59' : ''}.` : '');
            return value;
        };
        // Validar antes de los manejadores de Flatpickr evita que una hora inválida se normalice silenciosamente.
        const commit = event => {
            const value = validate();
            if (value === null) {
                event.stopImmediatePropagation();
                if (event.type === 'keydown') event.preventDefault();
                input.reportValidity();
                return;
            }
            if (input._flatpickr) {
                if (value) input._flatpickr.setDate(value, false, format);
                else input._flatpickr.clear(false);
            }
        };
        input.addEventListener('input', validate);
        input.addEventListener('blur', commit, true);
        input.addEventListener('keydown', event => {
            if (event.key === 'Enter') commit(event);
        }, true);

        flatpickr(input, {
            locale: Spanish,
            enableTime: true,
            noCalendar: true,
            time_24hr: true,
            enableSeconds: seconds,
            dateFormat: format,
            minuteIncrement: 1,
            allowInput: true,
            disableMobile: true,
            defaultHour: 8,
            parseDate: value => {
                const time = normalized(value);
                if (!time) return undefined;
                const [hour, minute, second = 0] = time.split(':').map(Number);
                const date = new Date();
                date.setHours(hour, minute, second, 0);
                return date;
            },
            onReady: (_, __, picker) => {
                picker.calendarContainer.classList.add('attendance-time-picker');
                picker.hourElement.setAttribute('aria-label', 'Hora');
                picker.minuteElement.setAttribute('aria-label', 'Minutos');
                picker.secondElement?.setAttribute('aria-label', 'Segundos');
                const footer = document.createElement('div');
                footer.className = 'time-picker-actions';
                const hint = document.createElement('span');
                hint.textContent = 'Formato 24 h';
                const clear = document.createElement('button');
                clear.type = 'button';
                clear.className = 'btn btn-sm btn-outline-secondary';
                clear.textContent = 'Limpiar';
                clear.addEventListener('click', () => { picker.clear(); input.setCustomValidity(''); picker.close(); });
                const done = document.createElement('button');
                done.type = 'button';
                done.className = 'btn btn-sm btn-primary';
                done.textContent = 'Listo';
                done.addEventListener('click', () => {
                    if (!picker.selectedDates.length) {
                        const time = `${String(picker.hourElement.value).padStart(2, '0')}:${String(picker.minuteElement.value).padStart(2, '0')}${seconds ? `:${String(picker.secondElement.value).padStart(2, '0')}` : ''}`;
                        picker.setDate(time, true, format);
                    }
                    picker.close();
                });
                footer.append(hint, clear, done);
                picker.calendarContainer.append(footer);
            },
            onChange: () => { input.setCustomValidity(''); input.classList.remove('is-invalid'); },
        });
        input.form?.addEventListener('reset', () => {
            queueMicrotask(() => {
                const original = input.defaultValue;
                if (original) input._flatpickr.setDate(original, false, format);
                else input._flatpickr.clear(false);
                input.setCustomValidity('');
            });
        });
    });
}

initializeTimePickers();
