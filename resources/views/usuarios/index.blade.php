@extends('layouts.app')

@section('titulo', 'Usuarios')

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Usuarios</h1>
        <a href="{{ route('usuarios.create') }}" class="btn btn-primary">Nuevo usuario</a>
    </div>

    <div class="table-responsive">
        <table class="table table-sm align-middle bg-body border">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo electrónico</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($usuarios as $usuario)
                    <tr>
                        <td>
                            {{ $usuario->name }}
                            @if ($usuario->id === auth()->id())
                                <span class="text-body-secondary">(usted)</span>
                            @endif
                        </td>
                        <td>{{ $usuario->email }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $usuario->rol->nombre)) }}</td>
                        <td>
                            <span class="badge text-bg-{{ $usuario->activo ? 'success' : 'secondary' }}">
                                {{ $usuario->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('usuarios.edit', $usuario) }}"
                               class="btn btn-sm btn-outline-primary">Editar</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection