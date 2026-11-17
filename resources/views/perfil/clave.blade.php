@extends('layouts.app')

@section('titulo', 'Cambiar contraseña')

@section('contenido')
    <h1 class="h3 mb-3">Cambiar contraseña</h1>

    <form method="POST" action="{{ route('perfil.clave.actualizar') }}" novalidate
          class="card card-body" style="max-width: 32rem;">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="current_password" class="form-label">Contraseña actual</label>
            <input id="current_password" type="password" name="current_password" class="form-control"
                   autocomplete="current-password" required>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Nueva contraseña</label>
            <input id="password" type="password" name="password" class="form-control"
                   autocomplete="new-password" required>
            <div class="form-text">Al menos 10 caracteres, con una letra y un número.</div>
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="form-label">Confirmar nueva contraseña</label>
            <input id="password_confirmation" type="password" name="password_confirmation"
                   class="form-control" autocomplete="new-password" required>
        </div>

        <div>
            <button type="submit" class="btn btn-primary">Cambiar contraseña</button>
        </div>
    </form>
@endsection