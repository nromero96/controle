<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('images/icono-bio.png') }}" type="image/png">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">

    <!--==================== BOXICONS ====================-->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css">

    <!-- Scripts -->
    @vite(['resources/sass/marker.scss', 'resources/js/marker.js'])


    <style>

    </style>


</head>
<body data-server-time="{{ now()->toIso8601String() }}" data-timezone="{{ config('app.timezone') }}" data-camera-enabled="{{ $cameraEnabled ? '1' : '0' }}">
    <section class="clock container">
            <div class="clock__container grid">
                <div class="clock__content grid">
                    <div class="clock__circle">
                        <span class="clock__twelve"></span>
                        <span class="clock__three"></span>
                        <span class="clock__six"></span>
                        <span class="clock__nine"></span>
    
                        <div class="clock__rounder"></div>
                        <div class="clock__hour" id="clock-hour"></div>
                        <div class="clock__minutes" id="clock-minutes"></div>
                        <div class="clock__seconds" id="clock-seconds"></div>

                        <!-- Dark/light button -->
                        <div class="clock__theme">
                            <i class='bx bxs-moon' id="theme-button" role="button" tabindex="0" aria-label="Cambiar tema"></i>
                        </div>
                    </div>

                    <div>
                        <div class="clock__text">
                            <div class="clock__text-hour" id="text-hour"></div>
                            <div class="clock__text-minutes" id="text-minutes"></div>
                            <div class="clock__text-ampm" id="text-ampm"></div>
                        </div>
    
                        <div class="clock__date">
                            <!-- <span id="date-day-week"></span> -->
                            <span id="date-day"></span>
                            <span id="date-month"></span>
                            <span id="date-year"></span>
                        </div>
                    </div>

                    <div class="text-center">

                  <!-- Button trigger modal -->
                  <button type="button" class="btn w-50 btn-primary pt-3 pb-3 btn-open-marker" data-bs-toggle="modal" data-bs-target="#staticBackdrop">
                      <b>Marcar Asistencia</b>
                  </button>  

                </div>

                    <img src="{{ asset('images/logo.svg') }}" alt="Controle" class="clock__logo">

                </div>
                
                

                <!-- Modal -->
                <div class="modal fade marker-modal" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header border-0 pb-1">
                        <h5 class="modal-title" id="staticBackdropLabel">Registrar asistencia</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar marcador"></button>
                      </div>
                      <div class="modal-body">
                        
                        <div>
                          <label for="num_document" class="visually-hidden">Número de documento</label>
                          <input type="text" class="form-control text-center num_document" id="num_document" maxlength="20" autocomplete="off" autocapitalize="off" spellcheck="false" aria-describedby="document-help" placeholder="Ingrese N° de documento">
                          <small id="document-help" class="marker-helper text-muted d-block mt-2">Consulta tus marcaciones con tu documento.</small>
                        </div>
                        <hr>
                        <div>
                          <h6 class="marker-section-title text-center">Tus marcaciones de hoy</h6>
                          <div class="text-center" id="employee_name" role="status" aria-live="polite">
                            <span class="text-muted">Ingrese su numero de documento para continuar</span>
                          </div>

                          <p id="employee-schedule" class="marker-helper text-muted text-center mb-1"></p>
                          <div id="marker-guidance" class="marker-guidance text-center mt-2" role="status" aria-live="polite">Consulta tu documento para comenzar.</div>
                          <table class="marker-table" aria-label="Próxima marcación seleccionada; puedes elegir otra opción disponible">
                            <tr>
                              <td data-marker="entry" class="inactive" role="button" tabindex="-1" aria-disabled="true" aria-pressed="false">
                                Entrada Laboral
                                <img src="{{ asset('images/registro-time.png') }}" alt="Entrada Laboral">
                              <span class="marker-state d-block small mt-1" data-mark-state>Pendiente</span>
                              </td>
                              <td data-marker="break_out" class="inactive" role="button" tabindex="-1" aria-disabled="true" aria-pressed="false">
                                Salida al almuerzo
                                <img src="{{ asset('images/registro-time.png') }}" alt="Salida al almuerzo">
                              <span class="marker-state d-block small mt-1" data-mark-state>Pendiente</span>
                              </td>
                            </tr>
                            <tr>
                              <td data-marker="break_in" class="inactive" role="button" tabindex="-1" aria-disabled="true" aria-pressed="false">
                                Retorno del almuerzo
                                <img src="{{ asset('images/registro-time.png') }}" alt="Retorno del almuerzo">
                              <span class="marker-state d-block small mt-1" data-mark-state>Pendiente</span>
                              </td>
                              <td data-marker="exit" class="inactive" role="button" tabindex="-1" aria-disabled="true" aria-pressed="false">
                                Salida Laboral
                                <img src="{{ asset('images/registro-time.png') }}" alt="Salida Laboral">
                              <span class="marker-state d-block small mt-1" data-mark-state>Pendiente</span>
                              </td>
                            </tr>
                          </table>


                          <p id="forgotten-lunch-help" class="marker-helper marker-note mt-3 mb-2" hidden>Si olvidaste marcar el almuerzo, registra tu salida laboral y solicita su regularización.</p>
                          <button id="btn-confirm" class="btn btn-primary w-100 mt-2 btn-confirm" disabled>Ingresa tu documento</button>

                          <p class="marker-privacy text-center mt-3 mb-1">
                              Se guardan tu documento, IP y hora{{ $cameraEnabled ? ', además de una foto como evidencia' : '' }}.
                          </p>

                        </div>

                      </div>
                    </div>
                  </div>
                  @if($cameraEnabled)
                  <aside class="marker-camera-widget" aria-labelledby="camera-widget-title">
                    <div class="marker-camera-widget__header">
                      <span class="marker-camera-widget__dot" aria-hidden="true"></span>
                      <h6 id="camera-widget-title">Cámara de marcación</h6>
                    </div>
                    <div class="marker-camera-widget__body">
                      <video id="marker-camera" autoplay muted playsinline aria-label="Vista previa de la foto de asistencia"></video>
                      <div class="marker-camera-widget__info">
                        <p id="camera-status" role="status" aria-live="polite">Consulta tu documento para activar la cámara.</p>
                        <button type="button" id="camera-retry" class="btn btn-outline-secondary btn-sm">Activar cámara</button>
                      </div>
                    </div>
                  </aside>
                  @endif
                </div>
                  

            </div>
    </section>

    

    @include('partials.footer')
</body>
</html>