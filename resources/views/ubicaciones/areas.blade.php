@extends('layouts.app')

@section('titulo', 'Áreas de '.$lugar->nombre)

@section('contenido')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('zonas.index') }}">Zonas</a></li>
            <li class="breadcrumb-item">
                <a href="{{ route('lugares.index', $lugar->zona) }}">{{ $lugar->zona->nombre }}</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">{{ $lugar->nombre }}</li>
        </ol>
    </nav>

    <h1 class="h3 mb-3">Áreas de {{ $lugar->nombre }}</h1>

    @include('catalogos._nuevo', [
        'accion' => route('areas.store', $lugar),
        'etiqueta' => 'Nueva área',
        'max' => 100,
        'campoActivo' => 'activa',
    ])

    @include('catalogos._tabla', [
        'items' => $areas,
        'max' => 100,
        'campoActivo' => 'activa',
        'etiquetaConteo' => 'Impresoras',
        'campoConteo' => 'impresoras_count',
        'rutas' => ['actualizar' => 'areas.update', 'eliminar' => 'areas.destroy'],
    ])
@endsection