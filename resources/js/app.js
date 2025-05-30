import './bootstrap';

document.querySelectorAll('.toggle-day').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                const day = this.id.replace('check_', '');
                const fields = document.getElementById('fields_' + day);
                if (this.checked) {
                    fields.classList.remove('d-none');
                } else {
                    fields.classList.add('d-none');
                    // Limpia los inputs si se desactiva
                    fields.querySelectorAll('input[type="time"]').forEach(input => input.value = '');
                }
            });
        });
