<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

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
<body>
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
                            <i class='bx bxs-moon' id="theme-button"></i>
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

                    

                </div>
                
                
                {{-- Boton Marcar Asistencia --}}
                {{-- <a href="#" class="btn btn-primary btn-lg" style="position: absolute; bottom: 1rem; left: 50%; transform: translateX(-50%);">Marcar Asistencia</a> --}}

                <div class="text-center">

                  <!-- Button trigger modal -->
                  <button type="button" class="btn w-50 btn-primary rounded-0 rounded-top pt-2 pb-2 btn-open-marker" data-bs-toggle="modal" data-bs-target="#staticBackdrop">
                      <b>Marcar Asistencia</b>
                  </button>  

                </div>

                <!-- Modal -->
                <div class="modal fade" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header border-0 pb-1">
                        <img src="{{ asset('images/icono-bio.png') }}" alt="Logo" width="40" height="40">
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        
                        <div>
                          <input type="text" class="form-control text-center num_document" id="num_document" placeholder="Ingrese su numero de documento">
                        </div>
                        <hr>
                        <div>
                          <h6 class="text-center">MARCAR</h6>
                          <div class="text-center" id="employee_name">
                            <span class="text-muted">Ingrese su numero de documento para continuar</span>
                          </div>

                          <table class="marker-table">
                            <tr>
                              <td data-marker="entry" class="inactive">
                                Entrada Laboral
                                <img src="{{ asset('images/registro-time.png') }}" alt="Entrada Laboral">
                              </td>
                              <td data-marker="break_out" class="inactive">
                                Salida Refrigerio
                                <img src="{{ asset('images/registro-time.png') }}" alt="Salida Refrigerio">
                              </td>
                            </tr>
                            <tr>
                              <td data-marker="break_in" class="inactive">
                                Retorno Refrigerio
                                <img src="{{ asset('images/registro-time.png') }}" alt="Retorno Refrigerio">
                              </td>
                              <td data-marker="exit" class="inactive">
                                Salida Laboral
                                <img src="{{ asset('images/registro-time.png') }}" alt="Salida Laboral">
                              </td>
                            </tr>
                          </table>

                          <button id="btn-confirm" class="btn btn-primary w-100 mt-2 btn-confirm" disabled>CONFIRMAR</button>

                          <p class="text-center mt-4">
                            <small style="font-size: 10px;display: block;line-height: 12px;">
                              La marcación debe realizarse desde tu PC asignada. Cada registro queda vinculado a tu usuario, IP y hora exacta.
                            </small>
                          </p>

                        </div>

                      </div>
                    </div>
                  </div>
                </div>
                  

            </div>
    </section>

    

</body>
</html>