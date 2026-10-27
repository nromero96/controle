@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-4"><h3 class="fw-bold mb-1">Mi perfil</h3><p class="text-muted mb-0">Administre sus datos de acceso y la seguridad de su cuenta.</p></div>
    <div class="row g-4">
        <div class="col-lg-4">
            <aside class="card border-0 shadow-sm">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mb-3 fw-bold fs-3" style="width: 76px; height: 76px;" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</div>
                    <h5 class="fw-bold text-break mb-1">{{ $user->name }}</h5>
                    <p class="text-muted small text-break">{{ $user->email }}</p>
                    <span class="badge text-bg-light border">Cuenta de administración</span>
                    <hr class="my-4">
                    <p class="text-muted small mb-0">Cuenta creada el {{ $user->created_at?->format('d/m/Y') ?? '—' }}</p>
                </div>
            </aside>
        </div>
        <div class="col-lg-8">
            <section class="card border-0 shadow-sm mb-4" aria-labelledby="profile-heading">
                <div class="card-body p-4">
                    <h5 id="profile-heading" class="fw-bold mb-1">Datos personales</h5>
                    <p class="small text-muted mb-4">El nombre se muestra en el encabezado y el correo se utiliza para iniciar sesión.</p>
                    @if(session('profile_success'))<div class="alert alert-success small" role="status">{{ session('profile_success') }}</div>@endif
                    <form action="{{ route('profile.update') }}" method="POST">
                        @csrf @method('PUT')
                        <div class="mb-3">
                            <label for="profile_name" class="form-label">Nombre</label>
                            <input id="profile_name" name="name" type="text" required maxlength="255" autocomplete="name" class="form-control @error('name', 'profile') is-invalid @enderror" value="{{ old('name', $user->name) }}">
                            @error('name', 'profile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="profile_email" class="form-label">Correo de acceso</label>
                            <input id="profile_email" name="email" type="email" required maxlength="255" autocomplete="email" class="form-control @error('email', 'profile') is-invalid @enderror" value="{{ old('email', $user->email) }}">
                            @error('email', 'profile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-4">
                            <label for="profile_current_password" class="form-label">Contraseña actual <span class="small text-muted">(solo para cambiar el correo)</span></label>
                            <input id="profile_current_password" name="current_password" type="password" autocomplete="current-password" class="form-control @error('current_password', 'profile') is-invalid @enderror" aria-describedby="profile-password-help">
                            @error('current_password', 'profile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div id="profile-password-help" class="form-text">Para cambiar únicamente el nombre puede dejarla vacía.</div>
                        </div>
                        <button class="btn btn-primary px-4">Guardar datos</button>
                    </form>
                </div>
            </section>
            <section class="card border-0 shadow-sm" aria-labelledby="password-heading">
                <div class="card-body p-4">
                    <h5 id="password-heading" class="fw-bold mb-1">Cambiar contraseña</h5>
                    <p class="small text-muted mb-4">Utilice una contraseña de al menos 8 caracteres, distinta de la actual.</p>
                    @if(session('password_success'))<div class="alert alert-success small" role="status">{{ session('password_success') }}</div>@endif
                    <form action="{{ route('profile.password') }}" method="POST">
                        @csrf @method('PUT')
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Contraseña actual</label>
                            <input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="form-control @error('current_password', 'password') is-invalid @enderror">
                            @error('current_password', 'password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="password" class="form-label">Nueva contraseña</label>
                                <input id="password" name="password" type="password" required minlength="8" maxlength="255" autocomplete="new-password" class="form-control @error('password', 'password') is-invalid @enderror">
                                @error('password', 'password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="password_confirmation" class="form-label">Confirmar nueva contraseña</label>
                                <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" maxlength="255" autocomplete="new-password" class="form-control">
                            </div>
                        </div>
                        <button class="btn btn-outline-primary px-4">Actualizar contraseña</button>
                    </form>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
