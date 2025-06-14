@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    
                    <div class="row">
                        <div class="col-md-6 d-flex flex-column justify-content-center">
                            <h4 class="mb-0">{{ __('Editar de Sede') }}</h4>
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
                                <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $branch->name) }}" required>
                                @error('name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="address" class="form-label mb-0">Dirección</label>
                                <input type="text" class="form-control" id="address" name="address" value="{{ old('address', $branch->address) }}" required>
                                @error('address')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label mb-0">Teléfono</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone', $branch->phone) }}" required>
                                @error('phone')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label mb-0">Correo</label>
                                <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $branch->email) }}" required>
                                @error('email')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Actualizar Sede</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
