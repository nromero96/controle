import { Modal } from 'bootstrap';

const element = document.getElementById('attendance-photo-modal');
if (element) {
    const caption = element.querySelector('#attendance-photo-caption');
    const status = element.querySelector('#attendance-photo-status');
    const container = element.querySelector('#attendance-photo-image');
    let activeImage = null;
    let trigger = null;

    document.addEventListener('click', event => {
        const button = event.target.closest('[data-attendance-photo]');
        if (!button) return;
        trigger = button;
        container.replaceChildren();
        caption.textContent = button.dataset.photoCaption;
        status.textContent = 'Cargando foto…';
        status.hidden = false;
        const photo = new Image();
        activeImage = photo;
        photo.alt = `Foto de la marcación: ${button.dataset.photoCaption}`;
        photo.className = 'img-fluid rounded';
        photo.style.maxHeight = '70dvh';
        photo.style.objectFit = 'contain';
        photo.onload = () => {
            if (activeImage !== photo) return;
            status.hidden = true;
            container.replaceChildren(photo);
        };
        photo.onerror = () => {
            if (activeImage !== photo) return;
            status.textContent = 'No se pudo cargar la foto. Cierra esta ventana e intenta nuevamente.';
        };
        Modal.getOrCreateInstance(element).show(button);
        photo.src = button.dataset.attendancePhoto;
    });

    element.addEventListener('hidden.bs.modal', () => {
        activeImage = null;
        container.replaceChildren();
        caption.textContent = '';
        status.textContent = '';
        trigger?.focus();
    });
}
