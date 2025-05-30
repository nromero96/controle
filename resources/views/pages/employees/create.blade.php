@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    
                    <div class="row">
                        <div class="col-md-6 d-flex flex-column justify-content-center">
                            <h4 class="mb-0">{{ __('Registro de Empleado') }}</h4>
                        </div>
                    </div>

                </div>

                <div class="card-body">
                    
                    <form action="{{ route('employees.store') }}" method="POST">
                        @csrf

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="document_type" class="form-label mb-0">Tipo Documento</label>
                                <select class="form-select" id="document_type" name="document_type" required>
                                    <option value="" disabled selected @if(old('document_type')) selected @endif>Seleccione un tipo de documento</option>
                                    <option value="DNI" @if(old('document_type') == 'DNI') selected @endif>DNI</option>
                                    <option value="RUC" @if(old('document_type') == 'RUC') selected @endif>RUC</option>
                                    <option value="Pasaporte" @if(old('document_type') == 'Pasaporte') selected @endif>Pasaporte</option>
                                    <option value="Carnet de Extranjería" @if(old('document_type') == 'Carnet de Extranjería') selected @endif>Carnet de Extranjería</option>
                                </select>
                                @error('document_type')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="document_number" class="form-label mb-0">Número de Documento</label>
                                <input type="text" class="form-control" id="document_number" name="document_number"  value="{{ old('document_number') }}" required>
                                @error('document_number')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="first_name" class="form-label mb-0">Nombre</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="{{ old('first_name') }}" required> 
                                @error('first_name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="last_name_father" class="form-label mb-0">Apellido Paterno</label>
                                <input type="text" class="form-control" id="last_name_father" name="last_name_father" value="{{ old('last_name_father') }}" required>
                                @error('last_name_father')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="last_name_mother" class="form-label mb-0">Apellido Materno</label>
                                <input type="text" class="form-control" id="last_name_mother" name="last_name_mother" value="{{ old('last_name_mother') }}" required>
                                @error('last_name_mother')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="mb-3">
                                <label for="email" class="form-label mb-0">Correo</label>
                                <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" required>
                                @error('email')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <hr>
                                <h5>Horario por Día</h5>

                                @php
                                    $dias = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
                                @endphp

                                @foreach($dias as $dia)
                                    <div class="card mb-2">
                                        <div class="card-body">
                                            <div class="form-check form-switch mb-2">
                                                <input class="form-check-input toggle-day" type="checkbox" id="check_{{ $dia }}" name="schedule[{{ $dia }}][active]">
                                                <label class="form-check-label" for="check_{{ $dia }}">{{ $dia }}</label>
                                            </div>

                                            <div class="row horario-fields d-none" id="fields_{{ $dia }}">
                                                <input type="hidden" name="schedule[{{ $dia }}][day]" value="{{ $dia }}">

                                                <div class="col-md-3">
                                                    <label>Entrada</label>
                                                    <input type="time" class="form-control" name="schedule[{{ $dia }}][start_time]">
                                                </div>
                                                <div class="col-md-3">
                                                    <label>Salida</label>
                                                    <input type="time" class="form-control" name="schedule[{{ $dia }}][end_time]">
                                                </div>
                                                <div class="col-md-3">
                                                    <label>Inicio Descanso</label>
                                                    <input type="time" class="form-control" name="schedule[{{ $dia }}][break_start]">
                                                </div>
                                                <div class="col-md-3">
                                                    <label>Fin Descanso</label>
                                                    <input type="time" class="form-control" name="schedule[{{ $dia }}][break_end]">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Agregar Empleado</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
