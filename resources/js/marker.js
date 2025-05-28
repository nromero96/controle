import * as bootstrap from 'bootstrap';
import Swal from 'sweetalert2';

/*==================== CLOCK ====================*/
const hour = document.getElementById('clock-hour'),
      minutes = document.getElementById('clock-minutes'),
      seconds = document.getElementById('clock-seconds')

const clock = () =>{
    let date = new Date()

    let hh = date.getHours() * 30,
        mm = date.getMinutes() * 6,
        ss = date.getSeconds() * 6
        
    // We add a rotation to the elements
    hour.style.transform = `rotateZ(${hh + mm / 12}deg)`
    minutes.style.transform = `rotateZ(${mm}deg)`
    seconds.style.transform = `rotateZ(${ss}deg)`
}
clock();
setInterval(clock, 1000) // 1000 = 1s

/*==================== CLOCK & DATE TEXT ====================*/
const textHour = document.getElementById('text-hour'),
      textMinutes = document.getElementById('text-minutes'),
      textAmPm = document.getElementById('text-ampm'),
    //   dateWeek = document.getElementById('date-day-week'),
      dateDay = document.getElementById('date-day'),
      dateMonth = document.getElementById('date-month'),
      dateYear = document.getElementById('date-year')

const clockText = () =>{
    let date = new Date()

    let hh = date.getHours(),
        ampm,
        mm = date.getMinutes(),
        day = date.getDate(),
        // dayweek = date.getDay(),
        month = date.getMonth(),
        year = date.getFullYear()

    // We change the hours from 24 to 12 hours and establish whether it is AM or PM
    if(hh >= 12){
        hh = hh - 12
        ampm = 'PM'
    }else{
        ampm = 'AM'
    }

    // We detect when it's 0 AM and transform to 12 AM
    if(hh == 0){hh = 12}

    // Show a zero before hours
    if(hh < 10){hh = `0${hh}`}

    // Show time
    textHour.innerHTML = `${hh}:`
    
    // Show a zero before the minutes
    if(mm < 10){mm = `0${mm}`}
    
    // Show minutes
    textMinutes.innerHTML = mm

    // Show am or pm
    textAmPm.innerHTML = ampm

    // If you want to show the name of the day of the week
    // let week = ['Sun', 'Mon', 'Tues', 'Wed', 'Thurs', 'Fri', 'Sat']

    // We get the months of the year and show it
    let months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

    // We show the day, the month and the year
    dateDay.innerHTML = day
    // dateWeek.innerHTML = `${week[dayweek]}`
    dateMonth.innerHTML = `${months[month]},`
    dateYear.innerHTML = year
}
clockText();
setInterval(clockText, 1000) // 1000 = 1s

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


// Selección de marcador
document.querySelectorAll('.marker-table td').forEach(td => {
    td.addEventListener('click', () => {
        // Ignorar si tiene clase inactive o checked
        if (td.classList.contains('inactive')) return;
        if (td.classList.contains('checked')) return;

        document.querySelectorAll('.marker-table td').forEach(c => c.classList.remove('marked'));
        td.classList.add('marked');
        // Habilitar el botón de confirmación
        const btnConfirm = document.getElementById('btn-confirm');
        btnConfirm.disabled = false;
    });
});



