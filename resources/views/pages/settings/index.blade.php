@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    
                    <div class="row">
                        <div class="col-md-6 d-flex flex-column justify-content-center">
                            <h4 class="mb-0">{{ __('Página de configuración') }}</h4>
                        </div>
                    </div>

                </div>

                <div class="card-body">
                    
                    <form action="{{ route('settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="company_name" class="form-label mb-0">Empresa</label>
                                <input type="text" class="form-control" id="company_name" name="company_name"  value="{{ old('company_name', $companyName) }}" placeholder="Ingrese el nombre de la empresa" required>
                                @error('company_name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="allowed_ips	" class="form-label mb-0">IP permitidas</label>
                                <input type="text" class="form-control" id="allowed_ips" name="allowed_ips" value="{{ old('allowed_ips', $allowedIps) }}" placeholder="Ingrese las IPs permitidas separadas por comas" required>
                                @error('allowed_ips')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Guardar Configuración</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
