@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="mb-4">
                <h3 class="fw-bold mb-1">Configuración</h3>
                <p class="text-muted mb-0">Personaliza los datos de tu empresa y las condiciones para marcar asistencia.</p>
            </div>

            @if(session('success'))
                <div class="alert alert-success" role="status">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger" role="alert">Revisa los campos indicados antes de guardar la configuración.</div>
            @endif

            <form action="{{ route('settings.update') }}" method="POST">
                @csrf
                @method('PUT')

                <section class="card border-0 shadow-sm mb-4" aria-labelledby="company-heading">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <span class="badge bg-primary-subtle text-primary rounded-3 p-3" aria-hidden="true">01</span>
                            <div>
                                <h5 id="company-heading" class="fw-bold mb-1">Datos de la empresa</h5>
                                <p class="text-muted small mb-0">Información general de tu organización.</p>
                            </div>
                        </div>
                        <label for="company_name" class="form-label fw-semibold">Nombre de la empresa</label>
                        <input type="text" class="form-control @error('company_name') is-invalid @enderror" id="company_name" name="company_name" value="{{ old('company_name', $companyName) }}" placeholder="Ingrese el nombre de la empresa" required>
                        @error('company_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </section>

                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <section class="card border-0 shadow-sm h-100" aria-labelledby="access-heading">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <span class="badge bg-primary-subtle text-primary rounded-3 p-3" aria-hidden="true">02</span>
                                    <div>
                                        <h5 id="access-heading" class="fw-bold mb-1">Acceso al marcador</h5>
                                        <p class="text-muted small mb-0">Define desde dónde se puede marcar.</p>
                                    </div>
                                </div>
                                <div class="bg-body-tertiary rounded-3 p-3 mb-4">
                                    <div class="form-check form-switch mb-2">
                                        <input type="hidden" name="restrict_ip_enabled" value="0">
                                        <input class="form-check-input" role="switch" type="checkbox" id="restrict_ip_enabled" name="restrict_ip_enabled" value="1" aria-describedby="ip-help" @checked(old('restrict_ip_enabled', $restrictIpEnabled))>
                                        <label class="form-check-label fw-semibold" for="restrict_ip_enabled">Restringir por IP</label>
                                    </div>
                                    <p id="ip-help" class="text-muted small mb-0">Activa esta opción para permitir únicamente las IP configuradas. Al desactivarla, se podrá marcar desde cualquier IP.</p>
                                </div>
                                @error('restrict_ip_enabled') <p class="text-danger small">{{ $message }}</p> @enderror
                                <label for="allowed_ips" class="form-label fw-semibold">IP permitidas</label>
                                <textarea class="form-control @error('allowed_ips') is-invalid @enderror" id="allowed_ips" name="allowed_ips" rows="3" aria-describedby="allowed-ips-help" placeholder="Ej.: 192.168.1.10, 190.10.20.30">{{ old('allowed_ips', $allowedIps) }}</textarea>
                                @error('allowed_ips') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div id="allowed-ips-help" class="form-text">Separa las direcciones con comas. Son obligatorias cuando la restricción está activada.</div>
                            </div>
                        </section>
                    </div>
                    <div class="col-lg-6">
                        <section class="card border-0 shadow-sm h-100" aria-labelledby="camera-heading">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <span class="badge bg-primary-subtle text-primary rounded-3 p-3" aria-hidden="true">03</span>
                                    <div>
                                        <h5 id="camera-heading" class="fw-bold mb-1">Cámara y evidencia</h5>
                                        <p class="text-muted small mb-0">Conserva una foto de cada marcación.</p>
                                    </div>
                                </div>
                                <div class="bg-body-tertiary rounded-3 p-3 mb-4">
                                    <div class="form-check form-switch mb-2">
                                        <input type="hidden" name="camera_enabled" value="0">
                                        <input class="form-check-input" role="switch" type="checkbox" id="camera_enabled" name="camera_enabled" value="1" aria-describedby="camera-help" @checked(old('camera_enabled', $cameraEnabled))>
                                        <label class="form-check-label fw-semibold" for="camera_enabled">Exigir foto al marcar</label>
                                    </div>
                                    <p id="camera-help" class="text-muted small mb-0">La foto se captura al confirmar. Si la cámara no está disponible o no tiene permiso, no se podrá completar la marcación.</p>
                                </div>
                                @error('camera_enabled') <p class="text-danger small">{{ $message }}</p> @enderror
                                <h6 class="fw-semibold">Para utilizar la cámara</h6>
                                <p class="text-muted small mb-3">El marcador debe abrirse con HTTPS o en localhost, y el usuario debe permitir el acceso a la cámara.</p>
                                <div class="border-start border-primary border-3 ps-3">
                                    <p class="small text-muted mb-0">Las fotos se consultan en Asistencias con una sesión iniciada. Son evidencia para revisión; no verifican automáticamente la identidad.</p>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <section class="card border-0 shadow-sm mb-4" aria-labelledby="overtime-heading">
                    <div class="card-body p-4">
                        <h5 id="overtime-heading" class="fw-bold mb-1">04 · Horas extra y almuerzo</h5>
                        <p class="text-muted small">Se comparan las horas efectivamente trabajadas, descontando el almuerzo, con la jornada neta programada. Las extras requieren aprobación.</p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="overtime_minimum_minutes" class="form-label">Mínimo de minutos extra</label>
                                <input type="number" min="0" max="1440" step="1" required class="form-control" id="overtime_minimum_minutes" name="overtime_minimum_minutes" value="{{ old('overtime_minimum_minutes', $overtimeMinimum) }}">
                                @error('overtime_minimum_minutes') <div class="text-danger">{{ $message }}</div> @enderror
                                <div class="form-text">0 cuenta desde el primer segundo. Con mínimo 15, 10 minutos no califican y 20 pasan completos a aprobación; no se restan los primeros 15.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="reference_lunch_minutes" class="form-label">Almuerzo de referencia (minutos)</label>
                                <input type="number" min="45" max="720" step="1" required class="form-control" id="reference_lunch_minutes" name="reference_lunch_minutes" value="{{ old('reference_lunch_minutes', $referenceLunch) }}">
                                @error('reference_lunch_minutes') <div class="text-danger">{{ $message }}</div> @enderror
                                <div class="form-text">Se descuenta de la jornada programada solo cuando el empleado no tiene horas de almuerzo configuradas. El trabajo real siempre descuenta el almuerzo marcado.</div>
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label for="minimum_lunch_minutes" class="form-label">Mínimo de almuerzo (minutos)</label>
                                <input type="number" min="45" max="720" required class="form-control" id="minimum_lunch_minutes" name="minimum_lunch_minutes" value="{{ old('minimum_lunch_minutes', $minimumLunch) }}">
                                @error('minimum_lunch_minutes')<div class="text-danger">{{ $message }}</div>@enderror
                                <div class="form-text">Desde 45 minutos. Configura 60 si la empresa establece una hora. Los intervalos menores solo generan incidencia si también superan la tolerancia configurada.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="maximum_lunch_minutes" class="form-label">Alerta por almuerzo largo (minutos)</label>
                                <input type="number" min="45" max="720" required class="form-control" id="maximum_lunch_minutes" name="maximum_lunch_minutes" value="{{ old('maximum_lunch_minutes', $maximumLunch) }}">
                                @error('maximum_lunch_minutes')<div class="text-danger">{{ $message }}</div>@enderror
                                <div class="form-text">Superar este tiempo requiere confirmar el almuerzo real. También se alerta si el retorno está a 5 minutos o menos de la salida laboral. No impide registrar la salida.</div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="lunch_tolerance_minutes" class="form-label">Tolerancia para almuerzo corto (minutos)</label>
                                <input type="number" min="0" max="30" step="1" required class="form-control" id="lunch_tolerance_minutes" name="lunch_tolerance_minutes" value="{{ old('lunch_tolerance_minutes', $lunchTolerance) }}">
                                @error('lunch_tolerance_minutes')<div class="text-danger">{{ $message }}</div>@enderror
                                <div class="form-text">Umbral guardado para la alerta: <strong>{{ max(1, (int) $minimumLunch - (int) $lunchTolerance) }} minutos</strong>. Se calcula restando la tolerancia al mínimo. Por ejemplo, 45 − 25 = 20 minutos. 0 desactiva la tolerancia. Se conserva el tiempo real, sin redondear ni descontar minutos ficticios.</div>
                                <div class="form-text">Esta tolerancia solo controla las incidencias del sistema. No reduce el descanso mínimo de 45 minutos aplicable al trabajo en horario corrido en Perú. Los cambios se aplican a nuevas jornadas; las que ya guardaron sus reglas las conservan.</div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="exit_tolerance_minutes" class="form-label">Tolerancia de salida (minutos)</label>
                                <input type="number" min="0" max="60" step="1" required class="form-control" id="exit_tolerance_minutes" name="exit_tolerance_minutes" value="{{ old('exit_tolerance_minutes', $exitTolerance) }}">
                                @error('exit_tolerance_minutes')<div class="text-danger">{{ $message }}</div>@enderror
                                <div class="form-text">Margen antes y después de la salida programada. Con salida a las 17:00 y tolerancia de 5 minutos, desde 16:55 hasta 17:05 se muestra como normal. Fuera del margen se indica salida anticipada o posterior, sin exigir regularización.</div>
                                <div class="form-text">Conserva las horas reales y no suma ni resta minutos al trabajo. Las extras se calculan con el tiempo real y su mínimo configurable, aunque la salida esté dentro de tolerancia.</div>
                            </div>
                        </div>
                        <p class="small text-muted mt-3 mb-0">Las reglas quedan guardadas al marcar ingreso. Los registros antiguos sin reglas guardadas usan estos valores hasta su revisión.</p>
                    </div>
                </section>

                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 bg-white rounded-3 border p-3">
                    <p class="text-muted small mb-0">Los cambios se aplican al guardar la configuración.</p>
                    <button type="submit" class="btn btn-primary px-4 py-2">Guardar configuración</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
