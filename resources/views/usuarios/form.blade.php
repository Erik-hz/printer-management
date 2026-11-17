@extends('layouts.app')

@php
    $editando = $usuario->exists;
    $activo = (string) old('activo', $editando ? (int) $usuario->activo : 1);
@endphp

@section('titulo', $editando ? 'Editar usuario' : 'Nuevo usuario')

@section('contenido')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('usuarios.index') }}">Usuarios</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $editando ? 'Editar' : 'Nuevo' }}</li>
        </ol>
    </nav>

    <h1 class="h3 mb-3">{{ $editando ? 'Editar usuario' : 'Nuevo usuario' }}</h1>

    <form method="POST" novalidate class="card card-body mb-4"
          action="{{ $editando ? route('usuarios.update', $usuario) : route('usuarios.store') }}">
        @csrf
        @if ($editando)
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <label for="name" class="form-label">Nombre completo</label>
                <input id="name" type="text" name="name" maxlength="255"
                       value="{{ old('name', $usuario->name) }}" class="form-control" required>
            </div>

            <div class="col-md-6">
                <label for="email" class="form-label">Correo electrónico</label>
                <input id="email" type="email" name="email" maxlength="254"
                       value="{{ old('email', $usuario->email) }}" class="form-control" required>
            </div>

            <div class="col-md-6">
                <label for="rol_id" class="form-label">Rol</label>
                <select id="rol_id" name="rol_id" class="form-select" required>
                    <option value="">Seleccione…</option>
                    @foreach ($roles as $rol)
                        <option value="{{ $rol->id }}" @selected((int) old('rol_id', $usuario->rol_id) === $rol->id)>
                            {{ ucfirst(str_replace('_', ' ', $rol->nombre)) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input type="hidden" name="activo" value="0">
                    <input id="activo" type="checkbox" class="form-check-input" name="activo" value="1"
                           @checked($activo === '1')>
                    <label for="activo" class="form-check-label">Usuario activo</label>
                </div>
            </div>

            @unless ($editando)
                <div class="col-md-6">
                    <label for="password" class="form-label">Contraseña</label>
                    <input id="password" type="password" name="password" class="form-control"
                           autocomplete="new-password" required>
                    <div class="form-text">Al menos 10 caracteres, con una letra y un número.</div>
                </div>

                <div class="col-md-6">
                    <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                    <input id="password_confirmation" type="password" name="password_confirmation"
                           class="form-control" autocomplete="new-password" required>
                </div>
            @endunless
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>

    @if ($editando)
        <form method="POST" action="{{ route('usuarios.clave', $usuario) }}" novalidate class="card card-body">
            @csrf
            @method('PUT')

            <h2 class="h5 mb-3">Restablecer contraseña</h2>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="nueva_password" class="form-label">Nueva contraseña</label>
                    <input id="nueva_password" type="password" name="password" class="form-control"
                           autocomplete="new-password" required>
                    <div class="form-text">Al menos 10 caracteres, con una letra y un número.</div>
                </div>

                <div class="col-md-6">
                    <label for="nueva_password_confirmation" class="form-label">Confirmar contraseña</label>
                    <input id="nueva_password_confirmation" type="password" name="password_confirmation"
                           class="form-control" autocomplete="new-password" required>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-outline-primary">Restablecer contraseña</button>
            </div>
        </form>
    @endif
@endsection