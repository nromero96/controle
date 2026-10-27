import * as bootstrap from 'bootstrap';
import Swal from 'sweetalert2';

const serverStart = Date.parse(document.body.dataset.serverTime) || Date.now();
const elapsedStart = performance.now();
const clockFormatter = new Intl.DateTimeFormat('es-PE', {
    timeZone: document.body.dataset.timezone || 'America/Lima',
    hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
    day: '2-digit', month: 'long', year: 'numeric',
});
function refreshClock() {
    const parts = Object.fromEntries(clockFormatter.formatToParts(new Date(serverStart + performance.now() - elapsedStart)).map(part => [part.type, part.value]));
    const hour = Number(parts.hour), minute = Number(parts.minute), second = Number(parts.second);
    document.getElementById('clock-hour').style.transform = `rotateZ(${hour * 30 + minute / 2}deg)`;
    document.getElementById('clock-minutes').style.transform = `rotateZ(${minute * 6}deg)`;
    document.getElementById('clock-seconds').style.transform = `rotateZ(${second * 6}deg)`;
    document.getElementById('text-hour').textContent = `${String(hour % 12 || 12).padStart(2, '0')}:`;
    document.getElementById('text-minutes').textContent = parts.minute;
    document.getElementById('text-ampm').textContent = hour >= 12 ? 'PM' : 'AM';
    document.getElementById('date-day').textContent = parts.day;
    document.getElementById('date-month').textContent = `${parts.month} de`;
    document.getElementById('date-year').textContent = parts.year;
}
refreshClock();
setInterval(refreshClock, 1000);

/*==================== DARK/LIGHT THEME ====================*/
const themeButton = document.getElementById('theme-button')
const darkTheme = 'dark-theme'
const iconTheme = 'bxs-sun'

// Previously selected topic (if user selected)
const selectedTheme = localStorage.getItem('selected-theme')
const selectedIcon = localStorage.getItem('selected-icon')

// We obtain the current theme that the interface has by validating the dark-theme class
const getCurrentTheme = () => document.body.classList.contains(darkTheme) ? 'dark' : 'light'
const getCurrentIcon = () => themeButton.classList.contains(iconTheme) ? 'bxs-moon' : 'bxs-sun'

// We validate if the user previously chose a topic
if (selectedTheme) {
  // If the validation is fulfilled, we ask what the issue was to know if we activated or deactivated the dark
  document.body.classList[selectedTheme === 'dark' ? 'add' : 'remove'](darkTheme)
  themeButton.classList[selectedIcon === 'bxs-moon' ? 'add' : 'remove'](iconTheme)
}

// Activate / deactivate the theme manually with the button
themeButton.addEventListener('click', () => {
    // Add or remove the dark / icon theme
    document.body.classList.toggle(darkTheme)
    themeButton.classList.toggle(iconTheme)
    // We save the theme and the current icon that the user chose
    localStorage.setItem('selected-theme', getCurrentTheme())
    localStorage.setItem('selected-icon', getCurrentIcon())
})


const modal = document.getElementById('staticBackdrop');
const input = document.getElementById('num_document');
const button = document.getElementById('btn-confirm');
const name = document.getElementById('employee_name');
const guidance = document.getElementById('marker-guidance');
const scheduleText = document.getElementById('employee-schedule');
const forgottenHelp = document.getElementById('forgotten-lunch-help');
const cells = [...document.querySelectorAll('.marker-table td')];
const labels = { entry: 'Ingreso laboral', break_out: 'Salida al almuerzo', break_in: 'Retorno del almuerzo', exit: 'Salida laboral' };
const actionLabels = { entry: 'Registrar ingreso', break_out: 'Registrar salida al almuerzo', break_in: 'Registrar retorno del almuerzo', exit: 'Registrar salida laboral' };
const cameraEnabled = document.body.dataset.cameraEnabled === '1';
const video = document.getElementById('marker-camera');
const status = document.getElementById('camera-status');
let stream, lookup, searchTimer;
let generation = 0, cameraGeneration = 0, busy = false;
let employee = null, marks = [], allowed = [];
const ready = () => stream && video.readyState >= 2 && video.videoWidth && stream.getVideoTracks().some(t => t.readyState === 'live');
const selectedType = () => cells.find(c => c.classList.contains('marked'))?.dataset.marker;

function update() {
    const selected = selectedType();
    button.disabled = busy || !employee || !allowed.includes(selected) || (cameraEnabled && !ready());
    button.textContent = busy ? 'Registrando…' : selected ? actionLabels[selected] : marks.some(mark => mark.mark_type === 'exit') ? 'Jornada finalizada' : 'Ingresa tu documento';
    modal.querySelector('.btn-close').disabled = busy;
    cells.forEach(cell => {
        const enabled = !busy && allowed.includes(cell.dataset.marker);
        cell.setAttribute('aria-disabled', String(!enabled));
        cell.setAttribute('aria-pressed', String(cell.classList.contains('marked')));
        cell.tabIndex = enabled ? 0 : -1;
    });
}

