@extends('layouts.app')

@php($editando = $proveedor->exists)

@section('titulo', $editando ? 'Editar proveedor' : 'Nuevo proveedor')

@section('contenido')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('proveedores.index') }}">Proveedores</a></li>
            <li class="breadcrumb-item active">{{ $editando ? 'Editar' : 'Nuevo' }}</li>
        </ol>
    </nav>

    <h1 class="h3 mb-3">{{ $editando ? 'Editar proveedor' : 'Nuevo proveedor' }}</h1>

    <form method="POST" novalidate class="card card-body"
          action="{{ $editando ? route('proveedores.update', $proveedor) : route('proveedores.store') }}">
        @csrf
        @if ($editando) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-6">
                <label for="nombre" class="form-label">Nombre</label>
                <input id="nombre" name="nombre" maxlength="150" required
                       value="{{ old('nombre', $proveedor->nombre) }}"
                       class="form-control @error('nombre') is-invalid @enderror">
                @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="nit" class="form-label">NIT</label>
                <input id="nit" name="nit" maxlength="30" value="{{ old('nit', $proveedor->nit) }}"
                       class="form-control @error('nit') is-invalid @enderror">
                @error('nit')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="contacto" class="form-label">Persona de contacto</label>
                <input id="contacto" name="contacto" maxlength="150" value="{{ old('contacto', $proveedor->contacto) }}"
                       class="form-control @error('contacto') is-invalid @enderror">
                @error('contacto')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="telefono" class="form-label">Teléfono</label>
                <input id="telefono" name="telefono" maxlength="30" value="{{ old('telefono', $proveedor->telefono) }}"
                       class="form-control @error('telefono') is-invalid @enderror">
                @error('telefono')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-5">
                <label for="correo" class="form-label">Correo</label>
                <input id="correo" type="email" name="correo" maxlength="254" value="{{ old('correo', $proveedor->correo) }}"
                       class="form-control @error('correo') is-invalid @enderror">
                @error('correo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <input type="hidden" name="activo" value="0">
                <div class="form-check mb-2">
                    <input id="activo" type="checkbox" name="activo" value="1" class="form-check-input"
                           @checked((string) old('activo', $editando ? (int) $proveedor->activo : 1) === '1')>
                    <label for="activo" class="form-check-label">Proveedor activo</label>
                </div>
            </div>
        </div>
        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('proveedores.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
@endsection
