@extends('layouts.app')

@section('titulo', 'Lugares de '.$zona->nombre)

@section('contenido')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('zonas.index') }}">Zonas</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $zona->nombre }}</li>
        </ol>
    </nav>

    <h1 class="h3 mb-3">Lugares de {{ $zona->nombre }}</h1>

    @include('catalogos._nuevo', [
        'accion' => route('lugares.store', $zona),
        'etiqueta' => 'Nuevo lugar',
        'max' => 100,
        'campoActivo' => 'activo',
    ])

    @include('catalogos._tabla', [
        'items' => $lugares,
        'max' => 100,
        'campoActivo' => 'activo',
        'etiquetaConteo' => 'Áreas',
        'campoConteo' => 'areas_count',
        'etiquetaHijos' => 'Ver áreas',
        'rutas' => ['actualizar' => 'lugares.update', 'eliminar' => 'lugares.destroy', 'hijos' => 'areas.index'],
    ])
@endsection