function reset() {
    employee = null; marks = []; allowed = [];
    name.classList.remove('has-employee');
    scheduleText.textContent = '';
    forgottenHelp.hidden = true;
    cells.forEach(c => {
        c.classList.remove('marked', 'checked'); c.classList.add('inactive');
        c.querySelector('img').src = '/images/registro-time.png';
        c.querySelector('[data-mark-state]').textContent = 'Pendiente';
    });
    update();
}

function stopCamera() {
    cameraGeneration++;
    stream?.getTracks().forEach(t => t.stop()); stream = null;
    if (video) video.srcObject = null;
    update();
}

async function startCamera() {
    if (!cameraEnabled || busy || !employee || !allowed.length) return;
    if (ready()) return;
    stopCamera();
    const current = cameraGeneration;
    status.textContent = 'Conectando cámara…';
    try {
        if (!navigator.mediaDevices?.getUserMedia) throw new Error('Abre el marcador con HTTPS o localhost para usar la cámara.');
        const acquired = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }, audio: false });
        if (current !== cameraGeneration) { acquired.getTracks().forEach(t => t.stop()); return; }
        stream = acquired; video.srcObject = stream; await video.play();
        if (current !== cameraGeneration) return;
        status.textContent = 'Cámara lista. La foto se toma al registrar.';
        stream.getVideoTracks().forEach(t => t.addEventListener('ended', () => { status.textContent = 'La cámara se desconectó. Pulsa Activar cámara para continuar.'; update(); }));
    } catch (error) {
        if (current === cameraGeneration) {
            stopCamera();
            status.textContent = ({ NotAllowedError: 'Permite el acceso a la cámara en tu navegador y pulsa Activar cámara.', NotFoundError: 'No encontramos una cámara. Conéctala y pulsa Activar cámara.', NotReadableError: 'La cámara está ocupada o no responde. Cierra otras aplicaciones e intenta nuevamente.' })[error.name] || error.message;
        }
    }
    update();
}

function renderEmployee(data) {
    employee = data.data;
    marks = data.attendances || [];
    allowed = data.allowed_types || [];
    name.textContent = `Hola, ${employee.first_name} ${employee.last_name_father}`;
    name.classList.add('has-employee');
    if (data.is_unscheduled_day) {
        scheduleText.textContent = 'Día sin horario · Horas extra sujetas a aprobación.';
    } else if (data.schedule?.start_time && data.schedule?.end_time) {
        scheduleText.textContent = `Horario de hoy: ${data.schedule.start_time.slice(0, 5)} a ${data.schedule.end_time.slice(0, 5)}`;
    }
    cells.forEach(cell => {
        const type = cell.dataset.marker;
        const mark = marks.find(m => m.mark_type === type);
        cell.classList.toggle('inactive', !allowed.includes(type));
        cell.classList.toggle('checked', !!mark);
        cell.querySelector('[data-mark-state]').textContent = mark ? `Registrado a las ${mark.mark_time}` : allowed.includes(type) ? 'Disponible' : 'Pendiente';
        cell.querySelector('img').src = mark ? '/images/check.png' : '/images/registro-time.png';
    });
    const closed = marks.some(mark => mark.mark_type === 'exit');
    if (closed) {
        guidance.textContent = marks.length < 4 ? 'Tu salida ya está registrada. Solicita al administrador regularizar el almuerzo olvidado.' : 'Completaste tus marcaciones de hoy. ¡Hasta mañana!';
        stopCamera();
    } else if (!allowed.length) {
        guidance.textContent = 'Tus marcaciones requieren revisión. Comunícate con el administrador.';
        stopCamera();
    } else {
        selectMark(allowed[0]);
        forgottenHelp.hidden = !allowed.includes('exit') || marks.some(m => m.mark_type === 'break_in');
        if (cameraEnabled && modal.classList.contains('show')) startCamera();
    }
    update();
}

async function findEmployee() {
    clearTimeout(searchTimer);
    const documentNumber = input.value.trim();
    const current = ++generation;
    lookup?.abort(); reset(); stopCamera();
    if (documentNumber.length < 6) {
        name.textContent = 'Ingresa tu número de documento para continuar';
        guidance.textContent = 'Escribe al menos 6 caracteres de tu documento.';
        return;
    }
    lookup = new AbortController();
    name.textContent = 'Buscando empleado…';
    guidance.textContent = 'Consultando tus marcaciones de hoy.';
    try {
        const response = await fetch(`/get-employee/${encodeURIComponent(documentNumber)}`, { headers: { Accept: 'application/json' }, signal: lookup.signal });
        let data;
        try { data = await response.json(); } catch { throw new Error('La consulta no respondió correctamente. Revisa la conexión e intenta nuevamente.'); }
        if (current !== generation) return;
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudo consultar al empleado.');
        renderEmployee(data);
    } catch (error) {
        if (current === generation && error.name !== 'AbortError') {
            name.textContent = error instanceof TypeError ? 'No se pudo conectar. Revisa tu conexión e intenta nuevamente.' : error.message;
            guidance.textContent = 'Presiona Enter para volver a consultar o corrige tu documento.';
        }
    }
}

