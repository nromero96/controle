@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    
                    <div class="row">
                        <div class="col-md-6 d-flex flex-column justify-content-center">
                            <h4 class="mb-0">{{ __('Editar Sede') }}</h4>
                        </div>
                    </div>

                </div>

                <div class="card-body">
                    
                    <form action="{{ route('branches.update', $branch->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="name" class="form-label mb-0">Nombre</label>
                                <input type="text" class="form-control" id="name" name="name" maxlength="255" value="{{ old('name', $branch->name) }}" required>
                                @error('name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="address" class="form-label mb-0">Dirección</label>
                                <input type="text" class="form-control" id="address" name="address" maxlength="255" value="{{ old('address', $branch->address) }}" required>
                                @error('address')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label mb-0">Teléfono <small class="text-muted">(opcional)</small></label>
                                <input type="tel" class="form-control" maxlength="20" id="phone" name="phone" value="{{ old('phone', $branch->phone) }}">
                                @error('phone')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label mb-0">Correo <small class="text-muted">(opcional)</small></label>
                                <input type="email" class="form-control" id="email" name="email" maxlength="255" value="{{ old('email', $branch->email) }}">
                                @error('email')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-check form-switch mb-4">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" role="switch" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $branch->is_active))>
                            <label class="form-check-label" for="is_active">Sede activa</label>
                            <div class="form-text">Desactiva la sede para dejar de usarla en nuevas asignaciones, conservando sus empleados actuales.</div>
                            @error('is_active') <div class="text-danger">{{ $message }}</div> @enderror
                        </div>
                        <a href="{{ route('branches.index') }}" class="btn btn-outline-secondary">Volver</a>
                        <button type="submit" class="btn btn-primary">Actualizar Sede</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