document.addEventListener('DOMContentLoaded', function () {
    const searchNumdocument = document.getElementById('num_document');

    if (searchNumdocument) {
        // Desactivar autocompletado y correcciones automáticas
        searchNumdocument.setAttribute('autocomplete', 'off');
        searchNumdocument.setAttribute('spellcheck', 'false');
        searchNumdocument.setAttribute('autocorrect', 'off');
        searchNumdocument.setAttribute('autocapitalize', 'off');

        // Prevenir pegar, copiar, cortar, seleccionar y soltar
        ['paste', 'copy', 'cut', 'drop', 'selectstart'].forEach(evt => {
            searchNumdocument.addEventListener(evt, e => e.preventDefault());
        });

        // Aplicar estilo para deshabilitar selección visual de texto
        searchNumdocument.classList.add('no-select');
    }


  
    searchNumdocument.addEventListener('input', function () {
        if (this.value.length >= 6 || this.value.length === 0 ) {
            const btnConfirm = document.getElementById('btn-confirm');
            const doc = this.value;
            const dvEmployeeName = document.getElementById('employee_name');
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');


            dvEmployeeName.innerHTML = '<div class="spinner-grow" role="status"><span class="visually-hidden">Loading...</span></div>';

            fetch(`/get-employee/${doc}`, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Empleado encontrado:', data.data);
                    document.getElementById('employee_name').innerHTML = 'Hola, <b>' + data.data.first_name + ' ' + data.data.last_name_father + '</b>';
                    if(data.attendances == false){
                        //habilitar el primer td 
                        const firstTd = document.querySelector('.marker-table td');
                        firstTd.classList.remove('inactive');
                    }else{
                        console.log('Asistencias encontrada:', data.attendances);
                        const tds = document.querySelectorAll('.marker-table td');
                        tds.forEach(td => {
                            const markerType = td.dataset.marker;
                            const isMarked = data.attendances.some(attendance => attendance.mark_type === markerType);

                            if (isMarked) {
                                td.classList.add('checked');
                                td.classList.remove('inactive');
                                // remplazar img
                                const img = td.querySelector('img');
                                if(img) {
                                    img.src = '/images/check.png'; // Cambia la ruta a la imagen que desees
                                }
                            } else {
                                td.classList.remove('checked');
                            }
                        });

                        const firstTdIc = document.querySelector('.marker-table td.inactive');
                        firstTdIc.classList.remove('inactive');
                        // change image src in firstTdIc
                        const nowimg = firstTdIc.querySelector('img');
                        if(nowimg) {
                            nowimg.src = '/images/registro-now.gif'; // Cambia la ruta a la imagen que desees
                        }

                    }

                } else {
                    document.getElementById('employee_name').innerHTML = '<span class="text-muted">Empleado no encontrado</span>';
                    // Limpiar la tabla de marcadores
                    document.querySelectorAll('.marker-table td').forEach(td => {
                        td.classList.remove('marked');
                        td.classList.remove('checked');
                        td.classList.add('inactive');
                        const img = td.querySelector('img');
                        if(img) {
                            img.src = '/images/registro-time.png'; // Cambia la ruta a la imagen original
                        }
                    });

                    //bloquear el boton confirm
                    btnConfirm.disabled = true;

                }
            })
            .catch(error => {
                console.log('Error:', error.message);
                // Mostrar error o limpiar campos
            });
        }
    });

    
});



//Enviar el dato td marcado cuando hago click en el botón de btn-confirm

document.getElementById('btn-confirm').addEventListener('click', function() {
    const markedTd = document.querySelector('.marker-table td.marked');
    if (markedTd) {
        const markerType = markedTd.dataset.marker;
        const numDocument = document.getElementById('num_document').value;

        // Aquí puedes enviar el markerType y numDocument al servidor
        console.log('Tipo de marcador seleccionado:', markerType);
        console.log('Número de documento:', numDocument);

        // Ejemplo de envío con fetch
        fetch('/submit-marker', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                type: markerType,
                document_number: numDocument
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error HTTP: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Respuesta del servidor:', data);

            //Cerrar el modal de bootstrap
            const modal = bootstrap.Modal.getInstance(document.getElementById('staticBackdrop'));
            if (modal) {
                modal.hide();
            }
            
            
            // Limpiar el campo de búsqueda
            document.getElementById('num_document').value = '';
            // Limpiar el nombre del empleado
            document.getElementById('employee_name').innerHTML = '<span class="text-muted">Ingrese su numero de documento para continuar</span>';
            // Limpiar la tabla de marcadores
            document.querySelectorAll('.marker-table td').forEach(td => {
                td.classList.remove('marked');
                td.classList.remove('checked');
                td.classList.add('inactive');
                const img = td.querySelector('img');
                if(img) {
                    img.src = '/images/registro-time.png'; // Cambia la ruta a la imagen original
                }
            });


            // ✅ Mostrar alerta de éxito
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: data.message || 'Marcación registrada correctamente',
                timer: 2000,
                showConfirmButton: false
            });


            // Disable el botón de confirmación
            const btnConfirm = document.getElementById('btn-confirm');
            btnConfirm.disabled = true;

        })
        .catch(error => {
            console.error('Error al enviar los datos:', error);
        });


    } else {
        console.warn('No se ha seleccionado ningún marcador.');
    }
});