input.addEventListener('input', () => {
    clearTimeout(searchTimer); generation++; lookup?.abort(); reset(); stopCamera();
    name.textContent = 'Ingresa tu número de documento para continuar';
    guidance.textContent = 'Consulta tu documento para comenzar.';
    if (input.value.trim().length >= 6) searchTimer = setTimeout(findEmployee, 350);
});
input.addEventListener('keydown', event => {
    if (event.key === 'Enter' && !busy) { event.preventDefault(); findEmployee(); }
});
themeButton.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); themeButton.click(); } });
function selectMark(type) {
    if (busy || !employee || !allowed.includes(type)) return;
    cells.forEach(cell => {
        const cellType = cell.dataset.marker;
        cell.classList.toggle('marked', cellType === type);
        const mark = marks.find(mark => mark.mark_type === cellType);
        cell.querySelector('[data-mark-state]').textContent = mark ? `Registrado a las ${mark.mark_time}` : cellType === type ? 'Seleccionado' : allowed.includes(cellType) ? 'Disponible' : 'Pendiente';
    });
    guidance.textContent = `Revisa tu nombre y pulsa «${actionLabels[type]}».`;
    update();
}
cells.forEach(cell => {
    cell.addEventListener('click', () => selectMark(cell.dataset.marker));
    cell.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); cell.click(); } });
});
video?.addEventListener('loadeddata', update);
document.getElementById('camera-retry')?.addEventListener('click', startCamera);
modal.addEventListener('shown.bs.modal', () => { input.focus(); if (cameraEnabled) status.textContent = 'Consulta tu documento para activar la cámara.'; });
modal.addEventListener('hide.bs.modal', event => { if (busy) event.preventDefault(); });
modal.addEventListener('hidden.bs.modal', () => {
    clearTimeout(searchTimer); generation++; lookup?.abort(); reset(); stopCamera();
    input.value = ''; name.textContent = 'Ingresa tu número de documento para continuar';
    guidance.textContent = 'Consulta tu documento para comenzar.';
});
window.addEventListener('pagehide', stopCamera);

button.addEventListener('click', async () => {
    const type = selectedType();
    if (button.disabled || busy || !allowed.includes(type)) return;
    busy = true; input.disabled = true; update();
    let refresh = false, sending = false;
    try {
        if (type === 'exit' && (!marks.some(m => m.mark_type === 'break_out') || !marks.some(m => m.mark_type === 'break_in'))) {
            const decision = await Swal.fire({ icon: 'warning', title: '¿Registrar tu salida laboral?', text: 'Faltan marcas del almuerzo. Tu jornada quedará cerrada y el administrador deberá regularizar las horas reales.', showCancelButton: true, confirmButtonText: 'Sí, registrar salida', cancelButtonText: 'Volver' });
            if (!decision.isConfirmed) return;
        }
        const body = new FormData(); body.append('type', type); body.append('document_number', input.value.trim());
        if (cameraEnabled) {
            if (!ready()) throw new Error('Activa la cámara y espera a que esté lista para registrar.');
            const canvas = document.createElement('canvas');
            const scale = Math.min(1, 960 / Math.max(video.videoWidth, video.videoHeight));
            canvas.width = Math.round(video.videoWidth * scale); canvas.height = Math.round(video.videoHeight * scale);
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            const photo = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.85));
            if (!photo) throw new Error('No se pudo capturar la foto. Activa la cámara nuevamente e intenta otra vez.');
            body.append('photo', photo, 'capture.jpg');
        }
        sending = true;
        const response = await fetch('/submit-marker', { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body });
        let data;
        try { data = await response.json(); } catch { refresh = true; throw new Error('No pudimos confirmar la respuesta. Consulta tus marcas antes de intentar nuevamente.'); }
        if (!response.ok) {
            refresh = [409, 422].includes(response.status);
            throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'No se pudo guardar la marcación.');
        }
        busy = false; input.disabled = false; update();
        bootstrap.Modal.getInstance(modal)?.hide();
        await Swal.fire({ icon: 'success', title: 'Marcación registrada', text: `${labels[data.mark_type || type]} · ${data.employee_name || employee?.first_name || ''}: ${data.mark_date} a las ${data.mark_time}${cameraEnabled ? '. Foto guardada.' : '.'}`, showConfirmButton: false, timer: 3000, timerProgressBar: true });
    } catch (error) {
        if (sending && error instanceof TypeError) refresh = true;
        await Swal.fire({ icon: 'error', title: 'Revisa tu marcación', text: sending && error instanceof TypeError ? 'La conexión se interrumpió. Consultaremos tus marcas para comprobar si el registro se guardó.' : error.message });
    } finally {
        busy = false; input.disabled = false; update();
        if (refresh && modal.classList.contains('show')) await findEmployee();
    }
});
