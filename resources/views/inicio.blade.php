@extends('layouts.app')

@section('titulo', 'Inicio')

@section('contenido')
    <h1 class="h3 mb-3">Inicio</h1>

    <div class="card">
        <div class="card-body">
            <p class="mb-1">Bienvenido, <strong>{{ auth()->user()->name }}</strong>.</p>
            <p class="text-body-secondary mb-0">
                Rol: {{ ucfirst(str_replace('_', ' ', auth()->user()->rol->nombre)) }}
            </p>
        </div>
    </div>
@endsection